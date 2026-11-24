<?php
declare(strict_types=1);

/**
 * Online forms: field definitions, rendering, validation and answer storage.
 * Answers are encrypted at rest because forms often collect health or family details
 * (camp medical forms, baptism testimonies).
 */
final class Forms
{
    /** Field types => label. "heading" and "paragraph" only display text. */
    public static function types(): array
    {
        return [
            'text' => __('Short answer'), 'textarea' => __('Long answer'), 'email' => __('Email'), 'phone' => __('Phone'),
            'number' => __('Number'), 'date' => __('Date'), 'select' => __('Drop-down'), 'radio' => __('Multiple choice (one answer)'),
            'checkboxes' => __('Checkboxes (several answers)'), 'yesno' => __('Yes / no'), 'consent' => __('Consent tick box'),
            'heading' => __('Section heading'), 'paragraph' => __('Information text'),
        ];
    }

    public static function displayOnly(string $type): bool
    {
        return in_array($type, ['heading', 'paragraph'], true);
    }

    public static function fields(array $form): array
    {
        $f = json_decode((string)$form['fields'], true);
        return is_array($f) ? $f : [];
    }

    public static function slugify(string $title): string
    {
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        return substr($s !== '' ? $s : 'form', 0, 60);
    }

    public static function uniqueSlug(string $base, int $exceptId = 0): string
    {
        $slug = $base;
        for ($i = 2; DB::val('SELECT 1 FROM {p}forms WHERE slug = ? AND id <> ?', [$slug, $exceptId]); $i++) {
            $slug = substr($base, 0, 55) . '-' . $i;
        }
        return $slug;
    }

    /** Why the form can't take responses right now, or null if it can. */
    public static function closedReason(array $form): ?string
    {
        if ($form['status'] === 'draft') {
            return __('This form is not open yet.');
        }
        if ($form['status'] === 'closed' || ($form['closes_at'] && strtotime($form['closes_at']) < time())) {
            return __('This form is now closed.');
        }
        if ($form['opens_at'] && strtotime($form['opens_at']) > time()) {
            return __('This form opens on %s.', fmt_datetime($form['opens_at']));
        }
        if ($form['capacity'] && self::taken((int)$form['id']) >= (int)$form['capacity']) {
            return __('Sorry — this form has reached its limit and is full.');
        }
        return null;
    }

    public static function taken(int $formId): int
    {
        return (int)DB::val("SELECT COALESCE(SUM(places), 0) FROM {p}form_submissions WHERE form_id = ? AND status IN ('submitted','paid','part_paid','overdue','awaiting_payment') AND (status <> 'awaiting_payment' OR created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))", [$formId]);
    }

    /** Validate posted answers. Returns [answers, errors]. */
    public static function validate(array $fields, array $post): array
    {
        $answers = [];
        $errors = [];
        foreach ($fields as $f) {
            if (self::displayOnly($f['type'])) {
                continue;
            }
            $key = 'f_' . $f['key'];
            $raw = $post[$key] ?? null;
            $label = (string)$f['label'];
            $opts = array_values(array_filter(array_map('trim', (array)($f['options'] ?? []))));
            switch ($f['type']) {
                case 'checkboxes':
                    $v = array_values(array_intersect(array_map('strval', (array)$raw), $opts));
                    $empty = !$v;
                    break;
                case 'select':
                case 'radio':
                    $v = in_array((string)$raw, $opts, true) ? (string)$raw : '';
                    $empty = $v === '';
                    break;
                case 'yesno':
                    $v = in_array($raw, ['yes', 'no'], true) ? $raw : '';
                    $empty = $v === '';
                    break;
                case 'consent':
                    $v = !empty($raw) ? 'yes' : '';
                    $empty = $v === '';
                    break;
                default:
                    $v = trim(mb_substr((string)$raw, 0, $f['type'] === 'textarea' ? 5000 : 500));
                    $empty = $v === '';
                    if (!$empty && $f['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = __('%s: enter a valid email address.', $label);
                    }
                    if (!$empty && $f['type'] === 'number' && !is_numeric($v)) {
                        $errors[] = __('%s: enter a number.', $label);
                    }
                    if (!$empty && $f['type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        $errors[] = __('%s: enter a date.', $label);
                    }
            }
            if ($empty && (!empty($f['required']) || $f['type'] === 'consent')) {
                $errors[] = __('%s is required.', $label);
            }
            $answers[$f['key']] = $v;
        }
        return [$answers, $errors];
    }

    public static function encryptAnswers(array $answers): string
    {
        return Crypto::encrypt((string)json_encode($answers, JSON_UNESCAPED_UNICODE));
    }

    public static function answers(array $sub): array
    {
        $a = json_decode((string)Crypto::decrypt((string)$sub['answers']), true);
        return is_array($a) ? $a : [];
    }

    public static function show(mixed $v): string
    {
        if (is_array($v)) {
            return implode(', ', $v);
        }
        return match ((string)$v) { 'yes' => __('Yes'), 'no' => __('No'), default => (string)$v };
    }

    /** Plain answers for an email (label: value). */
    public static function summary(array $fields, array $answers): string
    {
        $html = '<table cellpadding="4" style="border-collapse:collapse">';
        foreach ($fields as $f) {
            if (self::displayOnly($f['type'])) {
                continue;
            }
            $html .= '<tr><td style="vertical-align:top;color:#555"><strong>' . e($f['label']) . '</strong></td><td>' . nl2br(e(self::show($answers[$f['key']] ?? ''))) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /** Mark a paid submission (Stripe webhook or return page). Idempotent. */
    /**
     * Profile fields a question's answer can be saved to: key => [label, question types that fit].
     * Custom profile fields are included as "custom:<id>". Health and care details are deliberately not offered:
     * those belong in the encrypted care record, updated by people with that permission.
     */
    public static function profileTargets(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $t = [
            'mobile' => [__('Mobile'), ['phone', 'text']],
            'birthdate' => [__('Date of birth'), ['date']],
            'address_line' => [__('Street address'), ['text', 'textarea']],
            'suburb' => [__('Suburb'), ['text']],
            'state' => [__('State'), ['text', 'select', 'radio']],
            'postcode' => [__('Postcode'), ['text', 'number']],
            'first_visit' => [__('First visit'), ['date']],
            'joined_on' => [__('Member since'), ['date']],
        ];
        foreach (DB::all('SELECT * FROM {p}custom_fields ORDER BY sort, label') as $cf) {
            $types = match ($cf['field_type']) {
                'date' => ['date'],
                'checkbox' => ['yesno', 'consent'],
                'select' => ['select', 'radio', 'text'],
                default => ['text', 'textarea', 'select', 'radio', 'number', 'phone', 'email'],
            };
            $t['custom:' . $cf['id']] = [$cf['label'], $types];
        }
        return $cache = $t;
    }

    /** Questions on a form that save to a profile field. */
    public static function mappedFields(array $form): array
    {
        $targets = self::profileTargets();
        return array_values(array_filter(self::fields($form), fn($f) => !empty($f['profile']) && isset($targets[$f['profile']])));
    }

    /**
     * What applying this response to a profile would change: list of [label, target label, new value, current value].
     * Blank answers and answers that don't fit the field are left out.
     */
    public static function profileChanges(array $form, array $sub, int $personId): array
    {
        $person = DB::row('SELECT * FROM {p}people WHERE id = ?', [$personId]);
        if (!$person) {
            return [];
        }
        $targets = self::profileTargets();
        $answers = self::answers($sub);
        $out = [];
        foreach (self::mappedFields($form) as $f) {
            $raw = $answers[$f['key']] ?? '';
            $val = is_array($raw) ? implode(', ', $raw) : trim((string)$raw);
            if ($val === '') {
                continue;
            }
            $target = (string)$f['profile'];
            if (str_starts_with($target, 'custom:')) {
                $cf = DB::row('SELECT * FROM {p}custom_fields WHERE id = ?', [(int)substr($target, 7)]);
                if (!$cf) {
                    continue;
                }
                if ($cf['field_type'] === 'checkbox') {
                    $val = $val === 'yes' ? '1' : '0';
                } elseif ($cf['field_type'] === 'select' && !in_array($val, array_map('trim', preg_split('/[,\n]/', (string)$cf['options'])), true)) {
                    continue;
                } elseif ($cf['field_type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    continue;
                }
                $current = (string)DB::val('SELECT value FROM {p}custom_field_values WHERE person_id = ? AND field_id = ?', [$personId, $cf['id']]);
            } else {
                if (in_array($target, ['birthdate', 'first_visit', 'joined_on'], true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    continue;
                }
                $val = mb_substr($val, 0, $target === 'address_line' ? 200 : ($target === 'postcode' ? 12 : ($target === 'mobile' ? 40 : 100)));
                $current = (string)($person[$target] ?? '');
            }
            if ($val !== $current) {
                $out[] = ['key' => $f['key'], 'question' => (string)$f['label'], 'target' => $target, 'field' => $targets[$target][0], 'new' => $val, 'old' => $current];
            }
        }
        return $out;
    }

    /** Save mapped answers to a person's profile. Returns the profile fields changed. */
    public static function applyToProfile(array $form, array $sub, int $personId, ?array $only = null): array
    {
        $changed = [];
        $person = [];
        foreach (self::profileChanges($form, $sub, $personId) as $c) {
            if ($only !== null && !in_array($c['key'], $only, true)) {
                continue;
            }
            if (str_starts_with($c['target'], 'custom:')) {
                DB::run('INSERT INTO {p}custom_field_values (person_id, field_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$personId, (int)substr($c['target'], 7), $c['new']]);
            } else {
                $person[$c['target']] = $c['new'];
            }
            $changed[] = $c['field'];
        }
        if ($person) {
            DB::update('people', $person + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$personId]);
        }
        if ($changed) {
            DB::update('form_submissions', ['profile_applied_at' => date('Y-m-d H:i:s')], 'id = ?', [$sub['id']]);
            audit('form.profile_updated', 'person', $personId, ['form_submission' => (int)$sub['id'], 'fields' => $changed]);
        }
        return $changed;
    }

    /** Ticket types on a form: list of ['name', 'price_cents', 'places']. Empty when the form has a single price. */
    public static function tickets(array $form): array
    {
        $t = json_decode((string)($form['tickets'] ?? ''), true);
        return is_array($t) ? array_values(array_filter($t, fn($x) => is_array($x) && trim((string)($x['name'] ?? '')) !== '')) : [];
    }

    /** Does filling in this form cost anything? */
    public static function isPaid(array $form): bool
    {
        $tickets = self::tickets($form);
        return $tickets ? max(array_map(fn($t) => (int)$t['price_cents'], $tickets)) > 0 : (int)$form['price_cents'] > 0;
    }

    /** "$180.00", "From $10.00" or "Free". */
    public static function priceText(array $form): string
    {
        $tickets = self::tickets($form);
        if (!$tickets) {
            return (int)$form['price_cents'] ? fmt_money((int)$form['price_cents']) : __('Free');
        }
        $prices = array_map(fn($t) => (int)$t['price_cents'], $tickets);
        if (max($prices) === 0) {
            return __('Free');
        }
        $paid = array_filter($prices);
        return count(array_unique($prices)) === 1 ? fmt_money(max($prices)) : __('From %s', fmt_money(min($paid)));
    }

    /**
     * Read the ticket quantities posted with a form. Returns [lines, total cents, places, errors]; each line is
     * a snapshot (name, qty, price, places) so renaming a ticket later doesn't change old responses.
     */
    public static function ticketChoice(array $form, array $post): array
    {
        $lines = [];
        $total = 0;
        $places = 0;
        $qty = (array)($post['qty'] ?? []);
        foreach (self::tickets($form) as $i => $t) {
            $n = max(0, min(20, (int)($qty[$i] ?? 0)));
            if ($n > 0) {
                $lines[] = ['name' => (string)$t['name'], 'qty' => $n, 'price_cents' => (int)$t['price_cents'], 'places' => max(1, (int)$t['places'])];
                $total += $n * (int)$t['price_cents'];
                $places += $n * max(1, (int)$t['places']);
            }
        }
        return [$lines, $total, $places, $lines ? [] : [__('Choose at least one ticket.')]];
    }

    /** "2 × Adult, 3 × Child" */
    public static function ticketSummary(array $sub): string
    {
        $lines = json_decode((string)($sub['tickets'] ?? ''), true);
        return is_array($lines) ? implode(', ', array_map(fn($l) => $l['qty'] . ' × ' . $l['name'], $lines)) : '';
    }

    /** Instalment choices for a form: number of payments => cents per payment. Always includes 1 (pay in full). */
    public static function plans(array $form, ?int $total = null): array
    {
        $price = $total ?? (int)$form['price_cents'];
        $max = max(1, (int)($form['instalments'] ?? 1));
        $plans = [1 => $price];
        if ($price > 0) {
            foreach ([2, 3, 4, 6, 10, 12] as $n) {
                if ($n <= $max) {
                    $plans[$n] = intdiv($price + $n - 1, $n);
                }
            }
        }
        return $plans;
    }

    /** "3 monthly payments of $60.00" */
    public static function planLabel(int $n, int $each): string
    {
        return $n <= 1 ? __('Pay in full: %s', fmt_money($each)) : __('%d monthly payments of %s', $n, fmt_money($each));
    }

    /** One-off card payment confirmed (by the webhook or the return page). */
    public static function markPaid(array $sub, string $ref = '', ?string $pi = null): void
    {
        self::recordPayment($sub, $ref !== '' ? $ref : (string)$sub['stripe_session_id'], (int)$sub['amount_cents'] - (int)($sub['paid_cents'] ?? 0), 'card', $pi);
    }

    /**
     * Record money received for a response and update its status. $ref (a Stripe session or invoice id) makes it
     * safe to call more than once for the same payment. Staff payments pass a null $ref.
     * Returns false if this payment was already recorded.
     */
    public static function recordPayment(array $sub, ?string $ref, int $cents, string $method = 'card', ?string $pi = null): bool
    {
        $cents = max(0, $cents);
        if ($ref !== null && $ref !== '' && DB::val('SELECT 1 FROM {p}form_payments WHERE stripe_ref = ?', [$ref])) {
            return false;
        }
        $wasStarted = in_array($sub['status'], ['part_paid', 'overdue', 'paid'], true);
        if ($cents > 0) {
            DB::insert('form_payments', ['submission_id' => $sub['id'], 'stripe_ref' => $ref ?: null, 'stripe_pi' => $pi ?: null, 'amount_cents' => $cents, 'method' => $method]);
        }
        $sub = self::recalculate($sub);
        $full = $sub['status'] === 'paid';
        audit($full ? 'form.paid' : 'form.part_paid', 'form_submission', (int)$sub['id'], ['cents' => $cents, 'method' => $method]);
        if ($full && !empty($sub['stripe_subscription_id'])) {
            self::stopInstalments($sub);
        }
        $form = DB::row('SELECT * FROM {p}forms WHERE id = ?', [$sub['form_id']]);
        if ($form && !$wasStarted && $cents > 0) {
            self::confirmed($form, $sub);
            if ($method === 'card') {
                self::notify($form, $sub); // the response is confirmed once the first payment arrives
            }
        } elseif ($form && $full && (int)$sub['instalments'] > 1 && $sub['email']) {
            Notify::emailAddress((string)$sub['email'], __('All payments received: %s', $form['title']), '<p>' . e(__('Thank you — your last payment for %s has been received. You have paid %s in full, and no more payments will be taken.', $form['title'], fmt_money((int)$sub['paid_cents']))) . '</p>');
        }
        return true;
    }

    /** Re-total payments less refunds, and set the status to match (a cancelled response stays cancelled). */
    public static function recalculate(array $sub): array
    {
        [$paid, $refunded] = array_map('intval', array_values(DB::row('SELECT COALESCE(SUM(amount_cents - refunded_cents), 0) AS net, COALESCE(SUM(refunded_cents), 0) AS ref FROM {p}form_payments WHERE submission_id = ?', [$sub['id']]) ?? ['net' => 0, 'ref' => 0]));
        $status = $sub['status'];
        $amount = (int)$sub['amount_cents'];
        if ($status !== 'cancelled') {
            if ($amount > 0 && $paid >= $amount) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = $status === 'overdue' ? 'overdue' : 'part_paid';
            } elseif (in_array($status, ['paid', 'part_paid', 'overdue'], true)) {
                $status = $amount > 0 ? 'awaiting_payment' : 'submitted';
            }
        }
        DB::update('form_submissions', ['paid_cents' => $paid, 'refunded_cents' => $refunded, 'status' => $status], 'id = ?', [$sub['id']]);
        return array_merge($sub, ['paid_cents' => $paid, 'refunded_cents' => $refunded, 'status' => $status]);
    }

    /** A response is confirmed (free and submitted, or first payment received): register them for the linked event. */
    public static function confirmed(array $form, array $sub): void
    {
        if (empty($form['event_id'])) {
            return;
        }
        $ev = DB::row("SELECT * FROM {p}events WHERE id = ? AND status = 'scheduled'", [$form['event_id']]);
        if ($ev) {
            Events::registerFromForm($ev, $sub);
        }
    }

    /** Cancel a response: stop any instalments and give up its event place. */
    public static function cancel(array $sub, string $note = ''): void
    {
        if (!empty($sub['stripe_subscription_id']) && (int)$sub['paid_cents'] < (int)$sub['amount_cents']) {
            self::stopInstalments($sub);
        }
        $upd = ['status' => 'cancelled'];
        if ($note !== '') {
            $upd['staff_notes'] = mb_substr(trim($note . ' ' . (string)$sub['staff_notes']), 0, 500);
        }
        DB::update('form_submissions', $upd, 'id = ?', [$sub['id']]);
        foreach (DB::col("SELECT id FROM {p}event_registrations WHERE form_submission_id = ? AND status IN ('registered','waitlisted')", [$sub['id']]) as $rid) {
            Events::cancel((int)$rid);
        }
        audit('form.cancelled', 'form_submission', (int)$sub['id']);
    }

    /** Card payments that can still be refunded through Stripe, newest first. */
    public static function refundable(array $sub): int
    {
        return (int)DB::val("SELECT COALESCE(SUM(amount_cents - refunded_cents), 0) FROM {p}form_payments WHERE submission_id = ? AND method = 'card' AND stripe_ref IS NOT NULL", [$sub['id']]);
    }

    /** The PaymentIntent (or charge) behind a recorded card payment, looked up in Stripe if we don't have it yet. */
    private static function paymentTarget(array $pay): array
    {
        if (!empty($pay['stripe_pi'])) {
            return [str_starts_with((string)$pay['stripe_pi'], 'ch_') ? 'charge' : 'payment_intent', (string)$pay['stripe_pi']];
        }
        $ref = (string)$pay['stripe_ref'];
        $obj = str_starts_with($ref, 'in_') ? Stripe::api('GET', 'invoices/' . rawurlencode($ref) . '?expand[]=payments') : Stripe::api('GET', 'checkout/sessions/' . rawurlencode($ref));
        $pi = $obj['payment_intent'] ?? ($obj['payments']['data'][0]['payment']['payment_intent'] ?? null);
        if (is_array($pi)) {
            $pi = $pi['id'] ?? null;
        }
        if ($pi) {
            DB::update('form_payments', ['stripe_pi' => $pi], 'id = ?', [$pay['id']]);
            return ['payment_intent', (string)$pi];
        }
        if (!empty($obj['charge'])) {
            return ['charge', (string)(is_array($obj['charge']) ? $obj['charge']['id'] : $obj['charge'])];
        }
        throw new RuntimeException(__('Stripe has no payment to refund for %s.', $ref));
    }

    /**
     * Refund up to $cents of a response. 'card' refunds through Stripe (newest payments first); 'cash' or 'bank'
     * records money given back another way. With $cancel the response is cancelled and its event place released;
     * otherwise the amount owed goes down by the refund (for a discount or a part refund).
     * Returns the cents refunded.
     */
    public static function refund(array $sub, int $cents, string $how, bool $cancel): int
    {
        $left = $cents;
        $error = null;
        if ($how === 'card') {
            if ($cents > self::refundable($sub)) {
                throw new RuntimeException(__('Only %s was paid by card through Stripe and can be refunded that way.', fmt_money(self::refundable($sub))));
            }
            try {
                foreach (DB::all("SELECT * FROM {p}form_payments WHERE submission_id = ? AND method = 'card' AND stripe_ref IS NOT NULL AND amount_cents > refunded_cents ORDER BY paid_at DESC, id DESC", [$sub['id']]) as $pay) {
                    if ($left <= 0) {
                        break;
                    }
                    $part = min($left, (int)$pay['amount_cents'] - (int)$pay['refunded_cents']);
                    if (!Demo::on()) {
                        [$field, $id] = self::paymentTarget($pay);
                        Stripe::api('POST', 'refunds', [$field => $id, 'amount' => $part, 'metadata[form_submission_id]' => $sub['id'], 'metadata[source]' => 'affreso']);
                    }
                    DB::run('UPDATE {p}form_payments SET refunded_cents = refunded_cents + ? WHERE id = ?', [$part, $pay['id']]);
                    $left -= $part;
                }
            } catch (Throwable $e) {
                $error = $e; // keep what was refunded before Stripe said no
            }
        } else {
            // Money returned in cash or by bank transfer: set it against the payments, newest first.
            foreach (DB::all('SELECT * FROM {p}form_payments WHERE submission_id = ? AND amount_cents > refunded_cents ORDER BY paid_at DESC, id DESC', [$sub['id']]) as $pay) {
                if ($left <= 0) {
                    break;
                }
                $part = min($left, (int)$pay['amount_cents'] - (int)$pay['refunded_cents']);
                DB::run('UPDATE {p}form_payments SET refunded_cents = refunded_cents + ? WHERE id = ?', [$part, $pay['id']]);
                $left -= $part;
            }
        }
        $done = $cents - $left;
        if (!$cancel && $done > 0) {
            DB::update('form_submissions', ['amount_cents' => max(0, (int)$sub['amount_cents'] - $done)], 'id = ?', [$sub['id']]);
            $sub['amount_cents'] = max(0, (int)$sub['amount_cents'] - $done);
        }
        $sub = self::recalculate($sub);
        audit('form.refunded', 'form_submission', (int)$sub['id'], ['cents' => $done, 'how' => $how, 'cancel' => $cancel]);
        if ($error) {
            throw new RuntimeException($done > 0 ? __('%s was refunded, but Stripe refused the rest: %s', fmt_money($done), $error->getMessage()) : $error->getMessage());
        }
        if ($cancel) {
            self::cancel($sub);
        }
        if ($done > 0 && $sub['email']) {
            $form = DB::row('SELECT title FROM {p}forms WHERE id = ?', [$sub['form_id']]);
            Notify::emailAddress((string)$sub['email'], __('Refund: %s', $form['title'] ?? ''), '<p>' . e($how === 'card'
                ? __('We have refunded %s to your card for %s. It usually reaches your account within 5–10 business days.', fmt_money($done), $form['title'] ?? '')
                : __('We have recorded a refund of %s for %s.', fmt_money($done), $form['title'] ?? '')) . '</p>' . ($cancel ? '<p>' . e(__('Your registration has been cancelled.')) . '</p>' : ''));
        }
        return $done;
    }

    /**
     * Stripe reports a refund (from Affreso or the Stripe dashboard). amount_refunded is the charge's running
     * total, so applying it twice changes nothing. A full refund of everything paid cancels the response.
     */
    public static function refundFromStripe(string $pi, int $amountRefunded): bool
    {
        $pay = DB::row('SELECT * FROM {p}form_payments WHERE stripe_pi = ?', [$pi]);
        if (!$pay) {
            return false;
        }
        DB::update('form_payments', ['refunded_cents' => min((int)$pay['amount_cents'], max((int)$pay['refunded_cents'], $amountRefunded))], 'id = ?', [$pay['id']]);
        $sub = DB::row('SELECT * FROM {p}form_submissions WHERE id = ?', [$pay['submission_id']]);
        if ($sub) {
            $sub = self::recalculate($sub);
            if ($sub['status'] !== 'cancelled' && (int)$sub['paid_cents'] <= 0 && (int)$sub['refunded_cents'] > 0) {
                self::cancel($sub, __('Refunded through Stripe'));
            }
        }
        return true;
    }

    /** Cancel the Stripe subscription behind an instalment plan (after the last payment, or when staff stop it). */
    public static function stopInstalments(array $sub): void
    {
        if (empty($sub['stripe_subscription_id']) || Demo::on() || !Stripe::enabled()) {
            return;
        }
        try {
            Stripe::api('DELETE', 'subscriptions/' . rawurlencode((string)$sub['stripe_subscription_id']));
        } catch (Throwable $e) {
            // Already cancelled, or Stripe unreachable: staff can stop it from the Stripe dashboard.
            error_log('Affreso: could not cancel form instalments ' . $sub['stripe_subscription_id'] . ': ' . $e->getMessage());
        }
    }

    /** Payments received for a response, oldest first. */
    public static function payments(int $subId): array
    {
        return DB::all('SELECT * FROM {p}form_payments WHERE submission_id = ? ORDER BY paid_at, id', [$subId]);
    }

    /** "Paid", "2 of 3 payments made ($120.00 of $180.00)" and so on. */
    public static function paymentSummary(array $sub): string
    {
        $amount = (int)$sub['amount_cents'];
        $paid = (int)($sub['paid_cents'] ?? 0);
        $refunded = (int)($sub['refunded_cents'] ?? 0);
        if (!$amount && !$refunded) {
            return '';
        }
        if ($refunded > 0 && ($sub['status'] ?? '') === 'cancelled') {
            return __('%s refunded', fmt_money($refunded)) . ($paid > 0 ? ' · ' . __('Paid %s', fmt_money($paid)) : '');
        }
        if ($paid >= $amount) {
            return __('Paid %s', fmt_money($paid)) . ($refunded > 0 ? ' · ' . __('%s refunded', fmt_money($refunded)) : '');
        }
        if ((int)($sub['instalments'] ?? 1) > 1) {
            $made = (int)DB::val("SELECT COUNT(*) FROM {p}form_payments WHERE submission_id = ? AND method = 'card'", [$sub['id']]);
            return __('%1$d of %2$d payments made (%3$s of %4$s)', min($made, (int)$sub['instalments']), (int)$sub['instalments'], fmt_money($paid), fmt_money($amount));
        }
        return $paid >= $amount ? __('Paid %s', fmt_money($paid)) : ($paid > 0 ? __('%s of %s paid', fmt_money($paid), fmt_money($amount)) : __('Payment: %s (%s)', fmt_money($amount), __('not yet paid')));
    }

    /** Email the staff contacts and the person who filled it in. */
    public static function notify(array $form, array $sub): void
    {
        $fields = self::fields($form);
        $answers = self::answers($sub);
        $paid = (int)$sub['amount_cents'] ? '<p>' . e(self::paymentSummary($sub)) . '</p>' : '';
        if ((int)($sub['instalments'] ?? 1) > 1 && $sub['status'] !== 'paid') {
            $paid .= '<p>' . e(__('The remaining payments will be taken monthly from the same card until %s is paid.', fmt_money((int)$sub['amount_cents']))) . '</p>';
        }
        foreach (array_filter(array_map('trim', explode(',', (string)$form['notify_emails']))) as $to) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Notify::emailAddress($to, __('New response: %s', $form['title']), '<p>' . e(__('%s filled in "%s".', $sub['name'], $form['title'])) . '</p>' . $paid . '<p><a href="' . e(url('/forms/' . $form['id'] . '/responses/' . $sub['id'], [], true)) . '">' . e(__('View the response in Affreso')) . '</a></p>');
            }
        }
        if ($sub['email']) {
            $msg = trim((string)$form['confirmation']) !== '' ? nl2br(e((string)$form['confirmation'])) : e(__('Thank you — we have received your response.'));
            Notify::emailAddress((string)$sub['email'], __('Received: %s', $form['title']), '<p>' . $msg . '</p>' . $paid . '<h3>' . e(__('Your answers')) . '</h3>' . self::summary($fields, $answers));
        }
    }
}
