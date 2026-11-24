<?php
declare(strict_types=1);

final class GivingController
{
    public static function receiptNo(int $id): string
    {
        return 'R' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    }

    public function index(): void
    {
        $from = input_date('from') ?? ((int)date('n') >= 7 ? date('Y') . '-07-01' : (date('Y') - 1) . '-07-01');
        $to = input_date('to') ?? date('Y-m-d');
        $fund = input_int('fund');
        $where = "COALESCE(d.received_on, DATE(d.created_at)) BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($fund) {
            $where .= ' AND d.fund_id = ?';
            $params[] = $fund;
        }
        $rows = DB::all("SELECT d.*, f.name AS fund, p.first_name, p.last_name, p.preferred_name FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id LEFT JOIN {p}people p ON p.id = d.person_id WHERE $where ORDER BY COALESCE(d.received_on, DATE(d.created_at)) DESC, d.id DESC LIMIT 500", $params);
        $byFund = DB::all("SELECT f.name, SUM(d.amount_cents) AS total, COUNT(*) AS n FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.status = 'paid' AND $where GROUP BY f.name ORDER BY total DESC", $params);
        $funds = array_column(DB::all('SELECT id, name FROM {p}funds WHERE active = 1 ORDER BY name'), 'name', 'id');
        audit('giving.viewed');
        view('giving/index', ['title' => __('Giving'), 'rows' => $rows, 'byFund' => $byFund, 'funds' => $funds, 'from' => $from, 'to' => $to, 'fund' => $fund]);
    }

    public function store(): void
    {
        $amount = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        if ($amount <= 0) {
            flash('error', __('Enter an amount.'));
            redirect('/giving');
        }
        $method = in_array(input('method'), ['cash', 'eft', 'cheque', 'other'], true) ? input('method') : 'other';
        $pid = input_int('person_id');
        $id = DB::insert('donations', ['person_id' => $pid, 'fund_id' => input_int('fund_id'), 'donor_name' => input('donor_name') ?: null, 'amount_cents' => $amount, 'currency' => setting('currency', 'AUD'), 'method' => $method, 'status' => 'paid', 'received_on' => input_date('received_on') ?? date('Y-m-d'), 'notes' => mb_substr((string)input('notes', ''), 0, 255) ?: null]);
        DB::update('donations', ['receipt_no' => self::receiptNo($id)], 'id = ?', [$id]);
        audit('giving.recorded', 'donation', $id);
        flash('success', __('Donation recorded (%s).', self::receiptNo($id)));
        redirect('/giving');
    }

    public function export(): void
    {
        $from = input_date('from') ?? date('Y-01-01');
        $to = input_date('to') ?? date('Y-m-d');
        audit('giving.exported');
        $rows = DB::all("SELECT COALESCE(d.received_on, DATE(d.created_at)) AS date, d.receipt_no, COALESCE(CONCAT(p.first_name, ' ', p.last_name), d.donor_name) AS donor, d.donor_email, f.name AS fund, d.amount_cents / 100 AS amount, d.currency, d.method, d.status FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id LEFT JOIN {p}people p ON p.id = d.person_id WHERE COALESCE(d.received_on, DATE(d.created_at)) BETWEEN ? AND ? ORDER BY date", [$from, $to]);
        $paid = array_values(array_filter($rows, fn($r) => $r['status'] === 'paid'));
        if (input('format') === 'xero') {
            csv_download("xero-giving-$from-to-$to.csv", ['*Date', '*Amount', 'Payee', 'Description', 'Reference', 'Account Code'], array_map(fn($r) => [date('d/m/Y', strtotime($r['date'])), number_format((float)$r['amount'], 2, '.', ''), $r['donor'] ?: 'Anonymous', 'Donation — ' . ($r['fund'] ?: 'General'), $r['receipt_no'], (string)(setting('xero_income_account') ?: '200')], $paid));
        }
        if (input('format') === 'myob') {
            csv_download("myob-giving-$from-to-$to.csv", ['Date', 'Memo', 'Amount', 'Account', 'Reference'], array_map(fn($r) => [date('d/m/Y', strtotime($r['date'])), 'Donation ' . ($r['fund'] ?: 'General') . ' — ' . ($r['donor'] ?: 'Anonymous'), number_format((float)$r['amount'], 2, '.', ''), (string)(setting('xero_income_account') ?: '4-1000'), $r['receipt_no']], $paid));
        }
        csv_download("giving-$from-to-$to.csv", ['date', 'receipt_no', 'donor', 'email', 'fund', 'amount', 'currency', 'method', 'status'], $rows);
    }

    public function refundForm(string $id): void
    {
        $d = DB::row('SELECT d.*, f.name AS fund, p.first_name, p.last_name, p.preferred_name FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id LEFT JOIN {p}people p ON p.id = d.person_id WHERE d.id = ?', [(int)$id]) ?? abort(404);
        view('giving/refund', ['title' => __('Refund a gift'), 'd' => $d]);
    }

    public function refund(string $id): void
    {
        $d = DB::row('SELECT * FROM {p}donations WHERE id = ?', [(int)$id]) ?? abort(404);
        $cents = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        $how = input('how') === 'card' ? 'card' : 'record';
        try {
            $d = Gifts::refund($d, $cents, $how, mb_substr(trim((string)input('reason', '')), 0, 80));
            flash('success', $how === 'card' ? __('%s refunded to the card through Stripe.', fmt_money($cents, $d['currency'])) : __('Refund of %s recorded.', fmt_money($cents, $d['currency'])));
            redirect('/giving');
        } catch (Throwable $e) {
            error_log('Affreso gift refund: ' . $e->getMessage());
            flash('error', __('The refund didn\'t go through: %s', $e->getMessage()));
            redirect('/giving/' . (int)$id . '/refund');
        }
    }

    public function sendReceipt(string $id): void
    {
        $d = DB::row('SELECT d.*, f.name AS fund FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.id = ?', [(int)$id]) ?? abort(404);
        self::emailReceipt($d);
        Notify::processQueue(5);
        flash('success', __('Receipt queued.'));
        redirect('/giving');
    }

    public static function emailReceipt(array $d): void
    {
        $html = '<p>' . e(__('Thank you for your gift to %s.', setting('church_name'))) . '</p><table cellpadding="6"><tr><td>' . e(__('Receipt')) . '</td><td><strong>' . e($d['receipt_no']) . '</strong></td></tr><tr><td>' . e(__('Date')) . '</td><td>' . e(fmt_date($d['received_on'] ?: $d['created_at'])) . '</td></tr><tr><td>' . e(__('Amount')) . '</td><td>' . e(fmt_money((int)$d['amount_cents'], $d['currency'])) . '</td></tr><tr><td>' . e(__('Fund')) . '</td><td>' . e($d['fund'] ?? '') . '</td></tr></table><p style="font-size:12px;color:#666">' . e((string)setting('giving_dgr_statement')) . ' ' . e((string)setting('church_abn') ? 'ABN ' . setting('church_abn') : '') . '</p>';
        if ($d['person_id']) {
            Notify::emailPerson((int)$d['person_id'], __('Your giving receipt %s', $d['receipt_no']), $html, true);
        } elseif ($d['donor_email']) {
            Notify::emailAddress($d['donor_email'], __('Your giving receipt %s', $d['receipt_no']), $html);
        }
    }

    // Public online giving (Stripe Checkout) ---------------------------------
    public function giveForm(): void
    {
        $funds = array_column(DB::all('SELECT id, name FROM {p}funds WHERE active = 1 ORDER BY id'), 'name', 'id');
        $me = person_id() ? DB::row('SELECT * FROM {p}people WHERE id = ?', [person_id()]) : null;
        view('giving/give', ['title' => __('Give'), 'funds' => $funds, 'enabled' => Stripe::enabled(), 'me' => $me], 'layout_public');
    }

    public function give(): void
    {
        if (!Stripe::enabled()) {
            abort(404);
        }
        $amount = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        if ($amount < 100 || $amount > 5000000) {
            flash('error', __('Please enter an amount between $1 and $50,000.'));
            redirect('/give');
        }
        $fundId = input_int('fund_id');
        $fund = (string)DB::val('SELECT name FROM {p}funds WHERE id = ? AND active = 1', [$fundId]) ?: __('General');
        $email = filter_var((string)input('email', ''), FILTER_VALIDATE_EMAIL) ?: null;
        $name = mb_substr((string)input('name', ''), 0, 160) ?: null;
        $freq = (string)input('frequency', 'once');
        $label = __('Gift to %s — %s', setting('church_name'), $fund);
        try {
            if (in_array($freq, ['week', 'fortnight', 'month'], true)) {
                if (!$email) {
                    flash('error', __('Please enter your email for a regular gift, so we can send receipts.'));
                    redirect('/give');
                }
                $rid = DB::insert('recurring_gifts', ['person_id' => person_id(), 'donor_name' => $name, 'donor_email' => $email, 'amount_cents' => $amount, 'currency' => setting('currency', 'AUD'), 'frequency' => $freq, 'fund_id' => $fundId ?: null]);
                $s = Stripe::createSubscriptionCheckout($rid, $amount, (string)setting('currency', 'AUD'), $label, $freq, $email, url('/give/thanks', [], true), url('/give', [], true));
                DB::update('recurring_gifts', ['stripe_session_id' => $s['id']], 'id = ?', [$rid]);
            } else {
                $id = DB::insert('donations', ['person_id' => person_id(), 'fund_id' => $fundId ?: null, 'donor_name' => $name, 'donor_email' => $email, 'amount_cents' => $amount, 'currency' => setting('currency', 'AUD'), 'method' => 'stripe', 'status' => 'pending']);
                $s = Stripe::createCheckout($id, $amount, (string)setting('currency', 'AUD'), $label, $email, url('/give/thanks', [], true), url('/give', [], true));
                DB::update('donations', ['stripe_session_id' => $s['id']], 'id = ?', [$id]);
            }
        } catch (Throwable $e) {
            error_log('[Affreso] Stripe: ' . $e->getMessage());
            flash('error', __('Online giving is temporarily unavailable. Please try again later.'));
            redirect('/give');
        }
        header('Location: ' . $s['url'], true, 303);
        exit;
    }

    private static function markPaid(array $d, ?string $pi = null): void
    {
        if ($d['status'] === 'paid') {
            if ($pi && empty($d['stripe_pi'])) {
                DB::update('donations', ['stripe_pi' => $pi], 'id = ?', [$d['id']]);
            }
            return;
        }
        DB::update('donations', ['status' => 'paid', 'received_on' => date('Y-m-d'), 'receipt_no' => self::receiptNo((int)$d['id']), 'stripe_pi' => $pi ?: ($d['stripe_pi'] ?? null)], 'id = ?', [$d['id']]);
        $d = DB::row('SELECT d.*, f.name AS fund FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.id = ?', [$d['id']]);
        self::emailReceipt($d);
        audit('giving.online_paid', 'donation', (int)$d['id']);
    }

    /** Record one paid instalment of a recurring gift (idempotent per Stripe invoice). */
    private static function recordInstalment(array $rg, string $invoiceId, int $cents, ?string $pi = null): void
    {
        if (DB::val('SELECT 1 FROM {p}donations WHERE external_ref = ?', ['stripe:' . $invoiceId])) {
            return;
        }
        $id = DB::insert('donations', ['person_id' => $rg['person_id'], 'fund_id' => $rg['fund_id'], 'donor_name' => $rg['donor_name'], 'donor_email' => $rg['donor_email'], 'amount_cents' => $cents ?: (int)$rg['amount_cents'], 'currency' => $rg['currency'], 'method' => 'stripe', 'status' => 'pending', 'recurring_id' => $rg['id'], 'external_ref' => 'stripe:' . $invoiceId]);
        self::markPaid(DB::row('SELECT * FROM {p}donations WHERE id = ?', [$id]), $pi);
    }

    public function thanks(): void
    {
        $sid = (string)input('session_id', '');
        if (preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) {
            try {
                $s = Stripe::api('GET', 'checkout/sessions/' . rawurlencode($sid));
                if (($s['mode'] ?? '') === 'subscription' && ($rg = DB::row('SELECT * FROM {p}recurring_gifts WHERE stripe_session_id = ?', [$sid]))) {
                    if (!empty($s['subscription'])) {
                        DB::update('recurring_gifts', ['status' => 'active', 'stripe_subscription_id' => (string)$s['subscription']], 'id = ?', [$rg['id']]);
                    }
                } elseif (($s['payment_status'] ?? '') === 'paid' && ($d = DB::row('SELECT * FROM {p}donations WHERE stripe_session_id = ?', [$sid]))) {
                    self::markPaid($d, is_string($s['payment_intent'] ?? null) ? $s['payment_intent'] : null);
                }
                Notify::processQueue(3);
            } catch (Throwable) {
                // The webhook will confirm it.
            }
        }
        view('giving/thanks', ['title' => __('Thank you')], 'layout_public');
    }

    public function webhook(): void
    {
        $payload = (string)file_get_contents('php://input');
        $secret = (string)Crypto::decrypt((string)setting('stripe_webhook_secret', ''));
        if ($secret === '' || !Stripe::verifyWebhook($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
            http_response_code(400);
            exit('invalid signature');
        }
        $ev = json_decode($payload, true);
        $type = (string)($ev['type'] ?? '');
        $obj = $ev['data']['object'] ?? [];
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            if (($obj['mode'] ?? '') === 'subscription' && !empty($obj['metadata']['form_submission_id'])) {
                // Instalments for a form: link the subscription; the payments themselves arrive as invoice.paid.
                $fs = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND stripe_session_id = ?', [(int)$obj['metadata']['form_submission_id'], (string)($obj['id'] ?? '')]);
                if ($fs && !empty($obj['subscription'])) {
                    DB::update('form_submissions', ['stripe_subscription_id' => (string)$obj['subscription']], 'id = ?', [$fs['id']]);
                }
            } elseif (($obj['mode'] ?? '') === 'subscription') {
                $rg = DB::row('SELECT * FROM {p}recurring_gifts WHERE stripe_session_id = ?', [(string)($obj['id'] ?? '')]);
                if ($rg && !empty($obj['subscription'])) {
                    DB::update('recurring_gifts', ['status' => 'active', 'stripe_subscription_id' => (string)$obj['subscription']], 'id = ?', [$rg['id']]);
                    audit('giving.recurring_started', 'recurring_gift', (int)$rg['id']);
                }
            } elseif (!empty($obj['metadata']['form_submission_id'])) {
                $fs = DB::row('SELECT * FROM {p}form_submissions WHERE id = ? AND stripe_session_id = ?', [(int)$obj['metadata']['form_submission_id'], (string)($obj['id'] ?? '')]);
                if ($fs && ($obj['payment_status'] ?? '') === 'paid') {
                    Forms::markPaid($fs, '', is_string($obj['payment_intent'] ?? null) ? $obj['payment_intent'] : null);
                }
            } elseif (($obj['payment_status'] ?? '') === 'paid' && ($d = DB::row('SELECT * FROM {p}donations WHERE stripe_session_id = ?', [(string)($obj['id'] ?? '')]))) {
                self::markPaid($d, is_string($obj['payment_intent'] ?? null) ? $obj['payment_intent'] : null);
            }
        } elseif (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
            $sub = (string)($obj['subscription'] ?? ($obj['parent']['subscription_details']['subscription'] ?? ''));
            if (self::formInstalment($obj, $sub)) {
                Notify::processQueue(5);
                http_response_code(200);
                exit('ok');
            }
            $rg = $sub !== '' ? DB::row('SELECT * FROM {p}recurring_gifts WHERE stripe_subscription_id = ?', [$sub]) : null;
            if (!$rg && !empty($obj['subscription_details']['metadata']['recurring_id'])) {
                $rg = DB::row('SELECT * FROM {p}recurring_gifts WHERE id = ?', [(int)$obj['subscription_details']['metadata']['recurring_id']]);
                if ($rg && $sub !== '') {
                    DB::update('recurring_gifts', ['stripe_subscription_id' => $sub, 'status' => 'active'], 'id = ?', [$rg['id']]);
                }
            }
            if ($rg) {
                $pi = $obj['payment_intent'] ?? ($obj['payments']['data'][0]['payment']['payment_intent'] ?? null);
                self::recordInstalment($rg, (string)($obj['id'] ?? ''), (int)($obj['amount_paid'] ?? 0), is_string($pi) ? $pi : null);
                if ($rg['status'] !== 'active') {
                    DB::update('recurring_gifts', ['status' => 'active'], 'id = ?', [$rg['id']]);
                }
            }
        } elseif ($type === 'invoice.payment_failed') {
            $sub = (string)($obj['subscription'] ?? ($obj['parent']['subscription_details']['subscription'] ?? ''));
            DB::run("UPDATE {p}recurring_gifts SET status = 'past_due' WHERE stripe_subscription_id = ?", [$sub]);
            if ($sub !== '' && ($fs = DB::row("SELECT * FROM {p}form_submissions WHERE stripe_subscription_id = ? AND status IN ('part_paid','awaiting_payment')", [$sub]))) {
                DB::update('form_submissions', ['status' => 'overdue'], 'id = ?', [$fs['id']]);
                audit('form.payment_failed', 'form_submission', (int)$fs['id']);
            }
        } elseif ($type === 'customer.subscription.deleted') {
            $fs = DB::row('SELECT * FROM {p}form_submissions WHERE stripe_subscription_id = ?', [(string)($obj['id'] ?? '')]);
            if ($fs && (int)$fs['paid_cents'] < (int)$fs['amount_cents'] && $fs['status'] !== 'cancelled') {
                DB::update('form_submissions', ['staff_notes' => mb_substr(trim(__('Instalments stopped in Stripe before the last payment.') . ' ' . (string)$fs['staff_notes']), 0, 500)], 'id = ?', [$fs['id']]);
            }
            DB::run("UPDATE {p}recurring_gifts SET status = 'cancelled', cancelled_at = NOW() WHERE stripe_subscription_id = ?", [(string)($obj['id'] ?? '')]);
        } elseif ($type === 'charge.refunded') {
            // Form payments: matched by PaymentIntent (or charge), using Stripe's running refund total.
            $handled = (!empty($obj['payment_intent']) && Forms::refundFromStripe((string)$obj['payment_intent'], (int)($obj['amount_refunded'] ?? 0)))
                || (!empty($obj['id']) && Forms::refundFromStripe((string)$obj['id'], (int)($obj['amount_refunded'] ?? 0)));
            if (!$handled && !empty($obj['metadata']['form_submission_id']) && ($fs = DB::row('SELECT * FROM {p}form_submissions WHERE id = ?', [(int)$obj['metadata']['form_submission_id']]))) {
                Forms::cancel($fs, __('Refunded through Stripe')); // a payment from before version 4.2
            }
            if (!$handled) {
                Gifts::refundFromStripe($obj);
            }
        }
        Notify::processQueue(5);
        http_response_code(200);
        echo 'ok';
        exit;
    }

    /** invoice.paid for a form paid in instalments. Returns true if the invoice belonged to a form. */
    private static function formInstalment(array $inv, string $sub): bool
    {
        $meta = $inv['subscription_details']['metadata'] ?? ($inv['parent']['subscription_details']['metadata'] ?? []);
        $fs = $sub !== '' ? DB::row('SELECT * FROM {p}form_submissions WHERE stripe_subscription_id = ?', [$sub]) : null;
        if (!$fs && !empty($meta['form_submission_id'])) {
            $fs = DB::row('SELECT * FROM {p}form_submissions WHERE id = ?', [(int)$meta['form_submission_id']]);
            if ($fs && $sub !== '' && !$fs['stripe_subscription_id']) {
                DB::update('form_submissions', ['stripe_subscription_id' => $sub], 'id = ?', [$fs['id']]);
                $fs['stripe_subscription_id'] = $sub;
            }
        }
        if (!$fs) {
            return false;
        }
        $cents = (int)($inv['amount_paid'] ?? 0);
        $pi = $inv['payment_intent'] ?? ($inv['payments']['data'][0]['payment']['payment_intent'] ?? ($inv['charge'] ?? null));
        if ($cents > 0) {
            Forms::recordPayment($fs, (string)($inv['id'] ?? ''), $cents, 'card', is_string($pi) ? $pi : null);
        }
        return true;
    }

    public function cancelRecurring(string $id): void
    {
        $rg = DB::row('SELECT * FROM {p}recurring_gifts WHERE id = ?', [(int)$id]) ?? abort(404);
        if ((int)$rg['person_id'] !== person_id() && !can('giving.manage')) {
            abort(403);
        }
        try {
            if ($rg['stripe_subscription_id'] && !Demo::on()) {
                Stripe::api('DELETE', 'subscriptions/' . rawurlencode($rg['stripe_subscription_id']));
            }
            DB::update('recurring_gifts', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')], 'id = ?', [$rg['id']]);
            audit('giving.recurring_cancelled', 'recurring_gift', (int)$rg['id']);
            flash('success', __('Your regular gift has been cancelled. Thank you for your generosity.'));
        } catch (Throwable $e) {
            flash('error', __('We could not cancel this with Stripe just now. Please try again or contact the church office.'));
        }
        back('/me/giving');
    }

    // Statements --------------------------------------------------------------
    public static function fyBounds(?int $fy = null): array
    {
        $fy ??= (int)date('n') >= 7 ? (int)date('Y') - 1 : (int)date('Y') - 2;
        return [$fy, "$fy-07-01", ($fy + 1) . '-06-30'];
    }

    public static function statementData(int $personId, int $fy): array
    {
        [, $from, $to] = self::fyBounds($fy);
        $rows = DB::all("SELECT d.*, f.name AS fund FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.person_id = ? AND d.status = 'paid' AND COALESCE(d.received_on, DATE(d.created_at)) BETWEEN ? AND ? ORDER BY COALESCE(d.received_on, d.created_at)", [$personId, $from, $to]);
        return ['person' => DB::row('SELECT * FROM {p}people WHERE id = ?', [$personId]), 'rows' => $rows, 'total' => array_sum(array_map(fn($r) => (int)$r['amount_cents'], $rows)), 'fy' => $fy, 'from' => $from, 'to' => $to];
    }

    public function statements(): void
    {
        [$fy, $from, $to] = self::fyBounds(input_int('fy'));
        $donors = DB::all("SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.email, SUM(d.amount_cents) AS total, COUNT(*) AS gifts FROM {p}donations d JOIN {p}people p ON p.id = d.person_id WHERE d.status = 'paid' AND COALESCE(d.received_on, DATE(d.created_at)) BETWEEN ? AND ? GROUP BY p.id, p.first_name, p.last_name, p.preferred_name, p.email ORDER BY p.last_name", [$from, $to]);
        view('giving/statements', ['title' => __('Giving statements'), 'donors' => $donors, 'fy' => $fy]);
    }

    public function statement(string $personId): void
    {
        $pid = (int)$personId;
        if ($pid !== person_id() && !can('giving.view')) {
            abort(403);
        }
        [$fy] = self::fyBounds(input_int('fy'));
        view('giving/statement', ['title' => __('Giving statement')] + self::statementData($pid, $fy), 'layout_print');
    }

    public function emailStatements(): void
    {
        [$fy, $from, $to] = self::fyBounds(input_int('fy'));
        $n = 0;
        foreach (DB::col("SELECT DISTINCT person_id FROM {p}donations WHERE person_id IS NOT NULL AND status = 'paid' AND COALESCE(received_on, DATE(created_at)) BETWEEN ? AND ?", [$from, $to]) as $pid) {
            $d = self::statementData((int)$pid, $fy);
            ob_start();
            extract($d);
            require APP_DIR . '/views/giving/statement_body.php';
            $html = (string)ob_get_clean();
            if (Notify::emailPerson((int)$pid, __('Your giving statement %d–%d', $fy, $fy + 1), $html, true)) {
                $n++;
            }
        }
        Notify::processQueue(20);
        audit('giving.statements_emailed', null, null, ['fy' => $fy, 'count' => $n]);
        flash('success', __('%d statements queued. The rest will be sent by the scheduled task.', $n));
        redirect('/giving/statements', ['fy' => $fy]);
    }

    // Bank import ---------------------------------------------------------------
    public function importForm(): void
    {
        view('giving/import', ['title' => __('Import bank deposits'), 'rows' => $_SESSION['bank_import'] ?? null, 'funds' => array_column(DB::all('SELECT id, name FROM {p}funds WHERE active = 1'), 'name', 'id'), 'people' => people_options()]);
    }

    /** Find who a bank line belongs to: giving number (e.g. G123) or a donor's full name in the description. */
    public static function matchPerson(string $desc): ?int
    {
        if (preg_match('/\bG[- ]?(\d{1,7})\b/i', $desc, $m)) {
            $pid = (int)DB::val('SELECT id FROM {p}people WHERE giving_number = ? OR (giving_number IS NULL AND id = ?) LIMIT 1', ['G' . $m[1], (int)$m[1]]);
            if ($pid) {
                return $pid;
            }
        }
        $up = mb_strtoupper($desc);
        foreach (DB::all('SELECT id, first_name, last_name FROM {p}people WHERE archived_at IS NULL') as $p) {
            if (mb_strlen($p['last_name']) > 2 && str_contains($up, mb_strtoupper($p['first_name'] . ' ' . $p['last_name']))) {
                return (int)$p['id'];
            }
        }
        return null;
    }

    public function importUpload(): void
    {
        $f = $_FILES['csv'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
            flash('error', __('Please choose a CSV file under 5 MB.'));
            redirect('/giving/import');
        }
        $fh = fopen($f['tmp_name'], 'r');
        $first = fgetcsv($fh) ?: [];
        $norm = array_map(fn($h) => preg_replace('/[^a-z]/', '', strtolower((string)$h)), $first);
        $find = fn(array $names) => array_values(array_filter(array_keys($norm), fn($i) => in_array($norm[$i], $names, true)))[0] ?? null;
        $iDate = $find(['date', 'transactiondate', 'posteddate', 'valuedate']);
        $iAmt = $find(['amount', 'credit', 'creditamount', 'deposit', 'deposits']);
        $iDesc = $find(['description', 'narrative', 'details', 'transactiondetails', 'memo', 'particulars']);
        $iRef = $find(['reference', 'ref', 'transactionid', 'id']);
        $headerless = $iDate === null || $iAmt === null;
        if ($headerless) {
            rewind($fh);
            [$iDate, $iAmt, $iDesc, $iRef] = [0, 1, 2, null];
        }
        $rows = [];
        while (($r = fgetcsv($fh)) !== false && count($rows) < 2000) {
            $amt = (float)str_replace([',', '$', ' '], '', (string)($r[$iAmt] ?? '0'));
            $ts = strtotime(str_replace('/', '-', trim((string)($r[$iDate] ?? ''))));
            if ($amt <= 0 || !$ts) {
                continue;
            }
            $desc = trim((string)($r[$iDesc] ?? ''));
            $ref = 'bank:' . substr(hash('sha256', date('Y-m-d', $ts) . '|' . $amt . '|' . $desc . '|' . ($iRef !== null ? ($r[$iRef] ?? '') : '')), 0, 40);
            $rows[] = ['date' => date('Y-m-d', $ts), 'cents' => (int)round($amt * 100), 'desc' => mb_substr($desc, 0, 200), 'ref' => $ref, 'person_id' => self::matchPerson($desc), 'dupe' => (bool)DB::val('SELECT 1 FROM {p}donations WHERE external_ref = ?', [$ref])];
        }
        fclose($fh);
        $_SESSION['bank_import'] = $rows;
        flash($rows ? 'success' : 'error', $rows ? __('%d deposits found. Check the matches, then import.', count($rows)) : __('No deposits found. The file needs Date, Amount and Description columns.'));
        redirect('/giving/import');
    }

    public function importConfirm(): void
    {
        $rows = $_SESSION['bank_import'] ?? [];
        $fund = input_int('fund_id');
        $n = 0;
        foreach ($rows as $i => $r) {
            if (empty($_POST['take'][$i]) || DB::val('SELECT 1 FROM {p}donations WHERE external_ref = ?', [$r['ref']])) {
                continue;
            }
            $pid = (int)($_POST['person'][$i] ?? 0) ?: null;
            $id = DB::insert('donations', ['person_id' => $pid, 'fund_id' => $fund, 'donor_name' => $pid ? null : mb_substr($r['desc'], 0, 160), 'amount_cents' => $r['cents'], 'currency' => setting('currency', 'AUD'), 'method' => 'eft', 'status' => 'paid', 'received_on' => $r['date'], 'external_ref' => $r['ref'], 'notes' => mb_substr($r['desc'], 0, 255)]);
            DB::update('donations', ['receipt_no' => self::receiptNo($id)], 'id = ?', [$id]);
            $n++;
        }
        unset($_SESSION['bank_import']);
        audit('giving.bank_imported', null, null, ['count' => $n]);
        flash('success', __('%d deposits imported as donations.', $n));
        redirect('/giving');
    }
}
