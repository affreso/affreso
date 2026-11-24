<?php
declare(strict_types=1);

/**
 * Children's ministry check-in and check-out: matching security codes, printed labels,
 * authorised collectors, allergy/medical alerts (encrypted, restricted) and leader ratios.
 */
final class CheckinController
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private function session(int|string $id): array
    {
        return DB::row('SELECT * FROM {p}checkin_sessions WHERE id = ?', [(int)$id]) ?? abort(404);
    }

    public static function careInfo(int $childId): array
    {
        $p = DB::row('SELECT * FROM {p}child_profiles WHERE person_id = ?', [$childId]);
        return [
            'allergies' => $p ? (string)Crypto::decrypt($p['allergies_enc']) : '',
            'medical' => $p ? (string)Crypto::decrypt($p['medical_enc']) : '',
            'needs' => $p ? (string)Crypto::decrypt($p['needs_enc']) : '',
            'emergency' => $p ? (string)Crypto::decrypt($p['emergency_enc']) : '',
            'photo_permission' => $p ? (int)$p['photo_permission'] : 0,
        ];
    }

    public static function hasAlert(int $childId): bool
    {
        $c = self::careInfo($childId);
        return trim($c['allergies'] . $c['medical'] . $c['needs']) !== '';
    }

    /** Best class for a child's age (for the session's campus). */
    public static function suggestClass(?string $birthdate, ?int $campus): ?int
    {
        $age = age_on($birthdate);
        foreach (DB::all('SELECT * FROM {p}checkin_classes WHERE active = 1 AND (campus_id IS NULL OR campus_id = ? OR ? IS NULL) ORDER BY min_age', [$campus, $campus]) as $c) {
            if ($age !== null && ($c['min_age'] === null || $age >= (int)$c['min_age']) && ($c['max_age'] === null || $age <= (int)$c['max_age'])) {
                return (int)$c['id'];
            }
        }
        return null;
    }

    private function classes(?int $campus = null): array
    {
        return array_column(DB::all('SELECT id, CONCAT(name, IF(min_age IS NULL, "", CONCAT(" (", min_age, "–", COALESCE(max_age, "+"), ")"))) AS label FROM {p}checkin_classes WHERE active = 1 AND (campus_id IS NULL OR campus_id = ? OR ? IS NULL) ORDER BY min_age, name', [$campus, $campus]), 'label', 'id');
    }

    public function index(): void
    {
        [$cw, $cp] = Campus::where('s');
        $sessions = DB::all("SELECT s.*, (SELECT COUNT(*) FROM {p}checkins c WHERE c.session_id = s.id) AS kids, (SELECT COUNT(*) FROM {p}checkins c WHERE c.session_id = s.id AND c.checked_out_at IS NULL) AS present FROM {p}checkin_sessions s WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)$cw ORDER BY s.session_date DESC, s.start_time DESC LIMIT 40", $cp);
        $classes = DB::all('SELECT c.*, r.name AS room FROM {p}checkin_classes c LEFT JOIN {p}rooms r ON r.id = c.room_id ORDER BY c.active DESC, c.min_age, c.name');
        view('checkin/index', ['title' => __('Children\'s check-in'), 'sessions' => $sessions, 'classes' => $classes, 'rooms' => array_column(DB::all('SELECT id, name FROM {p}rooms WHERE active = 1 AND is_equipment = 0 ORDER BY name'), 'name', 'id')]);
    }

    public function storeSession(): void
    {
        $date = input_date('session_date') ?? date('Y-m-d');
        $id = DB::insert('checkin_sessions', ['title' => mb_substr((string)input('title', '') ?: __('Sunday kids church'), 0, 160), 'session_date' => $date, 'start_time' => input('start_time') ?: null, 'campus_id' => Campus::fromInput(), 'created_by' => Auth::id()]);
        audit('checkin.session_opened', 'checkin_session', $id);
        redirect('/checkin/sessions/' . $id);
    }

    public function saveClass(): void
    {
        if (!can('groups.manage') && !can('admin.settings') && !can('children.medical')) {
            abort(403);
        }
        $d = ['name' => mb_substr((string)input('name', ''), 0, 120), 'min_age' => input_int('min_age'), 'max_age' => input_int('max_age'), 'ratio' => max(1, (int)input_int('ratio', 8)), 'room_id' => input_int('room_id'), 'campus_id' => Campus::fromInput(), 'active' => input('id') ? input_bool('active') : 1];
        if ($d['name'] === '') {
            redirect('/checkin');
        }
        if ($id = input_int('id')) {
            DB::update('checkin_classes', $d, 'id = ?', [$id]);
        } else {
            DB::insert('checkin_classes', $d);
        }
        audit('checkin.class_saved');
        redirect('/checkin');
    }

    public function show(string $id): void
    {
        $s = $this->session($id);
        $q = trim((string)input('q', ''));
        $families = [];
        if ($q !== '' && mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            $digits = preg_replace('/\D/', '', $q);
            $hids = DB::col("SELECT DISTINCT p.household_id FROM {p}people p LEFT JOIN {p}households h ON h.id = p.household_id WHERE p.archived_at IS NULL AND p.household_id IS NOT NULL AND (p.last_name LIKE ? OR h.name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?" . ($digits !== '' && strlen($digits) >= 4 ? " OR REPLACE(REPLACE(p.mobile, ' ', ''), '-', '') LIKE ?" : '') . ') LIMIT 10', array_merge([$like, $like, $like], $digits !== '' && strlen($digits) >= 4 ? ['%' . $digits . '%'] : []));
            foreach ($hids as $hid) {
                $members = DB::all('SELECT * FROM {p}people WHERE household_id = ? AND archived_at IS NULL ORDER BY household_role, birthdate', [$hid]);
                $kids = array_values(array_filter($members, fn($m) => $m['household_role'] === 'child'));
                if (!$kids) {
                    continue;
                }
                foreach ($kids as &$k) {
                    $k['suggest'] = self::suggestClass($k['birthdate'], $s['campus_id'] ? (int)$s['campus_id'] : null);
                    $k['alert'] = self::hasAlert((int)$k['id']);
                    $k['in'] = DB::row('SELECT * FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $k['id']]);
                }
                unset($k);
                $families[] = ['name' => DB::val('SELECT name FROM {p}households WHERE id = ?', [$hid]), 'adults' => array_values(array_filter($members, fn($m) => $m['household_role'] !== 'child')), 'kids' => $kids];
            }
        }
        $present = DB::all('SELECT c.*, p.first_name, p.last_name, p.preferred_name, p.birthdate, k.name AS class FROM {p}checkins c JOIN {p}people p ON p.id = c.child_id LEFT JOIN {p}checkin_classes k ON k.id = c.class_id WHERE c.session_id = ? ORDER BY c.checked_out_at IS NOT NULL, k.name, p.first_name', [$s['id']]);
        foreach ($present as &$pr) {
            $pr['alert'] = self::hasAlert((int)$pr['child_id']);
        }
        unset($pr);
        $expected = [];
        foreach (DB::all('SELECT pc.*, p.first_name, p.last_name, p.preferred_name, k.name AS class FROM {p}prechecks pc JOIN {p}people p ON p.id = pc.child_id LEFT JOIN {p}checkin_classes k ON k.id = pc.class_id WHERE pc.session_id = ? ORDER BY pc.created_at', [$s['id']]) as $pc) {
            $expected[$pc['arrival_code']][] = $pc;
        }
        if ($q !== '' && isset($expected[strtoupper($q)])) {
            $expected = [strtoupper($q) => $expected[strtoupper($q)]];
        }
        $classStats = DB::all('SELECT k.id, k.name, k.ratio, (SELECT COUNT(*) FROM {p}checkins c WHERE c.session_id = ? AND c.class_id = k.id AND c.checked_out_at IS NULL) AS kids, (SELECT COUNT(*) FROM {p}checkin_leaders l WHERE l.session_id = ? AND l.class_id = k.id AND l.checked_out_at IS NULL) AS leaders FROM {p}checkin_classes k WHERE k.active = 1 ORDER BY k.min_age', [$s['id'], $s['id']]);
        view('checkin/session', ['title' => $s['title'], 's' => $s, 'q' => $q, 'families' => $families, 'present' => $present, 'classes' => $this->classes($s['campus_id'] ? (int)$s['campus_id'] : null), 'classStats' => $classStats, 'expected' => $expected]);
    }

    public static function newCode(int $sessionId): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while (DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND security_code = ?', [$sessionId, $code]));
        return $code;
    }

    public function checkIn(string $id): void
    {
        $s = $this->session($id);
        if ($s['status'] !== 'open') {
            flash('error', __('This session is closed.'));
            redirect('/checkin/sessions/' . $s['id']);
        }
        $kids = input_ids('child_ids');
        if (!$kids) {
            flash('error', __('Choose at least one child.'));
            back('/checkin/sessions/' . $s['id']);
        }
        $code = self::newCode((int)$s['id']);
        $ids = [];
        foreach ($kids as $kid) {
            $child = DB::row("SELECT * FROM {p}people WHERE id = ? AND household_role = 'child' AND archived_at IS NULL", [$kid]);
            if (!$child || DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $kid])) {
                continue;
            }
            $class = input_int('class_' . $kid) ?: self::suggestClass($child['birthdate'], $s['campus_id'] ? (int)$s['campus_id'] : null);
            $pre = (int)DB::run('DELETE FROM {p}prechecks WHERE session_id = ? AND child_id = ?', [$s['id'], $kid])->rowCount();
            $ids[] = DB::insert('checkins', ['session_id' => $s['id'], 'child_id' => $kid, 'class_id' => $class, 'security_code' => $code, 'checked_in_by' => Auth::id(), 'dropped_off_by' => mb_substr((string)input('dropped_off_by', ''), 0, 160) ?: null, 'prechecked' => $pre ? 1 : 0]);
        }
        audit('checkin.children_in', 'checkin_session', (int)$s['id'], ['checkins' => $ids]);
        if (!$ids) {
            flash('warning', __('Those children are already checked in.'));
            redirect('/checkin/sessions/' . $s['id']);
        }
        redirect('/checkin/labels', ['ids' => implode(',', $ids)]);
    }

    private function labelRows(array $ids): array
    {
        $rows = $ids ? DB::all('SELECT c.*, p.first_name, p.last_name, p.preferred_name, k.name AS class, s.title, s.session_date FROM {p}checkins c JOIN {p}people p ON p.id = c.child_id LEFT JOIN {p}checkin_classes k ON k.id = c.class_id JOIN {p}checkin_sessions s ON s.id = c.session_id WHERE c.id IN (' . DB::in($ids) . ')', $ids) : [];
        foreach ($rows as &$r) {
            $info = self::careInfo((int)$r['child_id']);
            $r['allergies'] = $info['allergies'];
            $r['alert'] = trim($info['allergies'] . $info['medical'] . $info['needs']) !== '';
        }
        unset($r);
        return $rows;
    }

    public function labels(): void
    {
        $ids = array_filter(array_map('intval', explode(',', (string)input('ids', ''))));
        $rows = $this->labelRows($ids);
        $printer = (string)setting('label_printer_id', '');
        $sent = null;
        $error = null;
        if ($rows && Labels::printNodeOn() && $printer !== '') {
            $key = 'printed_' . md5(implode(',', $ids));
            if (empty($_SESSION[$key]) || input('reprint')) {
                $error = Labels::printCheckins($rows, $printer, can('children.medical'));
                $_SESSION[$key] = $error === null;
            }
            $sent = $error === null;
        }
        view('checkin/labels', ['title' => __('Labels'), 'rows' => $rows, 'sent' => $sent, 'printError' => $error, 'ids' => implode(',', $ids)], 'layout_print');
    }

    public function labelsPdf(): void
    {
        $ids = array_filter(array_map('intval', explode(',', (string)input('ids', ''))));
        $rows = $this->labelRows($ids);
        if (!$rows || !Labels::canRender()) {
            abort(404);
        }
        audit('checkin.labels_pdf', 'checkin_session', (int)$rows[0]['session_id']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="labels.pdf"');
        header('Cache-Control: no-store');
        echo Labels::forCheckins($rows, can('children.medical'));
        exit;
    }

    /** Who may collect a child: adults in the household plus listed authorised collectors. */
    public static function collectors(int $childId): array
    {
        $out = [];
        $hid = DB::val('SELECT household_id FROM {p}people WHERE id = ?', [$childId]);
        if ($hid) {
            foreach (DB::all("SELECT id, first_name, last_name, preferred_name FROM {p}people WHERE household_id = ? AND household_role <> 'child' AND archived_at IS NULL", [$hid]) as $a) {
                $out['p' . $a['id']] = person_name($a) . ' (' . __('household') . ')';
            }
        }
        foreach (DB::all('SELECT * FROM {p}authorised_collectors WHERE child_id = ? AND not_permitted = 0', [$childId]) as $c) {
            $out['c' . $c['id']] = $c['name'] . ($c['relationship'] ? ' (' . $c['relationship'] . ')' : '');
        }
        return $out;
    }

    public function checkOut(string $id): void
    {
        $c = DB::row('SELECT c.*, p.first_name, p.last_name, p.preferred_name FROM {p}checkins c JOIN {p}people p ON p.id = c.child_id WHERE c.id = ? AND c.checked_out_at IS NULL', [(int)$id]) ?? abort(404);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)input('code', '')));
        if (!hash_equals($c['security_code'], $code)) {
            audit('checkin.code_mismatch', 'checkin', (int)$c['id']);
            flash('error', __('The security code does not match. Do not release %s — ask a leader for help.', person_name($c)));
            redirect('/checkin/sessions/' . $c['session_id']);
        }
        $who = (string)input('collector', '');
        $allowed = self::collectors((int)$c['child_id']);
        $override = trim((string)input('override_reason', ''));
        if (!isset($allowed[$who]) && $override === '') {
            flash('error', __('Choose an authorised collector, or record why a leader approved someone else.'));
            redirect('/checkin/sessions/' . $c['session_id']);
        }
        $name = $allowed[$who] ?? mb_substr((string)input('collector_name', __('Other')), 0, 120);
        DB::update('checkins', ['checked_out_at' => date('Y-m-d H:i:s'), 'checked_out_by' => Auth::id(), 'collected_by' => mb_substr($name, 0, 160), 'notes' => $override !== '' ? mb_substr('Override: ' . $override, 0, 255) : null], 'id = ?', [$c['id']]);
        audit($override !== '' ? 'checkin.child_out_override' : 'checkin.child_out', 'checkin', (int)$c['id'], ['collected_by' => $name]);
        flash('success', __('%s checked out to %s.', person_name($c), $name));
        redirect('/checkin/sessions/' . $c['session_id']);
    }

    public function leaderIn(string $id): void
    {
        $s = $this->session($id);
        $pid = input_int('person_id');
        $class = input_int('class_id');
        if (!$pid || !$class) {
            back('/checkin/sessions/' . $s['id']);
        }
        if (!Volunteering::validCheck($pid, $s['session_date'])) {
            flash('error', __('This person has no verified, current Working With Children Check, so they cannot be counted as a leader.'));
            redirect('/checkin/sessions/' . $s['id']);
        }
        DB::run('INSERT INTO {p}checkin_leaders (session_id, class_id, person_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE checked_out_at = NULL', [$s['id'], $class, $pid]);
        audit('checkin.leader_in', 'checkin_session', (int)$s['id'], ['person_id' => $pid, 'class_id' => $class]);
        redirect('/checkin/sessions/' . $s['id']);
    }

    public function room(string $id, string $class): void
    {
        $s = $this->session($id);
        $k = DB::row('SELECT * FROM {p}checkin_classes WHERE id = ?', [(int)$class]) ?? abort(404);
        $kids = DB::all('SELECT c.*, p.first_name, p.last_name, p.preferred_name, p.birthdate FROM {p}checkins c JOIN {p}people p ON p.id = c.child_id WHERE c.session_id = ? AND c.class_id = ? AND c.checked_out_at IS NULL ORDER BY p.first_name', [$s['id'], $k['id']]);
        $showMedical = can('children.medical');
        foreach ($kids as &$kid) {
            $kid['info'] = self::careInfo((int)$kid['child_id']);
            $kid['photo'] = $kid['info']['photo_permission'];
        }
        unset($kid);
        if ($showMedical && $kids) {
            audit('children.medical_viewed', 'checkin_session', (int)$s['id'], ['class' => $k['name']]);
        }
        $leaders = DB::all('SELECT l.*, p.first_name, p.last_name, p.preferred_name FROM {p}checkin_leaders l JOIN {p}people p ON p.id = l.person_id WHERE l.session_id = ? AND l.class_id = ? AND l.checked_out_at IS NULL', [$s['id'], $k['id']]);
        view('checkin/room', ['title' => $k['name'], 's' => $s, 'k' => $k, 'kids' => $kids, 'leaders' => $leaders, 'showMedical' => $showMedical]);
    }

    public function close(string $id): void
    {
        $s = $this->session($id);
        $still = (int)DB::val('SELECT COUNT(*) FROM {p}checkins WHERE session_id = ? AND checked_out_at IS NULL', [$s['id']]);
        if ($still && !input_bool('confirm')) {
            flash('error', __('%d child(ren) are still checked in. Check them out before closing.', $still));
            redirect('/checkin/sessions/' . $s['id']);
        }
        DB::update('checkin_sessions', ['status' => 'closed'], 'id = ?', [$s['id']]);
        DB::run('DELETE FROM {p}prechecks WHERE session_id = ?', [$s['id']]);
        DB::run('UPDATE {p}checkin_leaders SET checked_out_at = NOW() WHERE session_id = ? AND checked_out_at IS NULL', [$s['id']]);
        audit('checkin.session_closed', 'checkin_session', (int)$s['id']);
        redirect('/checkin');
    }

    // Child care info & collectors (staff with children.medical, or the child's parents) -------
    public static function canEditChild(int $childId): bool
    {
        if (can('children.medical')) {
            return true;
        }
        $pid = person_id();
        $hid = DB::val('SELECT household_id FROM {p}people WHERE id = ?', [$childId]);
        return $pid && $hid && (bool)DB::val("SELECT 1 FROM {p}people WHERE id = ? AND household_id = ? AND household_role = 'adult'", [$pid, $hid]);
    }

    public function saveChildProfile(string $id): void
    {
        $child = DB::row('SELECT * FROM {p}people WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!self::canEditChild((int)$child['id'])) {
            abort(403);
        }
        $d = ['allergies_enc' => Crypto::encrypt(trim((string)input('allergies', ''))), 'medical_enc' => Crypto::encrypt(trim((string)input('medical', ''))), 'needs_enc' => Crypto::encrypt(trim((string)input('needs', ''))), 'emergency_enc' => Crypto::encrypt(trim((string)input('emergency', ''))), 'photo_permission' => input_bool('photo_permission'), 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')];
        DB::run('INSERT INTO {p}child_profiles (person_id, allergies_enc, medical_enc, needs_enc, emergency_enc, photo_permission, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE allergies_enc = VALUES(allergies_enc), medical_enc = VALUES(medical_enc), needs_enc = VALUES(needs_enc), emergency_enc = VALUES(emergency_enc), photo_permission = VALUES(photo_permission), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)', array_merge([$child['id']], array_values($d)));
        audit('children.care_info_updated', 'person', (int)$child['id']);
        flash('success', __('Care information saved (encrypted).'));
        back('/people/' . $child['id']);
    }

    public function addCollector(string $id): void
    {
        $child = DB::row('SELECT * FROM {p}people WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!self::canEditChild((int)$child['id'])) {
            abort(403);
        }
        $name = trim((string)input('name', ''));
        if ($name !== '') {
            DB::insert('authorised_collectors', ['child_id' => $child['id'], 'name' => mb_substr($name, 0, 160), 'relationship' => mb_substr((string)input('relationship', ''), 0, 60) ?: null, 'phone' => mb_substr((string)input('phone', ''), 0, 40) ?: null, 'not_permitted' => input_bool('not_permitted'), 'notes' => mb_substr((string)input('notes', ''), 0, 255) ?: null]);
            audit('children.collector_added', 'person', (int)$child['id']);
        }
        back('/people/' . $child['id']);
    }

    public function deleteCollector(string $id): void
    {
        $c = DB::row('SELECT * FROM {p}authorised_collectors WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!self::canEditChild((int)$c['child_id'])) {
            abort(403);
        }
        DB::run('DELETE FROM {p}authorised_collectors WHERE id = ?', [$c['id']]);
        audit('children.collector_removed', 'person', (int)$c['child_id']);
        back('/people/' . $c['child_id']);
    }

    /** Parents manage their own children's care info and collectors. */
    public function myChildren(): void
    {
        $pid = person_id() ?? abort(403);
        $hid = DB::val("SELECT household_id FROM {p}people WHERE id = ? AND household_role = 'adult'", [$pid]);
        $kids = $hid ? DB::all("SELECT * FROM {p}people WHERE household_id = ? AND household_role = 'child' AND archived_at IS NULL ORDER BY birthdate", [$hid]) : [];
        foreach ($kids as &$k) {
            $k['info'] = self::careInfo((int)$k['id']);
            $k['collectors'] = DB::all('SELECT * FROM {p}authorised_collectors WHERE child_id = ?', [$k['id']]);
            $k['history'] = DB::all('SELECT c.checked_in_at, c.checked_out_at, c.collected_by, s.title FROM {p}checkins c JOIN {p}checkin_sessions s ON s.id = c.session_id WHERE c.child_id = ? ORDER BY c.id DESC LIMIT 5', [$k['id']]);
        }
        unset($k);
        view('checkin/my_children', ['title' => __('My children'), 'kids' => $kids] + $this->precheckData($kids, $hid ? (int)$hid : null));
    }

    // Pre-check-in from a phone ------------------------------------------------------------

    /** Sessions a family can pre-check-in to: open, and today. */
    public static function precheckSessions(): array
    {
        return DB::all("SELECT * FROM {p}checkin_sessions WHERE status = 'open' AND session_date = CURDATE() ORDER BY start_time, id");
    }

    private function precheckData(array $kids, ?int $hid): array
    {
        $sessions = self::precheckSessions();
        $state = [];
        foreach ($sessions as $s) {
            foreach ($kids as $k) {
                $pc = DB::row('SELECT * FROM {p}prechecks WHERE session_id = ? AND child_id = ?', [$s['id'], $k['id']]);
                $in = DB::row('SELECT * FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $k['id']]);
                $state[$s['id']][$k['id']] = $in ? 'in' : ($pc ? 'pre' : 'none');
                if ($pc) {
                    $state[$s['id']]['code'] = $pc['arrival_code'];
                }
            }
        }
        $adults = $hid ? DB::all("SELECT id, first_name, last_name, preferred_name FROM {p}people WHERE household_id = ? AND household_role <> 'child' AND archived_at IS NULL", [$hid]) : [];
        return ['pcSessions' => $sessions, 'pcState' => $state, 'pcAdults' => $adults];
    }

    private function myHousehold(): int
    {
        $pid = person_id() ?? abort(403);
        return (int)DB::val("SELECT household_id FROM {p}people WHERE id = ? AND household_role = 'adult'", [$pid]) ?: abort(403);
    }

    /** A family's arrival code for a session (shared by all its pre-checked children). */
    private static function arrivalCode(int $sessionId, int $hid): string
    {
        $existing = DB::val('SELECT arrival_code FROM {p}prechecks WHERE session_id = ? AND household_id = ? LIMIT 1', [$sessionId, $hid]);
        if ($existing) {
            return (string)$existing;
        }
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while (DB::val('SELECT 1 FROM {p}prechecks WHERE session_id = ? AND arrival_code = ?', [$sessionId, $code]));
        return $code;
    }

    public function precheck(): void
    {
        $hid = $this->myHousehold();
        $s = DB::row("SELECT * FROM {p}checkin_sessions WHERE id = ? AND status = 'open' AND session_date = CURDATE()", [input_int('session_id')]);
        if (!$s) {
            flash('error', __('That check-in is not open today.'));
            redirect('/me/children');
        }
        $kids = input_ids('child_ids');
        if (!$kids) {
            flash('error', __('Choose at least one child.'));
            redirect('/me/children');
        }
        $adult = input_int('adult_id');
        $adultRow = $adult ? DB::row('SELECT * FROM {p}people WHERE id = ? AND household_id = ?', [$adult, $hid]) : null;
        $code = self::arrivalCode((int)$s['id'], $hid);
        $n = 0;
        foreach ($kids as $kid) {
            $child = DB::row("SELECT * FROM {p}people WHERE id = ? AND household_id = ? AND household_role = 'child' AND archived_at IS NULL", [$kid, $hid]);
            if (!$child || DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $kid])) {
                continue;
            }
            DB::run('INSERT IGNORE INTO {p}prechecks (session_id, child_id, household_id, class_id, arrival_code, dropped_off_by, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $s['id'], $kid, $hid, self::suggestClass($child['birthdate'], $s['campus_id'] ? (int)$s['campus_id'] : null), $code, $adultRow ? person_name($adultRow) : null, Auth::id()]);
            $n++;
        }
        audit('checkin.prechecked', 'checkin_session', (int)$s['id'], ['children' => $n]);
        flash('success', __('You\'re pre-checked in. When you arrive, give your arrival code %s (or your mobile number) at the kiosk or welcome desk and your labels will print.', $code));
        redirect('/me/children');
    }

    public function precheckCancel(): void
    {
        $hid = $this->myHousehold();
        DB::run('DELETE FROM {p}prechecks WHERE session_id = ? AND household_id = ?', [input_int('session_id'), $hid]);
        flash('success', __('Pre-check-in cancelled.'));
        redirect('/me/children');
    }

    /**
     * Turn pre-check-ins into real check-ins (one shared security code). Used by the desk and kiosks.
     * Returns the new checkin IDs.
     */
    public static function arrive(array $s, array $prechecks, ?int $kioskId = null): array
    {
        $code = self::newCode((int)$s['id']);
        $ids = [];
        foreach ($prechecks as $pc) {
            if (!DB::val('SELECT 1 FROM {p}checkins WHERE session_id = ? AND child_id = ? AND checked_out_at IS NULL', [$s['id'], $pc['child_id']])) {
                $ids[] = DB::insert('checkins', ['session_id' => $s['id'], 'child_id' => $pc['child_id'], 'class_id' => $pc['class_id'], 'security_code' => $code,
                    'checked_in_by' => Auth::id(), 'kiosk_id' => $kioskId, 'dropped_off_by' => $pc['dropped_off_by'], 'prechecked' => 1]);
            }
            DB::run('DELETE FROM {p}prechecks WHERE id = ?', [$pc['id']]);
        }
        return $ids;
    }

    /** Desk: confirm a pre-checked family has arrived, then print labels. */
    public function arriveDesk(string $id): void
    {
        $s = $this->session($id);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)input('code', '')));
        $pcs = DB::all('SELECT * FROM {p}prechecks WHERE session_id = ? AND arrival_code = ?', [$s['id'], $code]);
        if (!$pcs) {
            flash('error', __('No pre-check-in matches that arrival code.'));
            redirect('/checkin/sessions/' . $s['id']);
        }
        $ids = self::arrive($s, $pcs);
        audit('checkin.arrived', 'checkin_session', (int)$s['id'], ['checkins' => $ids]);
        redirect('/checkin/labels', ['ids' => implode(',', $ids)]);
    }
}
