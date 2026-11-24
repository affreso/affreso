<?php
declare(strict_types=1);

/** Self check-in kiosks: staff set-up pages and the parent-facing kiosk screens. */
final class KioskController
{
    // ------------------------------------------------------------------ staff: manage kiosks

    public function index(): void
    {
        $kiosks = DB::all('SELECT k.*, c.name AS campus FROM {p}kiosks k LEFT JOIN {p}campuses c ON c.id = k.campus_id ORDER BY k.active DESC, k.name');
        $printers = [];
        if (Labels::printNodeOn()) {
            try {
                $printers = Labels::printers();
            } catch (Throwable $e) {
                $printers = [];
            }
        }
        view('kiosk/manage', ['title' => __('Self check-in kiosks'), 'kiosks' => $kiosks, 'pairing' => $_SESSION['kiosk_pairing'] ?? null, 'printers' => $printers]);
        unset($_SESSION['kiosk_pairing']);
    }

    private function pin(): ?string
    {
        $pin = preg_replace('/\D/', '', (string)input('exit_pin', ''));
        return strlen($pin) >= 4 && strlen($pin) <= 8 ? $pin : null;
    }

    public function store(): void
    {
        $name = trim(mb_substr((string)input('name', ''), 0, 120));
        $pin = $this->pin();
        if ($name === '' || !$pin) {
            flash('error', __('Give the kiosk a name and a 4–8 digit exit PIN.'));
            redirect('/checkin/kiosks');
        }
        $id = DB::insert('kiosks', ['name' => $name, 'campus_id' => Campus::fromInput(), 'exit_pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'print_labels' => input_bool('print_labels'), 'created_by' => Auth::id()]);
        audit('kiosk.created', 'kiosk', $id);
        $this->issuePairing($id);
    }

    public function pairCode(string $id): void
    {
        $k = DB::row('SELECT * FROM {p}kiosks WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->issuePairing((int)$k['id']);
    }

    private function issuePairing(int $id): never
    {
        $code = Kiosk::pairingCode();
        DB::update('kiosks', ['pair_hash' => hash('sha256', $code), 'pair_expires' => date('Y-m-d H:i:s', time() + 1800), 'active' => 1], 'id = ?', [$id]);
        audit('kiosk.pairing_code', 'kiosk', $id);
        $_SESSION['kiosk_pairing'] = ['id' => $id, 'code' => substr($code, 0, 4) . '-' . substr($code, 4)];
        redirect('/checkin/kiosks');
    }

    public function update(string $id): void
    {
        $k = DB::row('SELECT * FROM {p}kiosks WHERE id = ?', [(int)$id]) ?? abort(404);
        $d = ['name' => trim(mb_substr((string)input('name', $k['name']), 0, 120)) ?: $k['name'], 'campus_id' => Campus::fromInput(), 'print_labels' => input_bool('print_labels'),
            'printer_id' => preg_replace('/\D/', '', (string)input('printer_id', '')) ?: null];
        if ($pin = $this->pin()) {
            $d['exit_pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
        }
        DB::update('kiosks', $d, 'id = ?', [$k['id']]);
        audit('kiosk.updated', 'kiosk', (int)$k['id']);
        flash('success', __('Kiosk saved.'));
        redirect('/checkin/kiosks');
    }

    public function revoke(string $id): void
    {
        $k = DB::row('SELECT * FROM {p}kiosks WHERE id = ?', [(int)$id]) ?? abort(404);
        DB::update('kiosks', ['token_hash' => null, 'pair_hash' => null, 'pair_expires' => null, 'active' => 0], 'id = ?', [$k['id']]);
        audit('kiosk.revoked', 'kiosk', (int)$k['id']);
        flash('success', __('%s has been switched off. The device will need a new pairing code.', $k['name']));
        redirect('/checkin/kiosks');
    }

    // ------------------------------------------------------------------ the kiosk itself (public routes)

    private function kiosk(): array
    {
        $k = Kiosk::current();
        if (!$k) {
            redirect('/kiosk/pair');
        }
        return $k;
    }

    private function screen(string $view, array $data): void
    {
        view('kiosk/' . $view, $data + ['title' => __('Check-in')], 'layout_kiosk');
    }

    public function pairForm(): void
    {
        if (Kiosk::current()) {
            redirect('/kiosk');
        }
        $this->screen('pair', []);
    }

    public function pair(): void
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        if ((int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email = 'kiosk:pair' AND success = 0 AND created_at > ?", [client_ip(), $since]) >= 10) {
            flash('error', __('Too many attempts. Please wait 15 minutes.'));
            redirect('/kiosk/pair');
        }
        $k = Kiosk::pair((string)input('code', ''));
        DB::insert('login_attempts', ['email' => 'kiosk:pair', 'ip' => client_ip(), 'success' => $k ? 1 : 0]);
        if (!$k) {
            flash('error', __('That pairing code is not valid or has expired. Ask a staff member for a new one.'));
            redirect('/kiosk/pair');
        }
        audit('kiosk.paired', 'kiosk', (int)$k['id'], ['ip' => client_ip()]);
        redirect('/kiosk');
    }

    public function start(): void
    {
        $k = $this->kiosk();
        unset($_SESSION['kiosk_family']);
        $this->screen('start', ['k' => $k, 's' => Kiosk::session($k)]);
    }

    public function find(): void
    {
        $k = $this->kiosk();
        $s = Kiosk::session($k) ?? redirect('/kiosk');
        if (Kiosk::throttled($k)) {
            flash('error', __('Too many searches. Please ask the welcome desk for help.'));
            redirect('/kiosk');
        }
        $hids = Kiosk::findHouseholds((string)input('phone', ''));
        Kiosk::logLookup($k, (bool)$hids);
        $hids = array_values(array_filter($hids, fn($h) => DB::val("SELECT 1 FROM {p}people WHERE household_id = ? AND household_role = 'child' AND archived_at IS NULL", [$h])));
        if (!$hids) {
            flash('error', __('We could not find children linked to that number. Please see the welcome desk.'));
            redirect('/kiosk');
        }
        $_SESSION['kiosk_family'] = ['hids' => $hids, 'at' => time(), 'kiosk' => (int)$k['id']];
        redirect('/kiosk/family');
    }

    /** The household(s) found in the last two minutes on this kiosk. */
    private function family(array $k): array
    {
        $f = $_SESSION['kiosk_family'] ?? null;
        if (!$f || $f['kiosk'] !== (int)$k['id'] || $f['at'] < time() - 300) {
            unset($_SESSION['kiosk_family']);
            flash('warning', __('That took a little long, so we started again for privacy.'));
            redirect('/kiosk');
        }
        return $f['hids'];
    }

    public function familyForm(): void
    {
        $k = $this->kiosk();
        $s = Kiosk::session($k) ?? redirect('/kiosk');
        $hids = $this->family($k);
        $kids = DB::all("SELECT * FROM {p}people WHERE household_id IN (" . DB::in($hids) . ") AND household_role = 'child' AND archived_at IS NULL ORDER BY birthdate", $hids);
        $adults = DB::all("SELECT * FROM {p}people WHERE household_id IN (" . DB::in($hids) . ") AND household_role <> 'child' AND archived_at IS NULL ORDER BY first_name", $hids);
        $classes = array_column(DB::all('SELECT id, name FROM {p}checkin_classes WHERE active = 1'), 'name', 'id');
        foreach ($kids as &$c) {
            $c['short'] = Kiosk::shortName($c);
            $c['in'] = (bool)DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $c['id']]);
            $c['pre'] = (bool)DB::val('SELECT 1 FROM {p}prechecks WHERE session_id = ? AND child_id = ?', [$s['id'], $c['id']]);
            $cid = CheckinController::suggestClass($c['birthdate'], $s['campus_id'] ? (int)$s['campus_id'] : null);
            $c['class'] = $cid ? ($classes[$cid] ?? '') : '';
        }
        unset($c);
        $this->screen('family', ['k' => $k, 's' => $s, 'kids' => $kids, 'adults' => array_map(fn($a) => ['id' => $a['id'], 'short' => Kiosk::shortName($a)], $adults)]);
    }

    public function checkIn(): void
    {
        $k = $this->kiosk();
        $s = Kiosk::session($k) ?? redirect('/kiosk');
        $hids = $this->family($k);
        $kids = input_ids('child_ids');
        if (!$kids) {
            flash('error', __('Tap the children you are checking in.'));
            redirect('/kiosk/family');
        }
        $adult = input_int('adult_id');
        $adultRow = $adult ? DB::row('SELECT * FROM {p}people WHERE id = ? AND household_id IN (' . DB::in($hids) . ')', array_merge([$adult], $hids)) : null;
        $code = CheckinController::newCode((int)$s['id']);
        $ids = [];
        foreach ($kids as $kid) {
            $child = DB::row("SELECT * FROM {p}people WHERE id = ? AND household_role = 'child' AND archived_at IS NULL AND household_id IN (" . DB::in($hids) . ')', array_merge([$kid], $hids));
            if (!$child || DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $kid])) {
                continue;
            }
            $class = CheckinController::suggestClass($child['birthdate'], $s['campus_id'] ? (int)$s['campus_id'] : null);
            $pre = DB::row('SELECT * FROM {p}prechecks WHERE session_id = ? AND child_id = ?', [$s['id'], $kid]);
            $ids[] = DB::insert('checkins', ['session_id' => $s['id'], 'child_id' => $kid, 'class_id' => $pre['class_id'] ?? $class, 'security_code' => $code, 'checked_in_by' => null, 'kiosk_id' => $k['id'],
                'dropped_off_by' => $adultRow ? person_name($adultRow) : ($pre['dropped_off_by'] ?? null), 'prechecked' => $pre ? 1 : 0]);
            if ($pre) {
                DB::run('DELETE FROM {p}prechecks WHERE id = ?', [$pre['id']]);
            }
        }
        unset($_SESSION['kiosk_family']);
        audit('checkin.kiosk_in', 'checkin_session', (int)$s['id'], ['kiosk' => (int)$k['id'], 'checkins' => $ids]);
        if (!$ids) {
            flash('warning', __('Those children are already checked in.'));
            redirect('/kiosk');
        }
        $_SESSION['kiosk_done'] = ['ids' => $ids, 'at' => time()];
        redirect('/kiosk/done');
    }

    public function done(): void
    {
        $k = $this->kiosk();
        $d = $_SESSION['kiosk_done'] ?? null;
        if (!$d || $d['at'] < time() - 600) {
            redirect('/kiosk');
        }
        $ids = $d['ids'];
        $rows = DB::all('SELECT c.*, p.first_name, p.last_name, p.preferred_name, k.name AS class, s.session_date FROM {p}checkins c JOIN {p}people p ON p.id = c.child_id LEFT JOIN {p}checkin_classes k ON k.id = c.class_id JOIN {p}checkin_sessions s ON s.id = c.session_id WHERE c.id IN (' . DB::in($ids) . ')', $ids);
        foreach ($rows as &$r) {
            $r['alert'] = CheckinController::hasAlert((int)$r['child_id']);
            $r['short'] = Kiosk::shortName($r);
        }
        unset($r);
        $printer = (string)($k['printer_id'] ?: setting('label_printer_id', ''));
        $silent = false;
        if ((int)$k['print_labels'] && Labels::printNodeOn() && $printer !== '') {
            if (empty($d['printed'])) {
                $err = Labels::printCheckins($rows, $printer, false);
                $_SESSION['kiosk_done']['printed'] = $err === null ? 'ok' : 'failed';
                $d['printed'] = $_SESSION['kiosk_done']['printed'];
            }
            $silent = $d['printed'] === 'ok';
        }
        $this->screen('done', ['k' => $k, 'rows' => $rows, 'silent' => $silent]);
    }

    public function exitForm(): void
    {
        $this->screen('exit', ['k' => $this->kiosk()]);
    }

    public function exit(): void
    {
        $k = $this->kiosk();
        if ((int)DB::val('SELECT COUNT(*) FROM {p}login_attempts WHERE email = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)', ['kiosk-exit:' . $k['id']]) >= 5) {
            flash('error', __('Too many attempts. Please wait 15 minutes.'));
            redirect('/kiosk/exit');
        }
        if (!password_verify(preg_replace('/\D/', '', (string)input('pin', '')), (string)$k['exit_pin_hash'])) {
            DB::insert('login_attempts', ['email' => 'kiosk-exit:' . $k['id'], 'ip' => client_ip(), 'success' => 0]);
            audit('kiosk.exit_failed', 'kiosk', (int)$k['id']);
            flash('error', __('Incorrect PIN.'));
            redirect('/kiosk/exit');
        }
        DB::update('kiosks', ['token_hash' => null], 'id = ?', [$k['id']]);
        Kiosk::setCookie('');
        audit('kiosk.exited', 'kiosk', (int)$k['id']);
        flash('success', __('Kiosk mode ended on this device.'));
        redirect('/login');
    }
}
