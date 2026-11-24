<?php
declare(strict_types=1);

final class PeopleController
{
    public const STATUSES = ['visitor' => 'Visitor', 'regular' => 'Regular attender', 'member' => 'Member', 'inactive' => 'Inactive'];

    private function find(int|string $id): array
    {
        $p = DB::row('SELECT * FROM {p}people WHERE id = ?', [(int)$id]);
        if (!$p) {
            abort(404);
        }
        return $p;
    }

    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        $q = (string)input('q', '');
        $status = (string)input('status', '');
        $group = input_int('group');
        $archived = input('archived') === '1';
        $where[] = $archived ? 'p.archived_at IS NOT NULL' : 'p.archived_at IS NULL';
        if ($q !== '') {
            $where[] = '(CONCAT(p.first_name, " ", p.last_name) LIKE ? OR p.preferred_name LIKE ? OR p.email LIKE ? OR p.mobile LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (isset(self::STATUSES[$status])) {
            $where[] = 'p.membership_status = ?';
            $params[] = $status;
        }
        if ($group) {
            $where[] = 'p.id IN (SELECT person_id FROM {p}group_members WHERE group_id = ?)';
            $params[] = $group;
        }
        [$cw, $cp] = Campus::where('p');
        $w = implode(' AND ', $where) . $cw;
        $params = array_merge($params, $cp);
        $pg = paginate("SELECT COUNT(*) FROM {p}people p WHERE $w", $params, 50);
        $rows = DB::all("SELECT p.*, h.name AS household FROM {p}people p LEFT JOIN {p}households h ON h.id = p.household_id WHERE $w ORDER BY p.last_name, p.first_name LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
        $groups = DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name');
        view('people/index', ['title' => __('Members & families'), 'rows' => $rows, 'pg' => $pg, 'q' => $q, 'status' => $status, 'group' => $group, 'groups' => $groups, 'archived' => $archived]);
    }

    public function create(): void
    {
        $p = ['household_id' => input_int('household'), 'membership_status' => 'visitor', 'notify_email' => 1, 'household_role' => 'adult', 'first_visit' => date('Y-m-d')];
        view('people/form', ['title' => __('Add person'), 'p' => $p, 'households' => $this->householdOptions(), 'fields' => $this->customFields(null)]);
    }

    public function edit(string $id): void
    {
        $p = $this->find($id);
        view('people/form', ['title' => __('Edit %s', person_name($p)), 'p' => $p, 'households' => $this->householdOptions(), 'fields' => $this->customFields((int)$p['id'])]);
    }

    private function householdOptions(): array
    {
        $o = [];
        foreach (DB::all('SELECT id, name, suburb FROM {p}households ORDER BY name') as $h) {
            $o[$h['id']] = $h['name'] . ($h['suburb'] ? ' (' . $h['suburb'] . ')' : '');
        }
        return $o;
    }

    private function customFields(?int $pid): array
    {
        $fields = DB::all('SELECT * FROM {p}custom_fields ORDER BY sort, id');
        if ($pid) {
            $vals = [];
            foreach (DB::all('SELECT field_id, value FROM {p}custom_field_values WHERE person_id = ?', [$pid]) as $v) {
                $vals[$v['field_id']] = $v['value'];
            }
            foreach ($fields as &$f) {
                $f['value'] = $vals[$f['id']] ?? '';
            }
        }
        return $fields;
    }

    /** Validated person fields from the submitted form. */
    public static function formData(bool $staff = true): array
    {
        $d = [
            'first_name' => mb_substr((string)input('first_name', ''), 0, 80),
            'last_name' => mb_substr((string)input('last_name', ''), 0, 80),
            'preferred_name' => mb_substr((string)input('preferred_name', ''), 0, 80) ?: null,
            'email' => mb_strtolower((string)input('email', '')) ?: null,
            'mobile' => mb_substr((string)input('mobile', ''), 0, 40) ?: null,
            'birthdate' => input_date('birthdate'),
            'gender' => mb_substr((string)input('gender', ''), 0, 20) ?: null,
            'address_line' => mb_substr((string)input('address_line', ''), 0, 200) ?: null,
            'suburb' => mb_substr((string)input('suburb', ''), 0, 100) ?: null,
            'state' => mb_substr((string)input('state', ''), 0, 50) ?: null,
            'postcode' => mb_substr((string)input('postcode', ''), 0, 12) ?: null,
            'directory_visible' => input_bool('directory_visible'),
            'notify_email' => input_bool('notify_email'),
            'notify_sms' => input_bool('notify_sms'),
        ];
        if ($staff) {
            $st = (string)input('membership_status', 'visitor');
            $d['membership_status'] = isset(self::STATUSES[$st]) ? $st : 'visitor';
            $d['first_visit'] = input_date('first_visit');
            $d['joined_on'] = input_date('joined_on');
            $hr = (string)input('household_role', 'adult');
            $d['household_role'] = in_array($hr, ['adult', 'child', 'other'], true) ? $hr : 'adult';
            $d['general_notes'] = (string)input('general_notes', '') ?: null;
            $d['campus_id'] = Campus::fromInput();
        }
        return $d;
    }

    private function validate(array $d): ?string
    {
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            return __('First and last name are required.');
        }
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return __('The email address is not valid.');
        }
        return null;
    }

    private function resolveHousehold(array $d): ?int
    {
        $hid = input_int('household_id');
        $new = trim((string)input('new_household', ''));
        if ($new !== '') {
            return DB::insert('households', ['name' => mb_substr($new, 0, 150), 'address_line' => $d['address_line'], 'suburb' => $d['suburb'], 'state' => $d['state'], 'postcode' => $d['postcode']]);
        }
        return $hid && DB::val('SELECT 1 FROM {p}households WHERE id = ?', [$hid]) ? $hid : null;
    }

    private function saveCustomFields(int $pid): void
    {
        foreach (DB::all('SELECT * FROM {p}custom_fields') as $f) {
            $v = $f['field_type'] === 'checkbox' ? (string)input_bool('cf_' . $f['id']) : mb_substr((string)input('cf_' . $f['id'], ''), 0, 1000);
            DB::run('INSERT INTO {p}custom_field_values (person_id, field_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$pid, $f['id'], $v]);
        }
    }

    public function store(): void
    {
        $d = self::formData();
        if ($err = $this->validate($d)) {
            flash('error', $err);
            back('/people/new');
        }
        $d['household_id'] = $this->resolveHousehold($d);
        $d['consent_contact'] = input_bool('consent_contact');
        $d['consent_photo'] = input_bool('consent_photo');
        $d['consent_updated_at'] = date('Y-m-d H:i:s');
        $id = DB::insert('people', $d);
        foreach (['contact' => $d['consent_contact'], 'photo' => $d['consent_photo']] as $type => $granted) {
            DB::insert('consent_records', ['person_id' => $id, 'consent_type' => $type, 'granted' => $granted, 'recorded_by' => Auth::id(), 'method' => 'staff entry']);
        }
        $this->saveCustomFields($id);
        audit('person.created', 'person', $id);
        if ($d['membership_status'] === 'visitor' && input_bool('create_followup')) {
            DB::insert('care_tasks', ['person_id' => $id, 'task_type' => 'newcomer', 'title' => __('Welcome follow-up: %s', person_name($d)), 'assigned_to' => ((int)setting('connect_followup_user', 0)) ?: Auth::id(), 'due_date' => date('Y-m-d', strtotime('+3 days')), 'created_by' => Auth::id()]);
        }
        flash('success', __('%s has been added.', person_name($d)));
        redirect('/people/' . $id);
    }

    public function update(string $id): void
    {
        $p = $this->find($id);
        $d = self::formData();
        if ($err = $this->validate($d)) {
            flash('error', $err);
            back('/people/' . $id . '/edit');
        }
        $d['household_id'] = $this->resolveHousehold($d);
        $d['updated_at'] = date('Y-m-d H:i:s');
        DB::update('people', $d, 'id = ?', [$p['id']]);
        $this->saveCustomFields((int)$p['id']);
        $changed = array_keys(array_filter($d, fn($v, $k) => array_key_exists($k, $p) && (string)$p[$k] !== (string)$v && $k !== 'updated_at', ARRAY_FILTER_USE_BOTH));
        audit('person.updated', 'person', (int)$p['id'], ['fields' => $changed]);
        if ($p['email'] !== $d['email'] && $d['email'] && ($uid = user_id_for_person((int)$p['id']))) {
            if (!DB::val('SELECT 1 FROM {p}users WHERE email = ? AND id <> ?', [$d['email'], $uid])) {
                DB::update('users', ['email' => $d['email']], 'id = ?', [$uid]);
            }
        }
        flash('success', __('Changes saved.'));
        redirect('/people/' . $p['id']);
    }

    public function show(string $id): void
    {
        $p = $this->find($id);
        $pid = (int)$p['id'];
        $d = ['title' => person_name($p), 'p' => $p];
        $d['household'] = $p['household_id'] ? DB::row('SELECT * FROM {p}households WHERE id = ?', [$p['household_id']]) : null;
        $d['family'] = $p['household_id'] ? DB::all('SELECT * FROM {p}people WHERE household_id = ? AND id <> ? AND archived_at IS NULL ORDER BY household_role, birthdate', [$p['household_id'], $pid]) : [];
        $d['groups'] = DB::all('SELECT g.id, g.name, g.group_type, gm.member_role FROM {p}group_members gm JOIN {p}groups g ON g.id = gm.group_id WHERE gm.person_id = ? ORDER BY g.name', [$pid]);
        $d['positions'] = DB::all('SELECT vp.*, po.name, g.name AS group_name FROM {p}volunteer_positions vp JOIN {p}positions po ON po.id = vp.position_id JOIN {p}groups g ON g.id = po.group_id WHERE vp.person_id = ?', [$pid]);
        $d['upcoming'] = DB::all("SELECT ra.status, r.id AS roster_id, r.title, r.service_date, po.name AS position FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions po ON po.id = ra.position_id WHERE ra.person_id = ? AND r.service_date >= CURDATE() ORDER BY r.service_date LIMIT 10", [$pid]);
        $d['training'] = DB::all('SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.person_id = ? ORDER BY e.created_at DESC', [$pid]);
        $d['fields'] = $this->customFields($pid);
        $d['care'] = can('care.manage') ? DB::all('SELECT * FROM {p}care_tasks WHERE person_id = ? AND (is_confidential = 0 OR ?) ORDER BY status, due_date', [$pid, can('pastoral.view') ? 1 : 0]) : [];
        $d['checks'] = can('safeguarding.view') ? DB::all('SELECT * FROM {p}safeguarding_checks WHERE person_id = ? ORDER BY expires_on DESC', [$pid]) : [];
        $d['notes'] = [];
        if (can('pastoral.view')) {
            $d['notes'] = DB::all('SELECT n.*, pe.first_name, pe.last_name FROM {p}pastoral_notes n LEFT JOIN {p}users u ON u.id = n.author_id LEFT JOIN {p}people pe ON pe.id = u.person_id WHERE n.person_id = ? ORDER BY n.created_at DESC', [$pid]);
            foreach ($d['notes'] as &$n) {
                $n['body'] = Crypto::decrypt($n['body_enc']) ?? __('[Unable to decrypt — check the app key]');
            }
            unset($n);
            if ($d['notes'] && setting('pastoral_access_logging', '1')) {
                audit('pastoral.viewed', 'person', $pid);
            }
        }
        $d['giving'] = can('giving.view') ? DB::all('SELECT d.*, f.name AS fund FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.person_id = ? ORDER BY d.created_at DESC LIMIT 10', [$pid]) : [];
        $d['account'] = DB::row('SELECT id, email, status, last_login_at, mfa_enabled FROM {p}users WHERE person_id = ?', [$pid]);
        $d['consents'] = DB::all('SELECT * FROM {p}consent_records WHERE person_id = ? ORDER BY id DESC LIMIT 10', [$pid]);
        audit('person.viewed', 'person', $pid);
        view('people/show', $d);
    }

    public function archive(string $id): void
    {
        $p = $this->find($id);
        DB::update('people', ['archived_at' => date('Y-m-d H:i:s')], 'id = ?', [$p['id']]);
        DB::run("UPDATE {p}users SET status = 'disabled' WHERE person_id = ?", [$p['id']]);
        audit('person.archived', 'person', (int)$p['id']);
        flash('success', __('%s has been archived. Their record is kept but hidden, and any login is disabled.', person_name($p)));
        redirect('/people/' . $p['id']);
    }

    public function restore(string $id): void
    {
        $p = $this->find($id);
        DB::update('people', ['archived_at' => null], 'id = ?', [$p['id']]);
        audit('person.restored', 'person', (int)$p['id']);
        flash('success', __('Record restored.'));
        redirect('/people/' . $p['id']);
    }

    /** Permanently de-identify a record (APP 11.2). Safeguarding records block erasure unless explicitly confirmed. */
    public function erase(string $id): void
    {
        $p = $this->find($id);
        $pid = (int)$p['id'];
        $hasSafeguarding = (int)DB::val('SELECT COUNT(*) FROM {p}safeguarding_checks WHERE person_id = ?', [$pid]);
        if ($hasSafeguarding && !input_bool('confirm_retention')) {
            flash('error', __('This person has safeguarding records. Child-safety records usually must be kept for a long period. Confirm you have checked your retention obligations before erasing.'));
            redirect('/people/' . $pid);
        }
        if ((string)input('confirm_name') !== $p['last_name']) {
            flash('error', __('Type the surname exactly to confirm erasure.'));
            redirect('/people/' . $pid);
        }
        DB::tx(function () use ($pid) {
            DB::update('people', ['first_name' => 'Erased', 'last_name' => 'Record #' . $pid, 'preferred_name' => null, 'email' => null, 'mobile' => null, 'birthdate' => null, 'gender' => null, 'address_line' => null, 'suburb' => null, 'postcode' => null, 'general_notes' => null, 'household_id' => null, 'directory_visible' => 0, 'notify_email' => 0, 'notify_sms' => 0, 'archived_at' => date('Y-m-d H:i:s')], 'id = ?', [$pid]);
            foreach (['pastoral_notes', 'custom_field_values', 'unavailability', 'group_members', 'volunteer_positions'] as $t) {
                DB::run("DELETE FROM {p}$t WHERE person_id = ?", [$pid]);
            }
            DB::run('UPDATE {p}care_tasks SET details = NULL WHERE person_id = ?', [$pid]);
            DB::run("UPDATE {p}event_registrations SET name = 'Erased', email = NULL, phone = NULL WHERE person_id = ?", [$pid]);
            DB::run("UPDATE {p}donations SET donor_name = NULL, donor_email = NULL WHERE person_id = ?", [$pid]);
            DB::run('UPDATE {p}message_recipients SET address = ?, body = NULL WHERE person_id = ?', ['erased', $pid]);
            $uid = DB::val('SELECT id FROM {p}users WHERE person_id = ?', [$pid]);
            if ($uid) {
                DB::run('DELETE FROM {p}user_roles WHERE user_id = ?', [$uid]);
                DB::run('DELETE FROM {p}users WHERE id = ?', [$uid]);
            }
        });
        audit('person.erased', 'person', $pid, ['safeguarding_records_kept' => $hasSafeguarding]);
        flash('success', __('The record has been de-identified.'));
        redirect('/people');
    }

    public function invite(string $id): void
    {
        $p = $this->find($id);
        if (!$p['email']) {
            flash('error', __('Add an email address before inviting this person.'));
            redirect('/people/' . $p['id']);
        }
        $existing = DB::row('SELECT * FROM {p}users WHERE person_id = ? OR email = ?', [$p['id'], $p['email']]);
        if ($existing && (int)$existing['person_id'] !== (int)$p['id']) {
            flash('error', __('Another account already uses this email address.'));
            redirect('/people/' . $p['id']);
        }
        $uid = $existing ? (int)$existing['id'] : DB::insert('users', ['person_id' => $p['id'], 'email' => $p['email'], 'password_hash' => password_hash(Crypto::token(24), PASSWORD_DEFAULT), 'status' => 'active', 'ical_token' => Crypto::token(20)]);
        if (!$existing) {
            $role = in_array(input('role'), ['member', 'volunteer'], true) ? input('role') : ($p['membership_status'] === 'visitor' ? 'visitor' : 'member');
            DB::insert('user_roles', ['user_id' => $uid, 'role_id' => (int)DB::val('SELECT id FROM {p}roles WHERE slug = ?', [$role])]);
        }
        $link = Auth::resetLink($uid, 72);
        Notify::emailPerson((int)$p['id'], __('You are invited to %s on Affreso', setting('church_name')), '<p>' . e(__('Hi %s,', person_name($p))) . '</p><p>' . e(__('You have been invited to use our church system to view rosters, update your details, register for events and complete training.')) . '</p><p><a href="' . e($link) . '">' . e(__('Set your password')) . '</a> ' . e(__('(this link works for 3 days)')) . '</p>', true);
        Notify::processQueue(5);
        audit('user.invited', 'user', $uid);
        flash('success', __('Invitation sent to %s.', $p['email']));
        redirect('/people/' . $p['id']);
    }

    public function addNote(string $id): void
    {
        $p = $this->find($id);
        $body = trim((string)input('body', ''));
        if ($body === '') {
            back('/people/' . $p['id']);
        }
        $cat = in_array(input('category'), ['general', 'visit', 'counselling', 'prayer', 'hospital', 'other'], true) ? input('category') : 'general';
        $nid = DB::insert('pastoral_notes', ['person_id' => $p['id'], 'author_id' => Auth::id(), 'category' => $cat, 'body_enc' => Crypto::encrypt($body)]);
        audit('pastoral.note_added', 'person', (int)$p['id'], ['note_id' => $nid]);
        flash('success', __('Confidential note saved (encrypted).'));
        redirect('/people/' . $p['id'] . '#pastoral');
    }

    public function consent(string $id): void
    {
        $p = $this->find($id);
        $c = input_bool('consent_contact');
        $ph = input_bool('consent_photo');
        DB::update('people', ['consent_contact' => $c, 'consent_photo' => $ph, 'consent_updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$p['id']]);
        $method = mb_substr((string)input('method', 'staff entry'), 0, 60);
        if ((int)$p['consent_contact'] !== $c) {
            DB::insert('consent_records', ['person_id' => $p['id'], 'consent_type' => 'contact', 'granted' => $c, 'recorded_by' => Auth::id(), 'method' => $method]);
        }
        if ((int)$p['consent_photo'] !== $ph) {
            DB::insert('consent_records', ['person_id' => $p['id'], 'consent_type' => 'photo', 'granted' => $ph, 'recorded_by' => Auth::id(), 'method' => $method]);
        }
        audit('person.consent_updated', 'person', (int)$p['id']);
        flash('success', __('Consent updated.'));
        redirect('/people/' . $p['id']);
    }

    /** Everything held about a person, as JSON — for access requests (APP 12). */
    public static function personDataBundle(int $pid, bool $includePastoral): array
    {
        $out = ['generated_at' => date('c'), 'organisation' => setting('church_name')];
        $out['profile'] = DB::row('SELECT * FROM {p}people WHERE id = ?', [$pid]);
        $out['household'] = $out['profile']['household_id'] ? DB::row('SELECT * FROM {p}households WHERE id = ?', [$out['profile']['household_id']]) : null;
        $out['custom_fields'] = DB::all('SELECT f.label, v.value FROM {p}custom_field_values v JOIN {p}custom_fields f ON f.id = v.field_id WHERE v.person_id = ?', [$pid]);
        $out['groups'] = DB::all('SELECT g.name, gm.member_role, gm.joined_at FROM {p}group_members gm JOIN {p}groups g ON g.id = gm.group_id WHERE gm.person_id = ?', [$pid]);
        $out['volunteer_positions'] = DB::all('SELECT po.name, vp.approved_at FROM {p}volunteer_positions vp JOIN {p}positions po ON po.id = vp.position_id WHERE vp.person_id = ?', [$pid]);
        $out['roster_assignments'] = DB::all('SELECT r.title, r.service_date, po.name AS position, ra.status FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions po ON po.id = ra.position_id WHERE ra.person_id = ?', [$pid]);
        $out['unavailability'] = DB::all('SELECT start_date, end_date, reason FROM {p}unavailability WHERE person_id = ?', [$pid]);
        $out['training'] = DB::all('SELECT c.title, e.status, e.score, e.completed_at, e.expires_at FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.person_id = ?', [$pid]);
        $out['safeguarding_checks'] = DB::all('SELECT check_type, jurisdiction, status, verified_on, expires_on FROM {p}safeguarding_checks WHERE person_id = ?', [$pid]);
        $out['event_registrations'] = DB::all('SELECT e.title, e.start_at, r.status FROM {p}event_registrations r JOIN {p}events e ON e.id = r.event_id WHERE r.person_id = ?', [$pid]);
        $out['donations'] = DB::all('SELECT amount_cents, currency, method, status, receipt_no, received_on, created_at FROM {p}donations WHERE person_id = ?', [$pid]);
        $out['consent_records'] = DB::all('SELECT consent_type, granted, method, created_at FROM {p}consent_records WHERE person_id = ?', [$pid]);
        $out['messages_received'] = DB::all('SELECT channel, subject, status, sent_at FROM {p}message_recipients WHERE person_id = ?', [$pid]);
        $out['volunteer_applications'] = DB::all('SELECT skills, availability, experience, status, created_at FROM {p}volunteer_applications WHERE person_id = ?', [$pid]);
        if ($includePastoral) {
            $out['pastoral_notes'] = array_map(fn($n) => ['category' => $n['category'], 'created_at' => $n['created_at'], 'note' => Crypto::decrypt($n['body_enc'])], DB::all('SELECT * FROM {p}pastoral_notes WHERE person_id = ?', [$pid]));
        }
        return $out;
    }

    public function exportPerson(string $id): void
    {
        $p = $this->find($id);
        $withPastoral = input('pastoral') === '1' && can('pastoral.view');
        audit('privacy.data_exported', 'person', (int)$p['id'], ['pastoral' => $withPastoral]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="person-' . (int)$p['id'] . '-data.json"');
        echo json_encode(self::personDataBundle((int)$p['id'], $withPastoral), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Households -------------------------------------------------------------
    public function households(): void
    {
        $q = (string)input('q', '');
        $rows = DB::all('SELECT h.*, (SELECT COUNT(*) FROM {p}people p WHERE p.household_id = h.id AND p.archived_at IS NULL) AS members FROM {p}households h WHERE h.name LIKE ? ORDER BY h.name LIMIT 500', ['%' . $q . '%']);
        view('people/households', ['title' => __('Households'), 'rows' => $rows, 'q' => $q]);
    }

    public function storeHousehold(): void
    {
        $name = trim((string)input('name', ''));
        if ($name === '') {
            back('/households');
        }
        $id = DB::insert('households', ['name' => mb_substr($name, 0, 150), 'address_line' => input('address_line') ?: null, 'suburb' => input('suburb') ?: null, 'state' => input('state') ?: null, 'postcode' => input('postcode') ?: null, 'phone' => input('phone') ?: null]);
        audit('household.created', 'household', $id);
        redirect('/households/' . $id);
    }

    public function household(string $id): void
    {
        $h = DB::row('SELECT * FROM {p}households WHERE id = ?', [(int)$id]) ?? abort(404);
        $members = DB::all('SELECT * FROM {p}people WHERE household_id = ? ORDER BY archived_at IS NOT NULL, household_role, birthdate', [$h['id']]);
        view('people/household', ['title' => $h['name'], 'h' => $h, 'members' => $members]);
    }

    public function updateHousehold(string $id): void
    {
        $h = DB::row('SELECT * FROM {p}households WHERE id = ?', [(int)$id]) ?? abort(404);
        DB::update('households', ['name' => mb_substr((string)input('name', $h['name']), 0, 150), 'address_line' => input('address_line') ?: null, 'suburb' => input('suburb') ?: null, 'state' => input('state') ?: null, 'postcode' => input('postcode') ?: null, 'phone' => input('phone') ?: null], 'id = ?', [$h['id']]);
        if (input_bool('apply_address')) {
            DB::run('UPDATE {p}people SET address_line = ?, suburb = ?, state = ?, postcode = ? WHERE household_id = ?', [input('address_line') ?: null, input('suburb') ?: null, input('state') ?: null, input('postcode') ?: null, $h['id']]);
        }
        audit('household.updated', 'household', (int)$h['id']);
        flash('success', __('Household saved.'));
        redirect('/households/' . $h['id']);
    }

    // Import / export -------------------------------------------------------
    public function exportCsv(): void
    {
        audit('people.exported_csv');
        $rows = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.email, p.mobile, p.birthdate, p.gender, p.address_line, p.suburb, p.state, p.postcode, p.membership_status, p.first_visit, p.joined_on, h.name AS household, p.household_role, p.consent_contact, p.consent_photo FROM {p}people p LEFT JOIN {p}households h ON h.id = p.household_id WHERE p.archived_at IS NULL ORDER BY p.last_name, p.first_name');
        csv_download('people-' . date('Y-m-d') . '.csv', ['id', 'first_name', 'last_name', 'preferred_name', 'email', 'mobile', 'birthdate', 'gender', 'address_line', 'suburb', 'state', 'postcode', 'membership_status', 'first_visit', 'joined_on', 'household', 'household_role', 'consent_contact', 'consent_photo'], $rows);
    }

    /** Map column names from Affreso, Elvanto, Planning Center and common spreadsheets to Affreso fields. */
    public static function mapHeader(string $h): string
    {
        $k = preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        $aliases = [
            'firstname' => 'first_name', 'givenname' => 'first_name', 'first' => 'first_name',
            'lastname' => 'last_name', 'surname' => 'last_name', 'familyname' => 'last_name', 'last' => 'last_name',
            'preferredname' => 'preferred_name', 'nickname' => 'preferred_name', 'goesby' => 'preferred_name',
            'email' => 'email', 'emailaddress' => 'email', 'homeemail' => 'email', 'primaryemail' => 'email', 'personalemail' => 'email',
            'mobile' => 'mobile', 'mobilephone' => 'mobile', 'mobilephonenumber' => 'mobile', 'cellphone' => 'mobile', 'mobilenumber' => 'mobile', 'phone' => 'mobile',
            'birthdate' => 'birthdate', 'birthday' => 'birthdate', 'dateofbirth' => 'birthdate', 'dob' => 'birthdate',
            'gender' => 'gender', 'sex' => 'gender',
            'address' => 'address_line', 'addressline' => 'address_line', 'homeaddress' => 'address_line', 'homeaddressstreetline1' => 'address_line', 'streetaddress' => 'address_line', 'address1' => 'address_line',
            'suburb' => 'suburb', 'city' => 'suburb', 'homecity' => 'suburb', 'homeaddresscity' => 'suburb',
            'state' => 'state', 'homestate' => 'state', 'homeaddressstate' => 'state',
            'postcode' => 'postcode', 'zip' => 'postcode', 'zipcode' => 'postcode', 'homepostcode' => 'postcode', 'homeaddresszipcode' => 'postcode', 'postalcode' => 'postcode',
            'membershipstatus' => 'membership_status', 'membership' => 'membership_status', 'status' => 'membership_status', 'memberstatus' => 'membership_status',
            'firstvisit' => 'first_visit', 'firstattended' => 'first_visit', 'datejoined' => 'joined_on', 'joinedon' => 'joined_on', 'membershipdate' => 'joined_on', 'memberdate' => 'joined_on',
            'household' => 'household', 'householdname' => 'household', 'family' => 'household', 'familyname2' => 'household',
            'householdrole' => 'household_role', 'familyrole' => 'household_role', 'familyrelationship' => 'household_role', 'householdprimarycontact' => 'household_role',
        ];
        return $aliases[$k] ?? strtolower(trim($h));
    }

    /** Normalise a membership label from another system to an Affreso status. */
    public static function mapStatus(string $v): string
    {
        $v = strtolower(trim($v));
        return match (true) {
            str_contains($v, 'member') && !str_contains($v, 'non') => 'member',
            str_contains($v, 'regular') || str_contains($v, 'attender') || str_contains($v, 'attendee') => 'regular',
            str_contains($v, 'visitor') || str_contains($v, 'guest') || str_contains($v, 'first') || str_contains($v, 'new') => 'visitor',
            str_contains($v, 'inactive') || str_contains($v, 'former') || str_contains($v, 'left') => 'inactive',
            default => isset(self::STATUSES[$v]) ? $v : 'member',
        };
    }

    public function importForm(): void
    {
        $runs = DB::all('SELECT r.* FROM {p}import_runs r ORDER BY r.id DESC LIMIT 5');
        view('people/import', ['title' => __('Import people'), 'runs' => $runs]);
    }

    public function import(): void
    {
        $f = $_FILES['csv'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
            flash('error', __('Please choose a CSV file under 5 MB.'));
            redirect('/people/import');
        }
        $fh = fopen($f['tmp_name'], 'r');
        $header = fgetcsv($fh) ?: [];
        $header = array_map(fn($h) => self::mapHeader((string)$h), $header);
        $allowed = ['first_name', 'last_name', 'preferred_name', 'email', 'mobile', 'birthdate', 'gender', 'address_line', 'suburb', 'state', 'postcode', 'membership_status', 'first_visit', 'joined_on', 'household', 'household_role'];
        if (!in_array('first_name', $header, true) || !in_array('last_name', $header, true)) {
            flash('error', __('The CSV needs at least first_name and last_name columns.'));
            redirect('/people/import');
        }
        $created = $skipped = 0;
        $households = [];
        $hasConsent = input_bool('consent_confirmed');
        DB::tx(function () use ($fh, $header, $allowed, &$created, &$skipped, &$households, $hasConsent) {
            while (($row = fgetcsv($fh)) !== false) {
                $r = [];
                foreach ($header as $i => $col) {
                    if (in_array($col, $allowed, true)) {
                        $r[$col] = trim((string)($row[$i] ?? ''));
                    }
                }
                if (($r['first_name'] ?? '') === '' || ($r['last_name'] ?? '') === '') {
                    $skipped++;
                    continue;
                }
                $email = mb_strtolower($r['email'] ?? '');
                if ($email && DB::val('SELECT 1 FROM {p}people WHERE email = ? AND first_name = ?', [$email, $r['first_name']])) {
                    $skipped++;
                    continue;
                }
                $hid = null;
                if (!empty($r['household'])) {
                    $hid = $households[$r['household']] ??= ((int)DB::val('SELECT id FROM {p}households WHERE name = ?', [$r['household']]) ?: DB::insert('households', ['name' => mb_substr($r['household'], 0, 150), 'suburb' => $r['suburb'] ?? null, 'state' => $r['state'] ?? null, 'postcode' => $r['postcode'] ?? null]));
                }
                $date = fn($v) => ($v && strtotime(str_replace('/', '-', $v))) ? date('Y-m-d', strtotime(str_replace('/', '-', $v))) : null;
                DB::insert('people', [
                    'first_name' => mb_substr($r['first_name'], 0, 80), 'last_name' => mb_substr($r['last_name'], 0, 80), 'preferred_name' => ($r['preferred_name'] ?? '') ?: null,
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, 'mobile' => ($r['mobile'] ?? '') ?: null, 'birthdate' => $date($r['birthdate'] ?? ''),
                    'gender' => ($r['gender'] ?? '') ?: null, 'address_line' => ($r['address_line'] ?? '') ?: null, 'suburb' => ($r['suburb'] ?? '') ?: null, 'state' => ($r['state'] ?? '') ?: null, 'postcode' => ($r['postcode'] ?? '') ?: null,
                    'membership_status' => self::mapStatus((string)($r['membership_status'] ?? 'member')),
                    'first_visit' => $date($r['first_visit'] ?? ''), 'joined_on' => $date($r['joined_on'] ?? ''),
                    'household_id' => $hid, 'household_role' => preg_match('/child|son|daughter|dependent/i', (string)($r['household_role'] ?? '')) ? 'child' : 'adult',
                    'consent_contact' => $hasConsent ? 1 : 0, 'consent_updated_at' => $hasConsent ? date('Y-m-d H:i:s') : null,
                ]);
                $created++;
            }
        });
        fclose($fh);
        audit('people.imported', null, null, ['created' => $created, 'skipped' => $skipped]);
        flash('success', __('Imported %d people (%d rows skipped as blank or duplicate).', $created, $skipped));
        redirect('/people');
    }

    /** Import directly from Elvanto or Planning Center with an API key (never stored). */
    public function importApi(): void
    {
        $source = (string)input('source');
        if (!isset(ApiImport::SOURCES[$source])) {
            abort(404);
        }
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/people/import', __('Connecting to other systems is switched off on the demo site.'));
        }
        @set_time_limit(600);
        $groups = input_bool('include_groups');
        try {
            $set = $source === 'elvanto'
                ? ApiImport::fetchElvanto(trim((string)input('api_key', '')), $groups)
                : ApiImport::fetchPco(trim((string)input('app_id', '')), trim((string)input('secret', '')), $groups);
        } catch (Throwable $e) {
            flash('error', __('Could not read from %s: %s', ApiImport::SOURCES[$source], $e->getMessage()));
            redirect('/people/import');
        }
        if (input('mode') === 'check') {
            $adults = count(array_filter($set['people'], fn($p) => !$p['child']));
            flash('success', __('Connected to %s: found %d people (%d adults, %d children) and %d groups. Nothing has been imported yet.', ApiImport::SOURCES[$source], count($set['people']), $adults, count($set['people']) - $adults, count($set['groups'])));
            redirect('/people/import');
        }
        $runId = DB::insert('import_runs', ['source' => $source, 'started_by' => Auth::id()]);
        try {
            $n = ApiImport::save($source, $set, input_bool('update_existing'), input_bool('consent_confirmed'));
        } catch (Throwable $e) {
            DB::update('import_runs', ['status' => 'failed', 'summary' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => date('Y-m-d H:i:s')], 'id = ?', [$runId]);
            flash('error', __('The import stopped: %s. Nothing from this run was saved.', $e->getMessage()));
            redirect('/people/import');
        }
        DB::update('import_runs', ['status' => 'done', 'summary' => json_encode($n), 'finished_at' => date('Y-m-d H:i:s')], 'id = ?', [$runId]);
        audit('people.imported_api', null, null, ['source' => $source] + $n);
        flash('success', __('Imported from %s: %d new people, %d updated, %d matched to existing records, %d skipped; %d households, %d groups and %d group memberships.', ApiImport::SOURCES[$source], $n['created'], $n['updated'], $n['linked'], $n['skipped'], $n['households'], $n['groups'], $n['memberships']));
        redirect('/people/import');
    }
}
