<?php
declare(strict_types=1);

/** Online forms: build them, share them, collect responses (with optional payment). */
final class FormsController
{
    // ------------------------------------------------------------------ staff: build

    private function form(string $id): array
    {
        return DB::row('SELECT * FROM {p}forms WHERE id = ?', [(int)$id]) ?? abort(404);
    }

    public function index(): void
    {
        $forms = DB::all("SELECT f.*, (SELECT COUNT(*) FROM {p}form_submissions s WHERE s.form_id = f.id AND s.status <> 'cancelled') AS responses FROM {p}forms f ORDER BY f.status = 'closed', f.created_at DESC");
        view('forms/index', ['title' => __('Forms'), 'forms' => $forms]);
    }

    /** Starter templates, so a church doesn't begin with a blank page. */
    public static function templates(): array
    {
        $k = fn(string $type, string $label, bool $req = false, array $opts = [], string $help = '') => ['key' => substr(bin2hex(random_bytes(4)), 0, 8), 'type' => $type, 'label' => $label, 'required' => $req, 'options' => $opts, 'help' => $help];
        return [
            'blank' => [__('Blank form'), []],
            'camp' => [__('Camp or retreat registration'), [
                $k('heading', __('About the camper')), $k('text', __('Camper\'s full name'), true), $k('date', __('Date of birth'), true),
                $k('text', __('Parent or guardian name'), true), $k('phone', __('Parent or guardian mobile'), true), $k('email', __('Parent or guardian email'), true),
                $k('heading', __('Health and safety')), $k('textarea', __('Allergies, medical conditions and medication'), false, [], __('Leave blank if none.')),
                $k('textarea', __('Dietary requirements')), $k('text', __('Emergency contact (name and phone)'), true),
                $k('yesno', __('May we take photos of your child for church use?'), true),
                $k('consent', __('I give permission for my child to attend and for leaders to seek medical treatment in an emergency.'), true),
            ]],
            'baptism' => [__('Baptism enquiry'), [
                $k('text', __('Your name'), true), $k('email', __('Email'), true), $k('phone', __('Mobile')),
                $k('radio', __('Is this for'), true, [__('Me'), __('My child'), __('Someone else')]),
                $k('textarea', __('Tell us a little about your journey of faith')),
                $k('checkboxes', __('Best times to meet'), false, [__('Sunday after church'), __('Weekday evening'), __('Weekday daytime')]),
            ]],
            'enquiry' => [__('General enquiry'), [
                $k('text', __('Your name'), true), $k('email', __('Email'), true), $k('phone', __('Phone')),
                $k('select', __('What is it about?'), true, [__('Visiting us'), __('Weddings'), __('Funerals'), __('Hall hire'), __('Something else')]),
                $k('textarea', __('Your message'), true),
            ]],
        ];
    }

    public function store(): void
    {
        $tpl = self::templates()[(string)input('template', 'blank')] ?? self::templates()['blank'];
        $title = trim(mb_substr((string)input('title', ''), 0, 160)) ?: $tpl[0];
        $id = DB::insert('forms', ['title' => $title, 'slug' => Forms::uniqueSlug(Forms::slugify($title)), 'fields' => json_encode($tpl[1], JSON_UNESCAPED_UNICODE),
            'audience' => 'public', 'status' => 'draft', 'notify_emails' => (string)(user()['email'] ?? ''), 'campus_id' => Campus::current(), 'created_by' => Auth::id(),
            'event_id' => ($ev = input_int('event_id')) && DB::val('SELECT 1 FROM {p}events WHERE id = ?', [$ev]) ? $ev : null]);
        audit('form.created', 'form', $id);
        flash('success', __('Form created. Add or change questions, then open it for responses.'));
        redirect('/forms/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $f = $this->form($id);
        $events = array_column(DB::all("SELECT id, CONCAT(title, ' · ', DATE_FORMAT(start_at, '%e %b %Y')) AS t FROM {p}events WHERE start_at >= CURDATE() AND status = 'scheduled' ORDER BY start_at LIMIT 60"), 't', 'id');
        view('forms/edit', ['title' => $f['title'], 'f' => $f, 'fields' => Forms::fields($f), 'events' => $events, 'responses' => (int)DB::val('SELECT COUNT(*) FROM {p}form_submissions WHERE form_id = ?', [$f['id']])]);
    }

    public function update(string $id): void
    {
        $f = $this->form($id);
        $title = trim(mb_substr((string)input('title', ''), 0, 160));
        if ($title === '') {
            flash('error', __('The title is required.'));
            redirect('/forms/' . $f['id'] . '/edit');
        }
        $slug = Forms::slugify((string)input('slug', '') ?: $title);
        $emails = implode(', ', array_filter(array_map('trim', explode(',', (string)input('notify_emails', ''))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        $price = (int)round((float)str_replace([',', '$'], '', (string)input('price', '0')) * 100);
        $d = [
            'title' => $title, 'slug' => Forms::uniqueSlug($slug, (int)$f['id']), 'intro' => mb_substr((string)input('intro', ''), 0, 5000) ?: null,
            'audience' => input('audience') === 'members' ? 'members' : 'public', 'status' => in_array(input('status'), ['draft', 'open', 'closed'], true) ? input('status') : 'draft',
            'opens_at' => input_datetime('opens_at'), 'closes_at' => input_datetime('closes_at'), 'capacity' => input_int('capacity') ?: null,
            'one_per_person' => input_bool('one_per_person'), 'profile_auto' => input_bool('profile_auto'), 'price_cents' => max(0, min(1000000, $price)), 'tickets' => $this->ticketsFromInput(), 'instalments' => in_array(input_int('instalments'), [2, 3, 4, 6, 10, 12], true) ? input_int('instalments') : 1, 'confirmation' => mb_substr((string)input('confirmation', ''), 0, 2000) ?: null,
            'notify_emails' => $emails ?: null, 'campus_id' => Campus::fromInput(), 'event_id' => input_int('event_id') ?: null, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($d['tickets']) {
            // With ticket types, the price is the cheapest paid ticket (used for "from" wording and to know it's paid).
            $paid = array_filter(array_column(json_decode($d['tickets'], true), 'price_cents'));
            $d['price_cents'] = $paid ? min($paid) : 0;
        }
        if ($d['price_cents'] && !Stripe::enabled()) {
            flash('warning', __('Online payment needs Stripe, which isn\'t set up yet. People will be asked to pay but can\'t until it is.'));
        }
        if ($d['status'] === 'open' && !$d['tickets'] && !array_filter(Forms::fields($f), fn($x) => !Forms::displayOnly($x['type']))) {
            flash('error', __('Add at least one question before opening the form.'));
            $d['status'] = 'draft';
        }
        DB::update('forms', $d, 'id = ?', [$f['id']]);
        audit('form.updated', 'form', (int)$f['id']);
        flash('success', __('Form settings saved.'));
        redirect('/forms/' . $f['id'] . '/edit');
    }

    private function fieldFromInput(): array
    {
        $type = (string)input('type', 'text');
        if (!isset(Forms::types()[$type])) {
            $type = 'text';
        }
        $opts = array_values(array_unique(array_filter(array_map(fn($o) => mb_substr(trim($o), 0, 120), preg_split('/\r?\n/', (string)input('options', ''))))));
        return ['type' => $type, 'label' => trim(mb_substr((string)input('label', ''), 0, $type === 'paragraph' ? 2000 : 300)), 'required' => input_bool('required'),
            'options' => in_array($type, ['select', 'radio', 'checkboxes'], true) ? array_slice($opts, 0, 50) : [], 'help' => mb_substr(trim((string)input('help', '')), 0, 300),
            'profile' => $this->profileTarget($type)];
    }

    /** Ticket types from the settings form (blank rows ignored), as JSON, or null for a single price. */
    private function ticketsFromInput(): ?string
    {
        $out = [];
        foreach (array_slice((array)($_POST['tickets'] ?? []), 0, 8) as $t) {
            $name = trim(mb_substr((string)($t['name'] ?? ''), 0, 60));
            if ($name === '') {
                continue;
            }
            $out[] = ['name' => $name, 'price_cents' => max(0, min(1000000, (int)round((float)str_replace([',', '$'], '', (string)($t['price'] ?? '0')) * 100))),
                'places' => max(1, min(20, (int)($t['places'] ?? 1)))];
        }
        return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /** The profile field chosen for this question, if it suits the question type. */
    private function profileTarget(string $type): ?string
    {
        $t = (string)input('profile', '');
        $targets = Forms::profileTargets();
        if ($t === '' || !isset($targets[$t])) {
            return null;
        }
        if (!in_array($type, $targets[$t][1], true)) {
            flash('warning', __('“%s” can only be saved from these kinds of question: %s.', $targets[$t][0], implode(', ', array_map(fn($x) => Forms::types()[$x], $targets[$t][1]))));
            return null;
        }
        return $t;
    }

    private function saveFields(array $f, array $fields, string $msg): never
    {
        DB::update('forms', ['fields' => json_encode(array_values($fields), JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$f['id']]);
        flash('success', $msg);
        redirect('/forms/' . $f['id'] . '/edit#questions');
    }

    public function addField(string $id): void
    {
        $f = $this->form($id);
        $field = $this->fieldFromInput();
        if ($field['label'] === '' || (in_array($field['type'], ['select', 'radio', 'checkboxes'], true) && count($field['options']) < 2)) {
            flash('error', __('Give the question a label (and at least two options for choice questions).'));
            redirect('/forms/' . $f['id'] . '/edit#questions');
        }
        $fields = Forms::fields($f);
        if (count($fields) >= 100) {
            flash('error', __('A form can have up to 100 questions.'));
            redirect('/forms/' . $f['id'] . '/edit#questions');
        }
        $fields[] = ['key' => substr(bin2hex(random_bytes(4)), 0, 8)] + $field;
        $this->saveFields($f, $fields, __('Question added.'));
    }

    public function updateField(string $id, string $key): void
    {
        $f = $this->form($id);
        $fields = Forms::fields($f);
        foreach ($fields as $i => $x) {
            if ($x['key'] === $key) {
                $new = $this->fieldFromInput();
                if ($new['label'] === '') {
                    flash('error', __('Give the question a label.'));
                    redirect('/forms/' . $f['id'] . '/edit#questions');
                }
                $fields[$i] = ['key' => $key] + $new;
                $this->saveFields($f, $fields, __('Question saved.'));
            }
        }
        abort(404);
    }

    public function moveField(string $id, string $key): void
    {
        $f = $this->form($id);
        $fields = Forms::fields($f);
        $i = array_search($key, array_column($fields, 'key'), true);
        $j = $i === false ? false : (input('dir') === 'up' ? $i - 1 : $i + 1);
        if ($i !== false && isset($fields[$j])) {
            [$fields[$i], $fields[$j]] = [$fields[$j], $fields[$i]];
        }
        $this->saveFields($f, $fields, __('Order saved.'));
    }

    public function deleteField(string $id, string $key): void
    {
        $f = $this->form($id);
        $this->saveFields($f, array_filter(Forms::fields($f), fn($x) => $x['key'] !== $key), __('Question removed. Earlier answers to it are kept with their responses.'));
    }

    public function duplicate(string $id): void
    {
        $f = $this->form($id);
        $copy = array_intersect_key($f, array_flip(['intro', 'fields', 'audience', 'capacity', 'one_per_person', 'profile_auto', 'price_cents', 'tickets', 'instalments', 'confirmation', 'notify_emails', 'campus_id']));
        $title = __('Copy of %s', $f['title']);
        $nid = DB::insert('forms', $copy + ['title' => mb_substr($title, 0, 160), 'slug' => Forms::uniqueSlug(Forms::slugify($title)), 'status' => 'draft', 'created_by' => Auth::id()]);
        audit('form.duplicated', 'form', $nid, ['from' => (int)$f['id']]);
        flash('success', __('Copied. The copy is a draft with no responses.'));
        redirect('/forms/' . $nid . '/edit');
    }

    public function delete(string $id): void
    {
        $f = $this->form($id);
        DB::run('DELETE FROM {p}form_submissions WHERE form_id = ?', [$f['id']]);
        DB::run('DELETE FROM {p}forms WHERE id = ?', [$f['id']]);
        audit('form.deleted', 'form', (int)$f['id'], ['title' => $f['title']]);
        flash('success', __('Form and its responses deleted.'));
        redirect('/forms');
    }

    // ------------------------------------------------------------------ staff: responses

    public function responses(string $id): void
    {
        $f = $this->form($id);
        $fields = array_values(array_filter(Forms::fields($f), fn($x) => !Forms::displayOnly($x['type'])));
        $subs = DB::all('SELECT * FROM {p}form_submissions WHERE form_id = ? ORDER BY created_at DESC', [$f['id']]);
        if (input('export')) {
            audit('form.exported', 'form', (int)$f['id'], ['rows' => count($subs)]);
            $rows = [];
            foreach ($subs as $s) {
                $a = Forms::answers($s);
                $rows[] = array_merge([$s['created_at'], $s['name'], $s['email'], $s['status'], $s['amount_cents'] ? number_format($s['amount_cents'] / 100, 2, '.', '') : '', $s['amount_cents'] ? number_format($s['paid_cents'] / 100, 2, '.', '') : '', Forms::ticketSummary($s), (int)$s['places']], array_map(fn($x) => Forms::show($a[$x['key']] ?? ''), $fields), [$s['staff_notes']]);
            }
            csv_download(Forms::slugify($f['title']) . '-' . date('Y-m-d') . '.csv', array_merge(['submitted', 'name', 'email', 'status', 'amount', 'paid', 'tickets', 'places'], array_column($fields, 'label'), ['staff_notes']), $rows);
        }
        audit('form.responses_viewed', 'form', (int)$f['id']);
        $paid = array_sum(array_map(fn($s) => $s['status'] !== 'cancelled' ? (int)$s['paid_cents'] : 0, $subs));
        // A quick tally for each choice question.
        $tallies = [];
        foreach ($fields as $x) {
            if (in_array($x['type'], ['select', 'radio', 'checkboxes', 'yesno'], true)) {
                $t = [];
                foreach ($subs as $s) {
                    if ($s['status'] === 'cancelled') {
                        continue;
                    }
                    foreach ((array)(Forms::answers($s)[$x['key']] ?? []) as $v) {
                        if ($v !== '') {
                            $t[Forms::show($v)] = ($t[Forms::show($v)] ?? 0) + 1;
                        }
                    }
                }
                arsort($t);
                $tallies[] = ['label' => $x['label'], 'counts' => $t];
            }
        }
        view('forms/responses', ['title' => __('Responses: %s', $f['title']), 'f' => $f, 'subs' => $subs, 'paid' => $paid, 'tallies' => $tallies]);
    }

    public function response(string $id, string $sid): void
    {
        $f = $this->form($id);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        audit('form.response_viewed', 'form_submission', (int)$s['id']);
        $target = input_int('person') ?: (int)$s['person_id'];
        $mapped = Forms::mappedFields($f) && can('people.edit');
        view('forms/response', ['title' => $s['name'], 'f' => $f, 's' => $s, 'fields' => Forms::fields($f), 'answers' => Forms::answers($s),
            'person' => $s['person_id'] ? DB::row('SELECT * FROM {p}people WHERE id = ?', [$s['person_id']]) : null,
            'payments' => Forms::payments((int)$s['id']), 'mapped' => $mapped, 'target' => $target,
            'changes' => $mapped && $target ? Forms::profileChanges($f, $s, $target) : [], 'people' => $mapped ? people_options_scoped() : []]);
    }

    public function updateResponse(string $id, string $sid): void
    {
        $f = $this->form($id);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        $status = in_array(input('status'), ['submitted', 'awaiting_payment', 'part_paid', 'overdue', 'paid', 'cancelled'], true) ? input('status') : $s['status'];
        DB::update('form_submissions', ['status' => $status, 'staff_notes' => mb_substr((string)input('staff_notes', ''), 0, 500) ?: null], 'id = ?', [$s['id']]);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ?', [$s['id']]);
        if ($status === 'cancelled') {
            Forms::cancel($s); // stops instalments and frees the event place
        } elseif (in_array($status, ['submitted', 'part_paid', 'paid'], true)) {
            Forms::confirmed($f, $s);
        }
        audit('form.response_updated', 'form_submission', (int)$s['id'], ['status' => $status]);
        flash('success', __('Response updated.'));
        redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
    }

    /** Staff record money received another way (cash, bank transfer), or a correction. */
    public function recordPayment(string $id, string $sid): void
    {
        $f = $this->form($id);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        $cents = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        $method = in_array(input('method'), ['cash', 'bank', 'card'], true) ? input('method') : 'cash';
        if ($cents <= 0 || $cents > 1000000) {
            flash('error', __('Enter an amount.'));
        } else {
            if (!(int)$s['amount_cents']) {
                DB::update('form_submissions', ['amount_cents' => $cents], 'id = ?', [$s['id']]);
                $s['amount_cents'] = $cents;
            }
            Forms::recordPayment($s, null, $cents, $method === 'card' ? 'manual' : $method);
            flash('success', __('Payment of %s recorded.', fmt_money($cents)));
        }
        redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
    }

    /** Save the mapped answers to a profile (the respondent's, or someone staff choose, such as the camper). */
    public function applyProfile(string $id, string $sid): void
    {
        $f = $this->form($id);
        if (!can('people.edit')) {
            abort(403);
        }
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        $pid = input_int('person_id');
        if (!$pid || !isset(people_options_scoped()[$pid])) {
            flash('error', __('Choose a person.'));
            redirect('/forms/' . $f['id'] . '/responses/' . $s['id'] . '#profile');
        }
        $keys = array_map('strval', (array)($_POST['keys'] ?? []));
        $changed = Forms::applyToProfile($f, $s, $pid, $keys);
        if (!$s['person_id'] && input_bool('link')) {
            DB::update('form_submissions', ['person_id' => $pid], 'id = ?', [$s['id']]);
        }
        flash($changed ? 'success' : 'info', $changed ? __('Profile updated: %s.', implode(', ', $changed)) : __('Nothing to change — the profile already matches.'));
        redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
    }

    /** Refund to the card through Stripe, or record money given back another way. */
    public function refund(string $id, string $sid): void
    {
        $f = $this->form($id);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        $cents = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        $how = in_array(input('how'), ['card', 'cash', 'bank'], true) ? input('how') : 'card';
        if ($cents <= 0 || $cents > (int)$s['paid_cents']) {
            flash('error', __('Enter an amount up to %s, the amount paid.', fmt_money((int)$s['paid_cents'])));
            redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
        }
        try {
            $done = Forms::refund($s, $cents, $how, (bool)input_bool('cancel'));
            flash('success', $how === 'card' ? __('%s refunded to the card through Stripe.', fmt_money($done)) : __('Refund of %s recorded.', fmt_money($done)));
        } catch (Throwable $e) {
            error_log('Affreso refund: ' . $e->getMessage());
            flash('error', __('The refund didn\'t go through: %s', $e->getMessage()));
        }
        redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
    }

    public function stopInstalments(string $id, string $sid): void
    {
        $f = $this->form($id);
        $s = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]) ?? abort(404);
        if ($s['stripe_subscription_id']) {
            Forms::stopInstalments($s);
            DB::update('form_submissions', ['stripe_subscription_id' => null, 'staff_notes' => mb_substr(trim(__('Monthly payments stopped by staff on %s.', fmt_date(date('Y-m-d'))) . ' ' . (string)$s['staff_notes']), 0, 500)], 'id = ?', [$s['id']]);
            audit('form.instalments_stopped', 'form_submission', (int)$s['id']);
            flash('success', __('Monthly payments stopped. No more payments will be taken.'));
        }
        redirect('/forms/' . $f['id'] . '/responses/' . $s['id']);
    }

    public function deleteResponse(string $id, string $sid): void
    {
        $f = $this->form($id);
        DB::run('DELETE FROM {p}form_submissions WHERE id = ? AND form_id = ?', [(int)$sid, $f['id']]);
        audit('form.response_deleted', 'form_submission', (int)$sid);
        flash('success', __('Response deleted.'));
        redirect('/forms/' . $f['id'] . '/responses');
    }

    public function deleteAllResponses(string $id): void
    {
        $f = $this->form($id);
        $n = DB::run('DELETE FROM {p}form_submissions WHERE form_id = ?', [$f['id']])->rowCount();
        audit('form.responses_deleted', 'form', (int)$f['id'], ['count' => $n]);
        flash('success', __('%d responses deleted.', $n));
        redirect('/forms/' . $f['id'] . '/responses');
    }

    // ------------------------------------------------------------------ filling in

    private function bySlug(string $slug): array
    {
        return DB::row('SELECT * FROM {p}forms WHERE slug = ?', [$slug]) ?? abort(404);
    }

    /** Members-only forms need a sign-in; drafts are visible only to form managers (preview). */
    private function gate(array $f): void
    {
        if ($f['status'] === 'draft' && !can('forms.manage')) {
            abort(404);
        }
        if ($f['audience'] === 'members' && !(Auth::id() !== null)) {
            $_SESSION['_intended'] = '/f/' . $f['slug'];
            flash('info', __('Please sign in to fill in this form.'));
            redirect('/login');
        }
    }

    public function show(string $slug): void
    {
        $f = $this->bySlug($slug);
        $this->gate($f);
        $me = person_id() ? DB::row('SELECT * FROM {p}people WHERE id = ?', [person_id()]) : null;
        $already = $me && $f['one_per_person'] ? DB::row("SELECT * FROM {p}form_submissions WHERE form_id = ? AND person_id = ? AND status <> 'cancelled'", [$f['id'], $me['id']]) : null;
        $old = $_SESSION['form_old'][$f['id']] ?? [];
        unset($_SESSION['form_old'][$f['id']]);
        view('forms/fill', ['title' => $f['title'], 'f' => $f, 'fields' => Forms::fields($f), 'closed' => Forms::closedReason($f), 'me' => $me, 'already' => $already,
            'old' => $old, 'left' => $f['capacity'] ? max(0, (int)$f['capacity'] - Forms::taken((int)$f['id'])) : null], (Auth::id() !== null) ? 'layout' : 'layout_public');
    }

    public function submit(string $slug): void
    {
        $f = $this->bySlug($slug);
        $this->gate($f);
        if (!empty($_POST['website'])) {
            redirect('/f/' . $f['slug'] . '/done');
        }
        if (!(Auth::id() !== null)) {
            if ((int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email = 'public:form' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [client_ip()]) >= 20) {
                abort(429);
            }
            DB::insert('login_attempts', ['email' => 'public:form', 'ip' => client_ip(), 'success' => 1]);
        }
        if (($why = Forms::closedReason($f)) !== null && !($f['status'] === 'draft' && can('forms.manage'))) {
            flash('error', $why);
            redirect('/f/' . $f['slug']);
        }
        $pid = person_id();
        if ($pid && $f['one_per_person'] && DB::val("SELECT 1 FROM {p}form_submissions WHERE form_id = ? AND person_id = ? AND status <> 'cancelled'", [$f['id'], $pid])) {
            flash('error', __('You have already filled in this form.'));
            redirect('/f/' . $f['slug']);
        }
        $fields = Forms::fields($f);
        [$answers, $errors] = Forms::validate($fields, $_POST);
        $name = trim(mb_substr((string)input('respondent_name', ''), 0, 160));
        $email = trim(mb_strtolower((string)input('respondent_email', '')));
        if ($name === '') {
            $errors[] = __('Your name is required.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('Enter a valid email address.');
        }
        $tickets = Forms::tickets($f);
        if ($tickets) {
            [$lines, $total, $places, $tErr] = Forms::ticketChoice($f, $_POST);
            $errors = array_merge($errors, $tErr);
            if ($f['capacity'] && $places > 0 && Forms::taken((int)$f['id']) + $places > (int)$f['capacity']) {
                $errors[] = __('Only %d places are left.', max(0, (int)$f['capacity'] - Forms::taken((int)$f['id'])));
            }
        } else {
            [$lines, $total, $places] = [null, (int)$f['price_cents'], 1];
        }
        if ($total > 0 && $email === '') {
            $errors[] = __('An email address is needed for your payment receipt.');
        }
        if (!input_bool('privacy_ok')) {
            $errors[] = __('Please confirm you have read the privacy notice.');
        }
        if ($errors) {
            $_SESSION['form_old'][$f['id']] = $_POST;
            foreach (array_slice($errors, 0, 6) as $err) {
                flash('error', $err);
            }
            redirect('/f/' . $f['slug'] . '#form');
        }
        $plans = Forms::plans($f, $total);
        $n = isset($plans[input_int('plan')]) ? input_int('plan') : 1;
        $each = $plans[$n];
        $amount = $n > 1 ? $each * $n : $total;
        $paying = $amount > 0 && Stripe::enabled() && !Demo::on();
        $sid = DB::insert('form_submissions', ['form_id' => $f['id'], 'person_id' => $pid, 'name' => $name, 'email' => $email ?: null, 'answers' => Forms::encryptAnswers($answers),
            'tickets' => $lines ? json_encode($lines, JSON_UNESCAPED_UNICODE) : null, 'places' => max(1, $places),
            'status' => $paying ? 'awaiting_payment' : 'submitted', 'amount_cents' => $amount, 'instalments' => $n, 'ip' => client_ip()]);
        audit('form.submitted', 'form_submission', $sid, ['form' => (int)$f['id']]);
        $sub = DB::row('SELECT * FROM {p}form_submissions WHERE id = ?', [$sid]);
        if ($pid && $f['profile_auto'] && Forms::mappedFields($f)) {
            Forms::applyToProfile($f, $sub, $pid); // signed-in people updating their own details
        }
        if ($paying) {
            try {
                $done = url('/f/' . $f['slug'] . '/done', ['s' => $sid], true);
                $cs = $n > 1
                    ? Stripe::createInstalmentCheckout(['form_submission_id' => $sid], $each, $n, (string)setting('currency', 'AUD'), $f['title'], $email, $done, url('/f/' . $f['slug'], [], true))
                    : Stripe::createPaymentCheckout(['form_submission_id' => $sid], $total, (string)setting('currency', 'AUD'), $f['title'], $email, $done, url('/f/' . $f['slug'], [], true));
                DB::update('form_submissions', ['stripe_session_id' => $cs['id']], 'id = ?', [$sid]);
                redirect((string)$cs['url']);
            } catch (Throwable $e) {
                DB::update('form_submissions', ['status' => 'submitted'], 'id = ?', [$sid]);
                flash('warning', __('Your response was saved, but online payment isn\'t available right now. The church will contact you about paying.'));
            }
        }
        if (DB::val('SELECT status FROM {p}form_submissions WHERE id = ?', [$sid]) === 'submitted') {
            Forms::confirmed($f, $sub); // free, or online payment unavailable: the place is confirmed now
        }
        Forms::notify($f, $sub);
        $_SESSION['form_done'] = $sid;
        redirect('/f/' . $f['slug'] . '/done');
    }

    public function done(string $slug): void
    {
        $f = $this->bySlug($slug);
        $sid = input_int('s');
        if ($sid && ($sub = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [$sid, $f['id']])) && $sub['status'] === 'awaiting_payment' && $sub['stripe_session_id']) {
            try {
                $cs = Stripe::api('GET', 'checkout/sessions/' . rawurlencode($sub['stripe_session_id']));
                if (($cs['payment_status'] ?? '') === 'paid') {
                    if ((int)$sub['instalments'] > 1) {
                        // The first instalment's invoice is recorded by the webhook; just link the subscription now.
                        if (!empty($cs['subscription']) && !$sub['stripe_subscription_id']) {
                            DB::update('form_submissions', ['stripe_subscription_id' => (string)$cs['subscription']], 'id = ?', [$sub['id']]);
                        }
                    } else {
                        Forms::markPaid($sub, '', is_string($cs['payment_intent'] ?? null) ? $cs['payment_intent'] : null);
                    }
                }
            } catch (Throwable $e) {
                // The webhook will confirm the payment.
            }
        }
        // Only show payment details to the person who just paid (Stripe sends them back with the session id).
        $sub = $sid ? DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND form_id = ?', [$sid, $f['id']]) : null;
        if ($sub && !(($sub['stripe_session_id'] && hash_equals((string)$sub['stripe_session_id'], (string)input('session_id', ''))) || ($_SESSION['form_done'] ?? 0) === (int)$sub['id'])) {
            $sub = null;
        }
        view('forms/done', ['title' => $f['title'], 'f' => $f, 'sub' => $sub], (Auth::id() !== null) ? 'layout' : 'layout_public');
    }

    /** Members: open forms they can fill in. */
    public function mine(): void
    {
        $forms = array_values(array_filter(DB::all("SELECT * FROM {p}forms WHERE status = 'open' ORDER BY closes_at IS NULL, closes_at, title"), fn($f) => Forms::closedReason($f) === null));
        $mine = person_id() ? DB::all('SELECT s.*, f.title, f.slug FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id WHERE s.person_id = ? ORDER BY s.created_at DESC LIMIT 20', [person_id()]) : [];
        view('forms/mine', ['title' => __('Forms'), 'forms' => $forms, 'mine' => $mine]);
    }
}
