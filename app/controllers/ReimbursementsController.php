<?php
declare(strict_types=1);

/**
 * Expense reimbursements: submit with a receipt → approved by the ministry leader (or an approver)
 * → paid by the treasurer, with CSV and ABA (Australian bank direct-entry) file export.
 * Bank details are encrypted; nobody can approve their own claim.
 */
final class ReimbursementsController
{
    public static function categories(): array
    {
        return ['Ministry supplies' => __('Ministry supplies'), 'Food & hospitality' => __('Food & hospitality'), 'Travel' => __('Travel'), 'Equipment' => __('Equipment'), 'Printing' => __('Printing'), 'Other' => __('Other')];
    }

    private function canApprove(array $r): bool
    {
        if ((int)$r['person_id'] === person_id()) {
            return false;
        }
        return can('reimbursements.approve') || ($r['group_id'] && leads_group((int)$r['group_id']));
    }

    private function canSee(array $r): bool
    {
        return (int)$r['person_id'] === person_id() || $this->canApprove($r) || can('reimbursements.pay');
    }

    private function find(int|string $id): array
    {
        $r = DB::row('SELECT * FROM {p}reimbursements WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!$this->canSee($r)) {
            abort(403);
        }
        return $r;
    }

    public function index(): void
    {
        $pid = person_id();
        $mine = $pid ? DB::all('SELECT r.*, g.name AS group_name FROM {p}reimbursements r LEFT JOIN {p}groups g ON g.id = r.group_id WHERE r.person_id = ? ORDER BY r.id DESC LIMIT 50', [$pid]) : [];
        $toApprove = [];
        $led = led_group_ids();
        if (can('reimbursements.approve') || $led) {
            $where = can('reimbursements.approve') ? '1=1' : 'r.group_id IN (' . DB::in($led) . ')';
            $toApprove = DB::all("SELECT r.*, g.name AS group_name, p.first_name, p.last_name, p.preferred_name FROM {p}reimbursements r LEFT JOIN {p}groups g ON g.id = r.group_id JOIN {p}people p ON p.id = r.person_id WHERE r.status = 'submitted' AND r.person_id <> ? AND $where ORDER BY r.created_at", array_merge([$pid ?? 0], can('reimbursements.approve') ? [] : $led));
        }
        $toPay = can('reimbursements.pay') ? DB::all("SELECT r.*, g.name AS group_name, p.first_name, p.last_name, p.preferred_name FROM {p}reimbursements r LEFT JOIN {p}groups g ON g.id = r.group_id JOIN {p}people p ON p.id = r.person_id WHERE r.status = 'approved' ORDER BY r.approved_at") : [];
        $last = $pid ? DB::row('SELECT payee_name, bsb_enc, account_enc FROM {p}reimbursements WHERE person_id = ? AND bsb_enc IS NOT NULL ORDER BY id DESC LIMIT 1', [$pid]) : null;
        $groups = array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name'), 'name', 'id');
        view('reimbursements/index', ['title' => __('Reimbursements'), 'mine' => $mine, 'toApprove' => $toApprove, 'toPay' => $toPay, 'hasSaved' => (bool)$last, 'groups' => $groups]);
    }

    public function store(): void
    {
        $pid = person_id() ?? abort(403);
        $amount = (int)round((float)str_replace([',', '$'], '', (string)input('amount', '0')) * 100);
        $title = trim((string)input('title', ''));
        if ($amount <= 0 || $title === '') {
            flash('error', __('Enter what the expense was for and the amount.'));
            redirect('/reimbursements');
        }
        $fileId = null;
        $f = $_FILES['receipt'] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK && !Demo::on()) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $ext = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/heic' => 'heic'][$mime] ?? null;
            if ($ext && $f['size'] <= 10 * 1024 * 1024) {
                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/uploads/' . $stored)) {
                    $fileId = DB::insert('files', ['original_name' => mb_substr(basename((string)$f['name']), 0, 255), 'stored_name' => $stored, 'mime' => $mime, 'size' => (int)$f['size'], 'uploaded_by' => Auth::id()]);
                }
            }
        }
        if (!$fileId && !Demo::on()) {
            flash('error', __('Please attach a photo or PDF of the receipt (JPG, PNG, HEIC or PDF, up to 10 MB).'));
            redirect('/reimbursements');
        }
        $payee = trim((string)input('payee_name', ''));
        $bsb = preg_replace('/\D/', '', (string)input('bsb', ''));
        $acct = preg_replace('/\D/', '', (string)input('account', ''));
        if (input_bool('use_saved')) {
            $last = DB::row('SELECT payee_name, bsb_enc, account_enc FROM {p}reimbursements WHERE person_id = ? AND bsb_enc IS NOT NULL ORDER BY id DESC LIMIT 1', [$pid]);
            [$payee, $bsbEnc, $acctEnc] = $last ? [$last['payee_name'], $last['bsb_enc'], $last['account_enc']] : ['', null, null];
        } else {
            if (strlen($bsb) !== 6 || strlen($acct) < 5 || strlen($acct) > 9 || $payee === '') {
                flash('error', __('Enter the account name, a 6-digit BSB and an account number (5–9 digits).'));
                redirect('/reimbursements');
            }
            [$bsbEnc, $acctEnc] = [Crypto::encrypt($bsb), Crypto::encrypt($acct)];
        }
        $cat = isset(self::categories()[input('category')]) ? input('category') : 'Other';
        $gid = input_int('group_id');
        $id = DB::insert('reimbursements', ['person_id' => $pid, 'title' => mb_substr($title, 0, 200), 'description' => input('description') ?: null, 'amount_cents' => $amount, 'expense_date' => input_date('expense_date') ?? date('Y-m-d'), 'category' => $cat, 'group_id' => $gid, 'receipt_file_id' => $fileId, 'payee_name' => mb_substr($payee, 0, 160), 'bsb_enc' => $bsbEnc, 'account_enc' => $acctEnc]);
        $approvers = users_with_permission('reimbursements.approve');
        if ($gid) {
            foreach (DB::col("SELECT person_id FROM {p}group_members WHERE group_id = ? AND member_role = 'leader'", [$gid]) as $lp) {
                if ($uid = user_id_for_person((int)$lp)) {
                    $approvers[] = $uid;
                }
            }
        }
        Notify::users(array_diff(array_unique($approvers), [Auth::id()]), __('Reimbursement to approve: %s (%s)', $title, fmt_money($amount)), '<p>' . e(__('A reimbursement request is waiting for your approval.')) . '</p>', url('/reimbursements'));
        audit('reimbursement.submitted', 'reimbursement', $id, ['amount' => $amount]);
        flash('success', __('Submitted. You will be notified when it is approved and paid.'));
        redirect('/reimbursements');
    }

    public function decide(string $id): void
    {
        $r = $this->find($id);
        if ($r['status'] !== 'submitted' || !$this->canApprove($r)) {
            abort(403, __('You cannot approve this claim (you may not approve your own).'));
        }
        $ok = input('decision') === 'approve';
        DB::update('reimbursements', ['status' => $ok ? 'approved' : 'rejected', 'approver_id' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s'), 'approver_note' => mb_substr((string)input('note', ''), 0, 255) ?: null], 'id = ?', [$r['id']]);
        Notify::person((int)$r['person_id'], $ok ? __('Reimbursement approved: %s', $r['title']) : __('Reimbursement not approved: %s', $r['title']), '<p>' . e($ok ? __('Your claim for %s has been approved and will be paid by the treasurer.', fmt_money((int)$r['amount_cents'])) : __('Your claim was not approved. %s', (string)input('note', ''))) . '</p>', null, url('/reimbursements'));
        if ($ok) {
            Notify::users(users_with_permission('reimbursements.pay'), __('Reimbursement ready to pay: %s', fmt_money((int)$r['amount_cents'])), '<p>' . e(__('An approved reimbursement is ready for payment.')) . '</p>', url('/reimbursements'));
        }
        audit($ok ? 'reimbursement.approved' : 'reimbursement.rejected', 'reimbursement', (int)$r['id']);
        redirect('/reimbursements');
    }

    public function pay(): void
    {
        $ids = input_ids('ids');
        $ref = mb_substr((string)input('payment_ref', ''), 0, 60) ?: 'Paid ' . date('j M Y');
        foreach ($ids as $id) {
            $r = DB::row("SELECT * FROM {p}reimbursements WHERE id = ? AND status = 'approved'", [$id]);
            if (!$r) {
                continue;
            }
            DB::update('reimbursements', ['status' => 'paid', 'paid_by' => Auth::id(), 'paid_at' => date('Y-m-d H:i:s'), 'payment_ref' => $ref], 'id = ?', [$id]);
            Notify::person((int)$r['person_id'], __('Reimbursement paid: %s', $r['title']), '<p>' . e(__('%s has been paid to your account (reference %s).', fmt_money((int)$r['amount_cents']), $ref)) . '</p>');
            audit('reimbursement.paid', 'reimbursement', (int)$id);
        }
        flash('success', __('%d claim(s) marked as paid.', count($ids)));
        redirect('/reimbursements');
    }

    public function exportSelected(): void
    {
        $ids = input_ids('ids');
        $format = (string)input('format', 'csv');
        $rows = $ids ? DB::all("SELECT r.*, p.first_name, p.last_name FROM {p}reimbursements r JOIN {p}people p ON p.id = r.person_id WHERE r.id IN (" . DB::in($ids) . ") AND r.status IN ('approved','paid')", $ids) : [];
        if (!$rows) {
            flash('error', __('Choose approved claims to export.'));
            redirect('/reimbursements');
        }
        audit('reimbursement.exported', null, null, ['format' => $format, 'ids' => $ids]);
        if ($format === 'aba') {
            $missing = array_filter(['aba_bsb', 'aba_account', 'aba_account_name', 'aba_bank', 'aba_user_name', 'aba_apca_id'], fn($k) => setting($k, '') === '');
            if ($missing) {
                flash('error', __('Add your church bank details in Settings → Integrations before creating a bank file.'));
                redirect('/reimbursements');
            }
            header('Content-Type: text/plain; charset=us-ascii');
            header('Content-Disposition: attachment; filename="reimbursements-' . date('Ymd') . '.aba"');
            echo self::aba($rows);
            exit;
        }
        csv_download('reimbursements-' . date('Y-m-d') . '.csv', ['*ContactName', '*InvoiceNumber', '*InvoiceDate', '*DueDate', 'Description', '*Quantity', '*UnitAmount', '*AccountCode', '*TaxType', 'TrackingName1', 'TrackingOption1'], array_map(fn($r) => [$r['payee_name'] ?: trim($r['first_name'] . ' ' . $r['last_name']), 'RB-' . $r['id'], date('d/m/Y', strtotime($r['expense_date'])), date('d/m/Y'), $r['title'] . ' (' . $r['category'] . ')', 1, number_format($r['amount_cents'] / 100, 2, '.', ''), (string)(setting('xero_expense_account') ?: '400'), 'BAS Excluded', 'Ministry', (string)DB::val('SELECT name FROM {p}groups WHERE id = ?', [$r['group_id']])], $rows));
    }

    /** Build an ABA (Cemtex / Australian Direct Entry) credit file. */
    public static function aba(array $rows): string
    {
        $alnum = fn(string $s, int $len) => str_pad(substr(strtoupper(preg_replace('/[^A-Za-z0-9 &\-\/.,]/', '', $s)), 0, $len), $len);
        $num = fn(int $n, int $len) => str_pad((string)$n, $len, '0', STR_PAD_LEFT);
        $bsbFmt = fn(string $b) => substr($b, 0, 3) . '-' . substr($b, 3, 3);
        $acctFmt = fn(string $a) => str_pad(substr($a, -9), 9, ' ', STR_PAD_LEFT);
        $churchBsb = preg_replace('/\D/', '', (string)setting('aba_bsb'));
        $churchAcct = preg_replace('/\D/', '', (string)setting('aba_account'));
        $remitter = $alnum((string)setting('aba_account_name'), 16);
        $lines = [];
        $lines[] = '0' . str_repeat(' ', 17) . '01' . $alnum((string)setting('aba_bank'), 3) . str_repeat(' ', 7) . $alnum((string)setting('aba_user_name'), 26) . $num((int)setting('aba_apca_id'), 6) . $alnum('REIMBURSE', 12) . date('dmy') . str_repeat(' ', 40);
        $total = 0;
        foreach ($rows as $r) {
            $bsb = (string)Crypto::decrypt($r['bsb_enc']);
            $acct = (string)Crypto::decrypt($r['account_enc']);
            $amt = (int)$r['amount_cents'];
            $total += $amt;
            $lines[] = '1' . $bsbFmt($bsb) . $acctFmt($acct) . ' ' . '53' . $num($amt, 10) . $alnum((string)($r['payee_name'] ?: $r['first_name'] . ' ' . $r['last_name']), 32) . $alnum('REIMB ' . $r['id'], 18) . $bsbFmt($churchBsb) . $acctFmt($churchAcct) . $remitter . '00000000';
        }
        $count = count($rows);
        if (setting('aba_balance', '0') === '1') {
            $lines[] = '1' . $bsbFmt($churchBsb) . $acctFmt($churchAcct) . ' ' . '13' . $num($total, 10) . $alnum((string)setting('aba_account_name'), 32) . $alnum('REIMBURSEMENTS', 18) . $bsbFmt($churchBsb) . $acctFmt($churchAcct) . $remitter . '00000000';
            $count++;
            $lines[] = '7' . '999-999' . str_repeat(' ', 12) . $num(0, 10) . $num($total, 10) . $num($total, 10) . str_repeat(' ', 24) . $num($count, 6) . str_repeat(' ', 40);
        } else {
            $lines[] = '7' . '999-999' . str_repeat(' ', 12) . $num($total, 10) . $num($total, 10) . $num(0, 10) . str_repeat(' ', 24) . $num($count, 6) . str_repeat(' ', 40);
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    public function receipt(string $id): void
    {
        $r = $this->find($id);
        $f = DB::row('SELECT * FROM {p}files WHERE id = ?', [$r['receipt_file_id']]) ?? abort(404);
        FilesController::send($f);
    }
}
