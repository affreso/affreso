<?php
declare(strict_types=1);

/** Pages that can be linked from the church website without signing in. */
final class PublicController
{
    private function rateLimit(string $key, int $max = 10): void
    {
        if ((int)DB::val('SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [client_ip(), $key]) >= $max) {
            abort(429, __('Too many submissions from your connection. Please try again later.'));
        }
        DB::insert('login_attempts', ['email' => $key, 'ip' => client_ip(), 'success' => 1]);
    }

    private function honeypot(): void
    {
        if (!empty($_POST['website'])) {
            flash('success', __('Thank you!'));
            redirect('/public/events');
        }
    }

    public function events(): void
    {
        $events = DB::all("SELECT * FROM {p}events WHERE is_public = 1 AND status = 'scheduled' AND end_at >= NOW() ORDER BY start_at LIMIT 50");
        view('public/events', ['title' => __('Upcoming events'), 'events' => $events], 'layout_public');
    }

    public function event(string $id): void
    {
        $ev = DB::row("SELECT * FROM {p}events WHERE id = ? AND is_public = 1", [(int)$id]) ?? abort(404);
        view('public/event', ['title' => $ev['title'], 'ev' => $ev, 'taken' => Events::taken((int)$ev['id']), 'form' => Events::form((int)$ev['id'])], 'layout_public');
    }

    public function register(string $id): void
    {
        $this->honeypot();
        $this->rateLimit('public:register', 20);
        $ev = DB::row("SELECT * FROM {p}events WHERE id = ? AND is_public = 1 AND status = 'scheduled'", [(int)$id]) ?? abort(404);
        $name = trim((string)input('name', ''));
        $email = filter_var(mb_strtolower(trim((string)input('email', ''))), FILTER_VALIDATE_EMAIL);
        if ($name === '' || !$email || !input_bool('privacy_ack')) {
            flash('error', __('Please enter your name and a valid email, and acknowledge the privacy notice.'));
            redirect('/public/events/' . $ev['id']);
        }
        $pid = (int)DB::val('SELECT id FROM {p}people WHERE email = ? AND archived_at IS NULL LIMIT 1', [$email]) ?: null;
        [$ok, $msg] = Events::register($ev, ['person_id' => $pid, 'name' => mb_substr($name, 0, 160), 'email' => $email, 'phone' => mb_substr((string)input('phone', ''), 0, 40) ?: null, 'guests' => max(0, min(10, (int)input_int('guests', 0))), 'notes' => mb_substr((string)input('notes', ''), 0, 255) ?: null]);
        flash($ok ? 'success' : 'error', $msg);
        redirect('/public/events/' . $ev['id']);
    }

    public function connectForm(): void
    {
        view('public/connect', ['title' => __("I'm new")], 'layout_public');
    }

    /** Digital connect card: creates a visitor record and the newcomer follow-up journey. */
    public function connect(): void
    {
        $this->honeypot();
        $this->rateLimit('public:connect', 10);
        $first = trim((string)input('first_name', ''));
        $last = trim((string)input('last_name', ''));
        $email = filter_var(mb_strtolower(trim((string)input('email', ''))), FILTER_VALIDATE_EMAIL) ?: null;
        if ($first === '' || $last === '' || (!$email && trim((string)input('mobile', '')) === '') || !input_bool('consent')) {
            flash('error', __('Please give your name, an email or mobile, and your consent for us to keep your details.'));
            redirect('/connect');
        }
        $pid = $email ? (int)DB::val('SELECT id FROM {p}people WHERE email = ? AND archived_at IS NULL LIMIT 1', [$email]) : 0;
        if (!$pid) {
            $pid = DB::insert('people', ['first_name' => mb_substr($first, 0, 80), 'last_name' => mb_substr($last, 0, 80), 'email' => $email, 'mobile' => mb_substr((string)input('mobile', ''), 0, 40) ?: null, 'suburb' => mb_substr((string)input('suburb', ''), 0, 100) ?: null, 'membership_status' => 'visitor', 'first_visit' => date('Y-m-d'), 'consent_contact' => 1, 'consent_updated_at' => date('Y-m-d H:i:s'), 'general_notes' => input('heard') ? 'Heard about us: ' . mb_substr((string)input('heard'), 0, 200) : null]);
            DB::insert('consent_records', ['person_id' => $pid, 'consent_type' => 'contact', 'granted' => 1, 'method' => 'online connect card']);
        }
        $assignee = ((int)setting('connect_followup_user', 0)) ?: null;
        $name = $first . ' ' . $last;
        DB::insert('care_tasks', ['person_id' => $pid, 'task_type' => 'newcomer', 'title' => __('Welcome follow-up: %s', $name), 'details' => input('message') ? mb_substr((string)input('message'), 0, 2000) : null, 'assigned_to' => $assignee, 'due_date' => date('Y-m-d', strtotime('+3 days'))]);
        $map = ['baptism' => __('Baptism enquiry: %s', $name), 'membership_class' => __('Interested in membership: %s', $name), 'pastoral_contact' => __('Would like to talk with a pastor: %s', $name)];
        foreach ((array)($_POST['interests'] ?? []) as $i) {
            if (isset($map[$i])) {
                DB::insert('care_tasks', ['person_id' => $pid, 'task_type' => $i, 'title' => $map[$i], 'assigned_to' => $assignee, 'due_date' => date('Y-m-d', strtotime('+7 days'))]);
            }
        }
        if (($prayer = trim((string)input('prayer', ''))) !== '') {
            $vis = input_bool('prayer_private') ? 'pastors' : 'team';
            $prid = DB::insert('prayer_requests', ['person_id' => $pid, 'name' => $name, 'request_enc' => Crypto::encrypt(mb_substr($prayer, 0, 4000)), 'visibility' => $vis]);
            PrayerController::alertTeam($prid, $vis);
        }
        if (input_bool('small_group')) {
            DB::insert('care_tasks', ['person_id' => $pid, 'task_type' => 'other', 'title' => __('Help %s find a small group', $name), 'assigned_to' => $assignee, 'due_date' => date('Y-m-d', strtotime('+7 days'))]);
        }
        Notify::users($assignee ? [$assignee] : users_with_permission('care.manage'), __('New connect card: %s', $name), '<p>' . e(__('%s filled in a connect card. A welcome follow-up has been created.', $name)) . '</p>', url('/care'));
        if ($email) {
            Notify::emailAddress($email, __('Welcome to %s', setting('church_name')), '<p>' . e(__('Hi %s,', $first)) . '</p><p>' . e(__('Thank you for connecting with us — we are so glad you visited. Someone from our welcome team will be in touch soon.')) . '</p>');
        }
        Notify::processQueue(5);
        audit('connect_card.submitted', 'person', $pid);
        view('public/connected', ['title' => __('Thank you'), 'first' => $first], 'layout_public');
    }

    // iCalendar ----------------------------------------------------------------
    private static function icsEscape(string $s): string
    {
        return str_replace(["\\", "\r\n", "\n", ',', ';'], ['\\\\', '\\n', '\\n', '\\,', '\;'], $s);
    }

    private static function icsLine(string $line): string
    {
        $out = '';
        while (strlen($line) > 74) {
            $cut = 74;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $out . $line . "\r\n";
    }

    private static function icsTime(string $local): string
    {
        return gmdate('Ymd\THis\Z', strtotime($local));
    }

    public function ical(string $token): void
    {
        $items = [];
        $calName = setting('church_name', 'Church');
        if ($token === 'church') {
            foreach (DB::all("SELECT * FROM {p}events WHERE is_public = 1 AND status = 'scheduled' AND end_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") as $ev) {
                $items[] = ['uid' => 'event-' . $ev['id'], 'start' => $ev['start_at'], 'end' => $ev['end_at'], 'title' => $ev['title'], 'loc' => $ev['location'], 'desc' => $ev['description']];
            }
        } else {
            if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
                abort(404);
            }
            $u = DB::row("SELECT * FROM {p}users WHERE ical_token = ? AND status = 'active'", [$token]) ?? abort(404);
            $pid = (int)$u['person_id'];
            $calName .= ' — ' . __('My calendar');
            foreach (DB::all("SELECT a.id, a.status, r.title, r.service_date, r.start_time, r.end_time, po.name AS position FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id WHERE a.person_id = ? AND r.status = 'published' AND a.status IN ('pending','accepted','swap_requested') AND r.service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$pid]) as $a) {
                $items[] = ['uid' => 'roster-' . $a['id'], 'start' => $a['service_date'] . ' ' . ($a['start_time'] ?: '09:00:00'), 'end' => $a['service_date'] . ' ' . ($a['end_time'] ?: ($a['start_time'] ? date('H:i:s', strtotime($a['start_time']) + 7200) : '11:00:00')), 'title' => __('Serving: %s', $a['position']) . ($a['status'] === 'pending' ? ' (' . __('please respond') . ')' : ''), 'loc' => null, 'desc' => $a['title']];
            }
            foreach (DB::all("SELECT e.* FROM {p}events e WHERE e.status = 'scheduled' AND e.end_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND (e.is_public = 1 OR e.id IN (SELECT event_id FROM {p}event_registrations WHERE person_id = ? AND status IN ('registered','attended')))", [$pid]) as $ev) {
                $items[] = ['uid' => 'event-' . $ev['id'], 'start' => $ev['start_at'], 'end' => $ev['end_at'], 'title' => $ev['title'], 'loc' => $ev['location'], 'desc' => $ev['description']];
            }
        }
        $host = parse_url(base_url(), PHP_URL_HOST) ?: 'affreso';
        $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Affreso//Church Management//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n" . self::icsLine('X-WR-CALNAME:' . self::icsEscape($calName));
        foreach ($items as $i) {
            $out .= "BEGIN:VEVENT\r\n" . self::icsLine('UID:' . $i['uid'] . '@' . $host) . 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . self::icsTime($i['start']) . "\r\nDTEND:" . self::icsTime($i['end']) . "\r\n" . self::icsLine('SUMMARY:' . self::icsEscape($i['title']));
            if ($i['loc']) {
                $out .= self::icsLine('LOCATION:' . self::icsEscape($i['loc']));
            }
            if ($i['desc']) {
                $out .= self::icsLine('DESCRIPTION:' . self::icsEscape($i['desc']));
            }
            $out .= "END:VEVENT\r\n";
        }
        $out .= "END:VCALENDAR\r\n";
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="calendar.ics"');
        echo $out;
        exit;
    }

    public function verifyCertificate(string $code): void
    {
        $e = DB::row("SELECT e.*, c.title, p.first_name, p.last_name FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id JOIN {p}people p ON p.id = e.person_id WHERE e.certificate_code = ? AND e.status = 'completed'", [strtoupper(substr($code, 0, 32))]);
        view('public/verify', ['title' => __('Verify certificate'), 'e' => $e], 'layout_public');
    }

    public function privacy(): void
    {
        view('public/privacy', ['title' => __('Privacy policy')], user() ? 'layout' : 'layout_public');
    }
}
