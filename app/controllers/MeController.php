<?php
declare(strict_types=1);

/** "My Affreso" — self-service for members and volunteers. Everything here is scoped to the signed-in person. */
final class MeController
{
    private function pid(): int
    {
        $pid = person_id();
        if (!$pid) {
            abort(403, __('Your login is not linked to a person record. Ask your church administrator to link it.'));
        }
        return $pid;
    }

    private function me(): array
    {
        return DB::row('SELECT * FROM {p}people WHERE id = ?', [$this->pid()]);
    }

    // Profile ------------------------------------------------------------------
    public function profile(): void
    {
        $p = $this->me();
        $household = $p['household_id'] ? DB::all('SELECT * FROM {p}people WHERE household_id = ? AND id <> ? AND archived_at IS NULL', [$p['household_id'], $p['id']]) : [];
        view('me/profile', ['title' => __('My profile'), 'p' => $p, 'household' => $household]);
    }

    public function saveProfile(): void
    {
        $p = $this->me();
        $d = PeopleController::formData(false);
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            flash('error', __('First and last name are required.'));
            redirect('/me/profile');
        }
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', __('The email address is not valid.'));
            redirect('/me/profile');
        }
        $d['updated_at'] = date('Y-m-d H:i:s');
        DB::update('people', $d, 'id = ?', [$p['id']]);
        if ($d['email'] && $d['email'] !== $p['email'] && !DB::val('SELECT 1 FROM {p}users WHERE email = ? AND id <> ?', [$d['email'], Auth::id()])) {
            DB::update('users', ['email' => $d['email']], 'id = ?', [Auth::id()]);
        }
        audit('person.self_updated', 'person', (int)$p['id']);
        flash('success', __('Your details have been updated.'));
        redirect('/me/profile');
    }

    // Rosters ------------------------------------------------------------------
    public function rosters(): void
    {
        $pid = $this->pid();
        $upcoming = DB::all("SELECT a.*, r.title, r.service_date, r.start_time, r.end_time, r.notes, r.group_id, po.name AS position, g.name AS group_name FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id JOIN {p}groups g ON g.id = r.group_id WHERE a.person_id = ? AND r.status = 'published' AND r.service_date >= CURDATE() ORDER BY r.service_date, r.start_time", [$pid]);
        $past = DB::all("SELECT a.status, r.title, r.service_date, po.name AS position FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id WHERE a.person_id = ? AND r.service_date < CURDATE() ORDER BY r.service_date DESC LIMIT 10", [$pid]);
        $openSwaps = DB::all("SELECT s.*, r.title, r.service_date, po.name AS position FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions po ON po.id = ra.position_id JOIN {p}volunteer_positions vp ON vp.position_id = ra.position_id AND vp.person_id = ? AND vp.approved_at IS NOT NULL WHERE s.status = 'open' AND s.requested_by <> ? AND r.service_date >= CURDATE()", [$pid, $pid]);
        $positions = DB::all('SELECT po.name, g.name AS group_name FROM {p}volunteer_positions vp JOIN {p}positions po ON po.id = vp.position_id JOIN {p}groups g ON g.id = po.group_id WHERE vp.person_id = ? AND vp.approved_at IS NOT NULL', [$pid]);
        view('me/rosters', ['title' => __('My rosters'), 'upcoming' => $upcoming, 'past' => $past, 'openSwaps' => $openSwaps, 'positions' => $positions]);
    }

    private function myAssignment(int|string $id): array
    {
        $a = DB::row("SELECT a.*, r.title, r.service_date, r.group_id, po.name AS position FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id WHERE a.id = ? AND a.person_id = ? AND r.status = 'published'", [(int)$id, $this->pid()]);
        if (!$a) {
            abort(404);
        }
        return $a;
    }

    public function respond(string $id): void
    {
        $a = $this->myAssignment($id);
        $accept = input('answer') === 'accept';
        DB::update('roster_assignments', ['status' => $accept ? 'accepted' : 'declined', 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [$a['id']]);
        audit($accept ? 'roster.accepted' : 'roster.declined', 'roster', (int)$a['roster_id']);
        if (!$accept) {
            $name = person_name($this->me());
            Rostering::notifyLeaders((int)$a['group_id'], __('%s declined %s on %s', $name, $a['position'], fmt_date($a['service_date'], 'D j M')), '<p>' . e(__('%s can no longer serve as %s for %s on %s. The slot needs to be filled.', $name, $a['position'], $a['title'], fmt_date($a['service_date']))) . '</p><p><a href="' . e(url('/rosters/' . $a['roster_id'], [], true)) . '">' . e(__('Open roster')) . '</a></p>', url('/rosters/' . $a['roster_id']));
        }
        flash('success', $accept ? __('Thanks for serving! You have accepted.') : __('You have declined. Your ministry leader has been told.'));
        back('/me/rosters');
    }

    public function swap(string $id): void
    {
        $a = $this->myAssignment($id);
        $replacement = input_int('replacement_id');
        if ($replacement && Volunteering::gaps($replacement, (int)$a['position_id'], $a['service_date'])) {
            flash('error', __('That person is not currently eligible for this position.'));
            back('/me/rosters');
        }
        if ($replacement && ($clash = Volunteering::clash($replacement, (int)$a['roster_id']))) {
            flash('error', __('That person is already serving at that time: %s', $clash));
            back('/me/rosters');
        }
        if ($replacement && !Volunteering::isApproved($replacement, (int)$a['position_id'])) {
            flash('error', __('That person is not approved for this position.'));
            back('/me/rosters');
        }
        $sid = DB::insert('swap_requests', ['assignment_id' => $a['id'], 'requested_by' => $this->pid(), 'replacement_id' => $replacement, 'reason' => mb_substr((string)input('reason', ''), 0, 255) ?: null, 'status' => $replacement ? 'accepted' : 'open']);
        DB::update('roster_assignments', ['status' => 'swap_requested'], 'id = ?', [$a['id']]);
        $name = person_name($this->me());
        if ($replacement && !setting('swap_requires_approval', '0')) {
            Rostering::completeSwap(DB::row('SELECT s.*, ra.roster_id, ra.position_id, r.group_id, r.service_date, r.title FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id WHERE s.id = ?', [$sid]));
            flash('success', __('Swap arranged — your replacement has been notified.'));
        } else {
            if (!$replacement) {
                foreach (DB::col('SELECT vp.person_id FROM {p}volunteer_positions vp WHERE vp.position_id = ? AND vp.approved_at IS NOT NULL AND vp.person_id <> ?', [$a['position_id'], $this->pid()]) as $other) {
                    if (!Volunteering::gaps((int)$other, (int)$a['position_id'], $a['service_date'])) {
                        Notify::person((int)$other, __('Can you cover %s on %s?', $a['position'], fmt_date($a['service_date'], 'D j M')), '<p>' . e(__('%s is looking for someone to cover %s for %s on %s.', $name, $a['position'], $a['title'], fmt_date($a['service_date']))) . '</p><p><a href="' . e(url('/me/rosters', [], true)) . '">' . e(__("I'll cover it")) . '</a></p>', null, url('/me/rosters'));
                    }
                }
            }
            Rostering::notifyLeaders((int)$a['group_id'], __('Swap requested: %s on %s', $a['position'], fmt_date($a['service_date'], 'D j M')), '<p>' . e(__('%s has requested a swap.', $name)) . '</p>', url('/rosters/swaps'));
            flash('success', __('Swap request sent. You remain rostered until someone covers or your leader confirms.'));
        }
        audit('roster.swap_requested', 'roster', (int)$a['roster_id']);
        Notify::processQueue(10);
        back('/me/rosters');
    }

    public function takeSwap(string $id): void
    {
        $pid = $this->pid();
        $s = DB::row("SELECT s.*, ra.roster_id, ra.position_id, r.group_id, r.service_date, r.title FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id WHERE s.id = ? AND s.status = 'open'", [(int)$id]) ?? abort(404);
        if (!Volunteering::isApproved($pid, (int)$s['position_id']) || ($gaps = Volunteering::gaps($pid, (int)$s['position_id'], $s['service_date']))) {
            flash('error', __('You are not currently eligible for this position.'));
            back('/me/rosters');
        }
        if (DB::val('SELECT 1 FROM {p}unavailability WHERE person_id = ? AND ? BETWEEN start_date AND end_date', [$pid, $s['service_date']])) {
            flash('error', __('You are marked as unavailable on that date.'));
            back('/me/rosters');
        }
        if ($clash = Volunteering::clash($pid, (int)$s['roster_id'])) {
            flash('error', __('You are already serving at that time: %s', $clash));
            back('/me/rosters');
        }
        DB::update('swap_requests', ['replacement_id' => $pid, 'status' => 'accepted'], 'id = ?', [$s['id']]);
        $s['replacement_id'] = $pid;
        if (setting('swap_requires_approval', '0')) {
            Rostering::notifyLeaders((int)$s['group_id'], __('Swap needs approval'), '<p>' . e(__('A volunteer has offered to cover a swap. Please approve it.')) . '</p>', url('/rosters/swaps'));
            flash('success', __('Thank you! Your ministry leader will confirm the swap.'));
        } else {
            Rostering::completeSwap($s);
            flash('success', __('Thank you for covering! You are now rostered.'));
        }
        audit('roster.swap_taken', 'roster', (int)$s['roster_id']);
        back('/me/rosters');
    }

    // Availability -------------------------------------------------------------
    public function availability(): void
    {
        $rows = DB::all('SELECT * FROM {p}unavailability WHERE person_id = ? AND end_date >= CURDATE() ORDER BY start_date', [$this->pid()]);
        view('me/availability', ['title' => __('Availability'), 'rows' => $rows]);
    }

    public function addUnavailable(): void
    {
        $s = input_date('start_date');
        $e = input_date('end_date') ?? $s;
        if (!$s || $e < $s) {
            flash('error', __('Choose a valid date range.'));
            redirect('/me/availability');
        }
        DB::insert('unavailability', ['person_id' => $this->pid(), 'start_date' => $s, 'end_date' => $e, 'reason' => mb_substr((string)input('reason', ''), 0, 200) ?: null]);
        $clashes = DB::all("SELECT a.id, r.title, r.service_date FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id WHERE a.person_id = ? AND a.status IN ('pending','accepted') AND r.service_date BETWEEN ? AND ?", [$this->pid(), $s, $e]);
        flash('success', __('Saved. You will not be suggested for rosters on those dates.'));
        if ($clashes) {
            flash('warning', __('You are already rostered during this time (%s). Decline or request a swap on My rosters.', implode(', ', array_map(fn($c) => fmt_date($c['service_date'], 'j M'), $clashes))));
        }
        redirect('/me/availability');
    }

    public function deleteUnavailable(string $id): void
    {
        DB::run('DELETE FROM {p}unavailability WHERE id = ? AND person_id = ?', [(int)$id, $this->pid()]);
        redirect('/me/availability');
    }

    // Training -----------------------------------------------------------------
    public function training(): void
    {
        $pid = $this->pid();
        $mine = DB::all("SELECT e.*, c.title, c.summary, c.category, c.validity_months, (SELECT COUNT(*) FROM {p}lessons l WHERE l.course_id = c.id) AS lessons, (SELECT COUNT(*) FROM {p}lesson_progress lp WHERE lp.enrolment_id = e.id) AS done FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.person_id = ? AND c.status = 'published' ORDER BY e.status = 'completed', e.due_date IS NULL, e.due_date", [$pid]);
        $available = DB::all("SELECT c.* FROM {p}courses c WHERE c.status = 'published' AND c.self_enrol = 1 AND c.id NOT IN (SELECT course_id FROM {p}enrolments WHERE person_id = ?) ORDER BY c.title", [$pid]);
        view('me/training', ['title' => __('My training'), 'mine' => $mine, 'available' => $available]);
    }

    public function selfEnrol(string $id): void
    {
        $pid = $this->pid();
        $c = DB::row("SELECT * FROM {p}courses WHERE id = ? AND status = 'published' AND self_enrol = 1", [(int)$id]) ?? abort(404);
        DB::run('INSERT IGNORE INTO {p}enrolments (course_id, person_id) VALUES (?, ?)', [$c['id'], $pid]);
        redirect('/me/training/' . $c['id']);
    }

    private function enrolment(int $courseId): array
    {
        $e = DB::row("SELECT e.*, c.title, c.summary, c.pass_mark, c.prerequisite_id, c.certificate_enabled FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.course_id = ? AND e.person_id = ? AND c.status = 'published'", [$courseId, $this->pid()]);
        if (!$e) {
            abort(404, __('You are not enrolled in this course.'));
        }
        return $e;
    }

    public function course(string $id): void
    {
        $e = $this->enrolment((int)$id);
        $course = DB::row('SELECT * FROM {p}courses WHERE id = ?', [$e['course_id']]);
        $prereqOk = Training::prerequisiteMet($course, $this->pid());
        $lessons = DB::all('SELECT l.*, (SELECT 1 FROM {p}lesson_progress lp WHERE lp.enrolment_id = ? AND lp.lesson_id = l.id) AS done FROM {p}lessons l WHERE l.course_id = ? ORDER BY l.sort, l.id', [$e['id'], $e['course_id']]);
        $current = null;
        $lid = input_int('lesson');
        foreach ($lessons as $l) {
            if (($lid && (int)$l['id'] === $lid) || (!$lid && !$l['done'] && !$current)) {
                $current = $l;
            }
        }
        $allDone = !array_filter($lessons, fn($l) => !$l['done']);
        $questions = DB::all('SELECT id, question, options FROM {p}quiz_questions WHERE course_id = ? ORDER BY sort, id', [$e['course_id']]);
        $showQuiz = input('quiz') === '1' || ($allDone && !$current && $questions && $e['status'] !== 'completed');
        $prereq = $course['prerequisite_id'] ? DB::val('SELECT title FROM {p}courses WHERE id = ?', [$course['prerequisite_id']]) : null;
        view('me/course', ['title' => $e['title'], 'e' => $e, 'lessons' => $lessons, 'current' => $showQuiz ? null : $current, 'questions' => $questions, 'showQuiz' => $showQuiz, 'allDone' => $allDone, 'prereqOk' => $prereqOk, 'prereq' => $prereq]);
    }

    public function completeLesson(string $id, string $lesson): void
    {
        $e = $this->enrolment((int)$id);
        $course = DB::row('SELECT * FROM {p}courses WHERE id = ?', [$e['course_id']]);
        if (!Training::prerequisiteMet($course, $this->pid())) {
            abort(403);
        }
        if (DB::val('SELECT 1 FROM {p}lessons WHERE id = ? AND course_id = ?', [(int)$lesson, $e['course_id']])) {
            DB::run('INSERT IGNORE INTO {p}lesson_progress (enrolment_id, lesson_id) VALUES (?, ?)', [$e['id'], (int)$lesson]);
        }
        if (Training::evaluate((int)$e['id'])) {
            flash('success', __('Course complete — well done!'));
        }
        redirect('/me/training/' . $e['course_id']);
    }

    public function submitQuiz(string $id): void
    {
        $e = $this->enrolment((int)$id);
        if ($e['status'] === 'completed') {
            redirect('/me/training/' . $e['course_id']);
        }
        $qs = DB::all('SELECT * FROM {p}quiz_questions WHERE course_id = ?', [$e['course_id']]);
        $answers = $_POST['q'] ?? [];
        $correct = 0;
        foreach ($qs as $q) {
            if (isset($answers[$q['id']]) && (int)$answers[$q['id']] === (int)$q['correct_index']) {
                $correct++;
            }
        }
        $score = $qs ? (int)round($correct / count($qs) * 100) : 100;
        $passed = $score >= (int)$e['pass_mark'];
        DB::update('enrolments', ['score' => $score, 'attempts' => (int)$e['attempts'] + 1, 'status' => $passed ? 'in_progress' : 'failed'], 'id = ?', [$e['id']]);
        if ($passed && Training::evaluate((int)$e['id'])) {
            flash('success', __('You scored %d%% — course complete!', $score));
        } elseif ($passed) {
            flash('success', __('You scored %d%%. Finish the remaining lessons to complete the course.', $score));
        } else {
            flash('error', __('You scored %d%%. The pass mark is %d%%. Review the lessons and try again.', $score, (int)$e['pass_mark']));
        }
        redirect('/me/training/' . $e['course_id']);
    }

    public function certificate(string $id): void
    {
        $e = DB::row("SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.id = ? AND e.status = 'completed' AND e.certificate_code IS NOT NULL", [(int)$id]) ?? abort(404);
        if ((int)$e['person_id'] !== person_id() && !can('training.reports')) {
            abort(403);
        }
        $p = DB::row('SELECT * FROM {p}people WHERE id = ?', [$e['person_id']]);
        view('me/certificate', ['title' => __('Certificate'), 'e' => $e, 'p' => $p]);
    }

    // Groups & events ----------------------------------------------------------
    public function groups(): void
    {
        $pid = $this->pid();
        $mine = DB::all('SELECT g.*, gm.member_role FROM {p}group_members gm JOIN {p}groups g ON g.id = gm.group_id WHERE gm.person_id = ? AND g.active = 1 ORDER BY g.name', [$pid]);
        $open = DB::all('SELECT g.* FROM {p}groups g WHERE g.active = 1 AND g.is_open = 1 AND g.id NOT IN (SELECT group_id FROM {p}group_members WHERE person_id = ?) ORDER BY g.name', [$pid]);
        view('me/groups', ['title' => __('My groups'), 'mine' => $mine, 'open' => $open]);
    }

    public function joinGroup(string $id): void
    {
        $g = DB::row('SELECT * FROM {p}groups WHERE id = ? AND is_open = 1 AND active = 1', [(int)$id]) ?? abort(404);
        DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id) VALUES (?, ?)', [$g['id'], $this->pid()]);
        Rostering::notifyLeaders((int)$g['id'], __('%s joined %s', person_name($this->me()), $g['name']), '<p>' . e(__('%s has joined your group through Affreso.', person_name($this->me()))) . '</p>', url('/groups/' . $g['id']));
        flash('success', __('You have joined %s.', $g['name']));
        redirect('/me/groups');
    }

    public function events(): void
    {
        $pid = $this->pid();
        $events = DB::all("SELECT e.*, (SELECT status FROM {p}event_registrations r WHERE r.event_id = e.id AND r.person_id = ? AND r.status <> 'cancelled' LIMIT 1) AS my_status, (SELECT COALESCE(SUM(1 + guests), 0) FROM {p}event_registrations r WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken FROM {p}events e WHERE e.status = 'scheduled' AND e.end_at >= NOW() ORDER BY e.start_at LIMIT 60", [$pid]);
        $forms = [];
        foreach ($events as $ev) {
            if ($f = Events::form((int)$ev['id'])) {
                $forms[(int)$ev['id']] = $f;
            }
        }
        view('me/events', ['title' => __('Events'), 'events' => $events, 'forms' => $forms]);
    }

    public function registerEvent(string $id): void
    {
        $p = $this->me();
        $ev = DB::row("SELECT * FROM {p}events WHERE id = ? AND status = 'scheduled'", [(int)$id]) ?? abort(404);
        [$ok, $msg] = Events::register($ev, ['person_id' => (int)$p['id'], 'name' => person_name($p), 'email' => $p['email'], 'phone' => $p['mobile'], 'guests' => max(0, min(10, (int)input_int('guests', 0)))]);
        flash($ok ? 'success' : 'error', $msg);
        redirect('/me/events');
    }

    public function cancelEvent(string $id): void
    {
        $r = DB::row("SELECT * FROM {p}event_registrations WHERE event_id = ? AND person_id = ? AND status IN ('registered','waitlisted')", [(int)$id, $this->pid()]);
        if ($r) {
            Events::cancel((int)$r['id']);
            flash('success', __('Your registration has been cancelled.'));
        }
        redirect('/me/events');
    }

    public function giving(): void
    {
        $rows = DB::all('SELECT d.*, f.name AS fund FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.person_id = ? ORDER BY COALESCE(d.received_on, d.created_at) DESC', [$this->pid()]);
        $fy = (int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1;
        $fyTotal = (int)DB::val("SELECT COALESCE(SUM(amount_cents), 0) FROM {p}donations WHERE person_id = ? AND status = 'paid' AND COALESCE(received_on, DATE(created_at)) BETWEEN ? AND ?", [$this->pid(), "$fy-07-01", ($fy + 1) . '-06-30']);
        $recurring = DB::all("SELECT rg.*, f.name AS fund FROM {p}recurring_gifts rg LEFT JOIN {p}funds f ON f.id = rg.fund_id WHERE rg.person_id = ? AND rg.status IN ('active','past_due','pending') ORDER BY rg.id DESC", [$this->pid()]);
        $gn = DB::val('SELECT giving_number FROM {p}people WHERE id = ?', [$this->pid()]) ?: 'G' . $this->pid();
        $pledges = DB::all("SELECT pl.*, c.name AS campaign, c.fund_id, c.starts_on, c.ends_on, c.id AS cid FROM {p}pledges pl JOIN {p}campaigns c ON c.id = pl.campaign_id WHERE pl.person_id = ? AND pl.status = 'active' ORDER BY c.ends_on DESC", [$this->pid()]);
        foreach ($pledges as &$pl) {
            $pl['given'] = CampaignsController::givenFor(['fund_id' => $pl['fund_id'], 'starts_on' => $pl['starts_on'], 'ends_on' => $pl['ends_on']], $pl);
        }
        unset($pl);
        $campaigns = DB::all("SELECT * FROM {p}campaigns WHERE status = 'active' AND ends_on >= CURDATE() ORDER BY ends_on");
        view('me/giving', ['title' => __('My giving'), 'rows' => $rows, 'fy' => $fy, 'fyTotal' => $fyTotal, 'recurring' => $recurring, 'givingNumber' => $gn, 'pledges' => $pledges, 'campaigns' => $campaigns]);
    }

    // Privacy ------------------------------------------------------------------
    public function privacy(): void
    {
        $p = $this->me();
        $log = DB::all('SELECT * FROM {p}consent_records WHERE person_id = ? ORDER BY id DESC LIMIT 20', [$p['id']]);
        view('me/privacy', ['title' => __('Privacy & consent'), 'p' => $p, 'log' => $log]);
    }

    public function savePrivacy(): void
    {
        $p = $this->me();
        if (input('request')) {
            $type = in_array(input('request'), ['access', 'correction', 'deletion', 'complaint'], true) ? input('request') : 'access';
            $rid = DB::insert('privacy_requests', ['person_id' => $p['id'], 'request_type' => $type, 'details' => mb_substr((string)input('details', ''), 0, 4000) ?: null, 'received_on' => date('Y-m-d'), 'due_on' => date('Y-m-d', strtotime('+30 days'))]);
            Notify::users(users_with_permission('privacy.manage'), __('New privacy request (%s)', $type), '<p>' . e(__('%s has lodged a privacy %s request. Please respond within 30 days.', person_name($p), $type)) . '</p>', url('/admin/privacy'));
            audit('privacy.request_lodged', 'privacy_request', $rid);
            flash('success', __('Your request has been sent to our privacy officer. We aim to respond within 30 days.'));
            redirect('/me/privacy');
        }
        $c = input_bool('consent_contact');
        $ph = input_bool('consent_photo');
        $dir = input_bool('directory_visible');
        foreach (['contact' => [$p['consent_contact'], $c], 'photo' => [$p['consent_photo'], $ph], 'directory' => [$p['directory_visible'], $dir]] as $type => [$old, $new]) {
            if ((int)$old !== $new) {
                DB::insert('consent_records', ['person_id' => $p['id'], 'consent_type' => $type, 'granted' => $new, 'recorded_by' => Auth::id(), 'method' => 'self-service']);
            }
        }
        DB::update('people', ['consent_contact' => $c, 'consent_photo' => $ph, 'directory_visible' => $dir, 'notify_email' => input_bool('notify_email'), 'notify_sms' => input_bool('notify_sms'), 'consent_updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$p['id']]);
        audit('person.consent_self_updated', 'person', (int)$p['id']);
        flash('success', __('Your preferences have been saved.'));
        redirect('/me/privacy');
    }

    public function export(): void
    {
        $pid = $this->pid();
        audit('privacy.self_export', 'person', $pid);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="my-church-data.json"');
        echo json_encode(PeopleController::personDataBundle($pid, false), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Volunteering -------------------------------------------------------------
    public function volunteerForm(): void
    {
        $positions = DB::all("SELECT po.*, g.name AS group_name, g.description AS group_desc FROM {p}positions po JOIN {p}groups g ON g.id = po.group_id WHERE g.active = 1 ORDER BY g.name, po.name");
        $existing = DB::all('SELECT * FROM {p}volunteer_applications WHERE person_id = ? ORDER BY id DESC', [$this->pid()]);
        view('me/volunteer', ['title' => __('Volunteer with us'), 'positions' => $positions, 'existing' => $existing]);
    }

    public function volunteerApply(): void
    {
        $ids = input_ids('position_ids');
        if (!$ids) {
            flash('error', __('Choose at least one area you would like to serve in.'));
            redirect('/me/volunteer');
        }
        $ids = array_map('intval', DB::col('SELECT id FROM {p}positions WHERE id IN (' . DB::in($ids) . ')', $ids));
        $aid = DB::insert('volunteer_applications', ['person_id' => $this->pid(), 'position_ids' => implode(',', $ids), 'skills' => mb_substr((string)input('skills', ''), 0, 2000) ?: null, 'availability' => mb_substr((string)input('availability', ''), 0, 2000) ?: null, 'experience' => mb_substr((string)input('experience', ''), 0, 2000) ?: null]);
        $groups = array_map('intval', DB::col('SELECT DISTINCT group_id FROM {p}positions WHERE id IN (' . DB::in($ids) . ')', $ids));
        $name = person_name($this->me());
        foreach ($groups as $gid) {
            Rostering::notifyLeaders($gid, __('New volunteer application: %s', $name), '<p>' . e(__('%s would like to serve in your team. Please review their application.', $name)) . '</p><p><a href="' . e(url('/volunteers/applications/' . $aid, [], true)) . '">' . e(__('Review application')) . '</a></p>', url('/volunteers/applications/' . $aid));
        }
        Notify::users(users_with_permission('volunteers.manage'), __('New volunteer application: %s', $name), '<p>' . e(__('A new volunteer application is waiting for review.')) . '</p>', url('/volunteers/applications/' . $aid));
        audit('volunteer_application.submitted', 'volunteer_application', $aid);
        flash('success', __('Thank you for offering to serve! A ministry leader will review your application.'));
        redirect('/me/volunteer');
    }

    public function directory(): void
    {
        $q = (string)input('q', '');
        $rows = DB::all("SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.email, p.mobile, h.name AS household FROM {p}people p LEFT JOIN {p}households h ON h.id = p.household_id WHERE p.directory_visible = 1 AND p.archived_at IS NULL AND p.household_role <> 'child' AND p.consent_contact = 1 AND CONCAT(p.first_name, ' ', p.last_name, ' ', COALESCE(p.preferred_name, '')) LIKE ? ORDER BY p.last_name, p.first_name LIMIT 500", ['%' . $q . '%']);
        view('me/directory', ['title' => __('Church directory'), 'rows' => $rows, 'q' => $q]);
    }
}
