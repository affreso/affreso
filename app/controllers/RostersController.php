<?php
declare(strict_types=1);

final class RostersController
{
    private function find(int|string $id): array
    {
        return DB::row('SELECT r.*, g.name AS group_name FROM {p}rosters r JOIN {p}groups g ON g.id = r.group_id WHERE r.id = ?', [(int)$id]) ?? abort(404);
    }

    private function manageableGroups(): array
    {
        if (can('rosters.manage')) {
            return array_column(DB::all("SELECT id, name FROM {p}groups WHERE active = 1 AND group_type <> 'small_group' ORDER BY name"), 'name', 'id');
        }
        $led = led_group_ids();
        return $led ? array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 AND id IN (' . DB::in($led) . ') ORDER BY name', $led), 'name', 'id') : [];
    }

    private function authorizeManage(array $r): void
    {
        if (!can_manage_roster_for((int)$r['group_id'])) {
            abort(403);
        }
    }

    public function index(): void
    {
        $canAll = can('rosters.view') || can('rosters.manage');
        $led = led_group_ids();
        if (!$canAll && !$led) {
            redirect('/me/rosters');
        }
        $group = input_int('group');
        $from = input_date('from') ?? date('Y-m-d');
        $where = ['r.service_date >= ?'];
        $params = [$from];
        if (!$canAll) {
            $where[] = 'r.group_id IN (' . DB::in($led) . ')';
            $params = array_merge($params, $led);
        }
        if ($group) {
            $where[] = 'r.group_id = ?';
            $params[] = $group;
        }
        if ($c = Campus::current()) {
            $where[] = '(r.campus_id = ? OR r.campus_id IS NULL)';
            $params[] = $c;
        }
        $rows = DB::all("SELECT r.*, g.name AS group_name, (SELECT COUNT(*) FROM {p}roster_assignments a WHERE a.roster_id = r.id) AS slots, (SELECT COUNT(*) FROM {p}roster_assignments a WHERE a.roster_id = r.id AND a.status = 'accepted') AS accepted, (SELECT COUNT(*) FROM {p}roster_assignments a WHERE a.roster_id = r.id AND a.status IN ('open','declined')) AS unfilled FROM {p}rosters r JOIN {p}groups g ON g.id = r.group_id WHERE " . implode(' AND ', $where) . ' ORDER BY r.service_date, r.start_time, g.name LIMIT 200', $params);
        $groups = $canAll ? array_column(DB::all("SELECT id, name FROM {p}groups WHERE active = 1 AND group_type <> 'small_group' ORDER BY name"), 'name', 'id') : $this->manageableGroups();
        $swaps = (int)DB::val("SELECT COUNT(*) FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id WHERE s.status IN ('open','accepted') AND r.service_date >= CURDATE()" . ($canAll ? '' : ' AND r.group_id IN (' . DB::in($led) . ')'), $canAll ? [] : $led);
        view('rosters/index', ['title' => __('Rosters'), 'rows' => $rows, 'groups' => $groups, 'group' => $group, 'from' => $from, 'canCreate' => (bool)$this->manageableGroups(), 'swaps' => $swaps]);
    }

    public function create(): void
    {
        $groups = $this->manageableGroups();
        if (!$groups) {
            abort(403);
        }
        $gid = input_int('group');
        $positions = $gid && isset($groups[$gid]) ? DB::all('SELECT * FROM {p}positions WHERE group_id = ? ORDER BY name', [$gid]) : [];
        view('rosters/create', ['title' => __('New roster'), 'groups' => $groups, 'gid' => $gid, 'positions' => $positions]);
    }

    public function store(): void
    {
        $gid = input_int('group_id');
        if (!$gid || !can_manage_roster_for($gid)) {
            abort(403);
        }
        $date = input_date('service_date');
        if (!$date) {
            flash('error', __('Choose a date.'));
            back('/rosters/new');
        }
        $groupName = (string)DB::val('SELECT name FROM {p}groups WHERE id = ?', [$gid]);
        $repeat = max(1, min(12, (int)input_int('repeat_weeks', 1)));
        $first = null;
        for ($w = 0; $w < $repeat; $w++) {
            $d = date('Y-m-d', strtotime($date . " +$w week"));
            $rid = DB::insert('rosters', ['campus_id' => DB::val('SELECT campus_id FROM {p}groups WHERE id = ?', [$gid]), 'group_id' => $gid, 'title' => mb_substr((string)input('title', '') ?: $groupName, 0, 200), 'service_date' => $d, 'start_time' => input('start_time') ?: null, 'end_time' => input('end_time') ?: null, 'notes' => input('notes') ?: null, 'created_by' => Auth::id()]);
            $first ??= $rid;
            foreach (DB::all('SELECT id FROM {p}positions WHERE group_id = ?', [$gid]) as $po) {
                $qty = max(0, min(20, (int)input_int('qty_' . $po['id'], 0)));
                for ($i = 0; $i < $qty; $i++) {
                    DB::insert('roster_assignments', ['roster_id' => $rid, 'position_id' => $po['id']]);
                }
            }
            audit('roster.created', 'roster', $rid);
        }
        flash('success', $repeat > 1 ? __('%d weekly rosters created. Fill the slots, then publish.', $repeat) : __('Roster created. Fill the slots, then publish.'));
        redirect('/rosters/' . $first);
    }

    public function show(string $id): void
    {
        $r = $this->find($id);
        $manage = can_manage_roster_for((int)$r['group_id']);
        if (!$manage && !can('rosters.view')) {
            abort(403);
        }
        $slots = DB::all('SELECT a.*, po.name AS position, po.requires_wwcc, p.first_name, p.last_name, p.preferred_name FROM {p}roster_assignments a JOIN {p}positions po ON po.id = a.position_id LEFT JOIN {p}people p ON p.id = a.person_id WHERE a.roster_id = ? ORDER BY po.name, a.id', [$r['id']]);
        $suggest = [];
        if ($manage) {
            foreach (array_unique(array_column($slots, 'position_id')) as $posId) {
                $suggest[$posId] = Volunteering::suggest((int)$r['id'], (int)$posId);
            }
        }
        $positions = array_column(DB::all('SELECT id, name FROM {p}positions WHERE group_id = ? ORDER BY name', [$r['group_id']]), 'name', 'id');
        $siblings = DB::all("SELECT r.id, r.title, g.name AS group_name FROM {p}rosters r JOIN {p}groups g ON g.id = r.group_id WHERE r.service_date = ? AND r.id <> ? ORDER BY g.name", [$r['service_date'], $r['id']]);
        view('rosters/show', ['title' => $r['title'], 'r' => $r, 'slots' => $slots, 'suggest' => $suggest, 'manage' => $manage, 'positions' => $positions, 'siblings' => $siblings]);
    }

    public function update(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        DB::update('rosters', ['title' => mb_substr((string)input('title', $r['title']), 0, 200), 'service_date' => input_date('service_date') ?? $r['service_date'], 'start_time' => input('start_time') ?: null, 'end_time' => input('end_time') ?: null, 'notes' => input('notes') ?: null], 'id = ?', [$r['id']]);
        audit('roster.updated', 'roster', (int)$r['id']);
        flash('success', __('Roster details saved.'));
        redirect('/rosters/' . $r['id']);
    }

    public function addSlots(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        $posId = input_int('position_id');
        if (!$posId || !DB::val('SELECT 1 FROM {p}positions WHERE id = ? AND group_id = ?', [$posId, $r['group_id']])) {
            back('/rosters/' . $r['id']);
        }
        $qty = max(1, min(20, (int)input_int('qty', 1)));
        for ($i = 0; $i < $qty; $i++) {
            DB::insert('roster_assignments', ['roster_id' => $r['id'], 'position_id' => $posId]);
        }
        redirect('/rosters/' . $r['id']);
    }

    private function invite(array $r, int $assignmentId): void
    {
        $a = DB::row('SELECT a.*, po.name AS position FROM {p}roster_assignments a JOIN {p}positions po ON po.id = a.position_id WHERE a.id = ?', [$assignmentId]);
        if (!$a || !$a['person_id']) {
            return;
        }
        $when = fmt_date($r['service_date'], 'l j F') . ($r['start_time'] ? ' ' . fmt_time($r['start_time']) : '');
        $link = url('/me/rosters', [], true);
        Notify::person((int)$a['person_id'], __('Serving invitation: %s, %s', $a['position'], fmt_date($r['service_date'], 'D j M')), '<p>' . e(__('You have been rostered as %s for %s on %s.', $a['position'], $r['title'], $when)) . '</p>' . ($r['notes'] ? '<p>' . nl2br(e($r['notes'])) . '</p>' : '') . '<p><a href="' . e($link) . '">' . e(__('Accept or decline')) . '</a></p>', __('Rostered: %s, %s. Please accept or decline in Affreso: %s', $a['position'], $when, $link), url('/me/rosters'));
    }

    public function assign(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        $aid = input_int('assignment_id');
        $pid = input_int('person_id');
        $a = DB::row('SELECT * FROM {p}roster_assignments WHERE id = ? AND roster_id = ?', [$aid, $r['id']]) ?? abort(404);
        if (!$pid) {
            redirect('/rosters/' . $r['id']);
        }
        if (!Volunteering::isApproved($pid, (int)$a['position_id'])) {
            flash('error', __('That person is not approved for this position.'));
            redirect('/rosters/' . $r['id']);
        }
        $gaps = Volunteering::gaps($pid, (int)$a['position_id'], $r['service_date']);
        if ($clash = Volunteering::clash($pid, (int)$r['id'])) {
            $gaps[] = __('Already serving: %s', $clash);
        }
        if (DB::val('SELECT 1 FROM {p}unavailability WHERE person_id = ? AND ? BETWEEN start_date AND end_date', [$pid, $r['service_date']])) {
            $gaps[] = __('Marked unavailable on this date');
        }
        if ($gaps) {
            flash('error', __('Cannot roster this person: %s', implode('; ', $gaps)));
            redirect('/rosters/' . $r['id']);
        }
        if ($busy = CalendarSync::busyReason($pid, $r['service_date'], $r['start_time'], $r['end_time'])) {
            flash('warning', __('Rostered, but note: %s. They can decline if it clashes.', $busy));
        }
        DB::update('roster_assignments', ['person_id' => $pid, 'status' => 'pending', 'responded_at' => null, 'reminded_at' => null], 'id = ?', [$a['id']]);
        audit('roster.assigned', 'roster', (int)$r['id'], ['assignment_id' => $a['id'], 'person_id' => $pid]);
        if ($r['status'] === 'published') {
            $this->invite($r, (int)$a['id']);
            Notify::processQueue(10);
        }
        redirect('/rosters/' . $r['id']);
    }

    public function autofill(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        $filled = 0;
        $used = array_map('intval', DB::col("SELECT person_id FROM {p}roster_assignments WHERE roster_id = ? AND person_id IS NOT NULL AND status IN ('pending','accepted')", [$r['id']]));
        $cache = [];
        foreach (DB::all("SELECT * FROM {p}roster_assignments WHERE roster_id = ? AND (status = 'open' OR (status = 'declined')) ORDER BY id", [$r['id']]) as $slot) {
            $cands = $cache[$slot['position_id']] ??= Volunteering::suggest((int)$r['id'], (int)$slot['position_id']);
            $declinedBy = $slot['status'] === 'declined' ? (int)$slot['person_id'] : 0;
            foreach ($cands as $c) {
                if ($c['eligible'] && !$c['burnout'] && !in_array($c['person_id'], $used, true) && $c['person_id'] !== $declinedBy) {
                    DB::update('roster_assignments', ['person_id' => $c['person_id'], 'status' => 'pending', 'responded_at' => null], 'id = ?', [$slot['id']]);
                    $used[] = $c['person_id'];
                    $filled++;
                    if ($r['status'] === 'published') {
                        $this->invite($r, (int)$slot['id']);
                    }
                    break;
                }
            }
        }
        audit('roster.autofilled', 'roster', (int)$r['id'], ['filled' => $filled]);
        flash($filled ? 'success' : 'warning', $filled ? __('Suggested %d volunteer(s) based on availability, training, preferences and how often they have served. Review before publishing.', $filled) : __('No eligible volunteers were available for the open slots.'));
        redirect('/rosters/' . $r['id']);
    }

    public function publish(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        DB::update('rosters', ['status' => 'published', 'published_by' => Auth::id()], 'id = ?', [$r['id']]);
        $r['status'] = 'published';
        $n = 0;
        foreach (DB::col("SELECT id FROM {p}roster_assignments WHERE roster_id = ? AND person_id IS NOT NULL AND status = 'pending'", [$r['id']]) as $aid) {
            $this->invite($r, (int)$aid);
            $n++;
        }
        Notify::processQueue(25);
        audit('roster.published', 'roster', (int)$r['id'], ['invited' => $n]);
        flash('success', __('Roster published. %d invitation(s) sent.', $n));
        redirect('/rosters/' . $r['id']);
    }

    public function copy(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        $date = input_date('service_date') ?? date('Y-m-d', strtotime($r['service_date'] . ' +7 days'));
        $nid = DB::insert('rosters', ['campus_id' => $r['campus_id'] ?? null, 'group_id' => $r['group_id'], 'title' => $r['title'], 'service_date' => $date, 'start_time' => $r['start_time'], 'end_time' => $r['end_time'], 'notes' => $r['notes'], 'created_by' => Auth::id()]);
        foreach (DB::col('SELECT position_id FROM {p}roster_assignments WHERE roster_id = ?', [$r['id']]) as $posId) {
            DB::insert('roster_assignments', ['roster_id' => $nid, 'position_id' => $posId]);
        }
        audit('roster.copied', 'roster', $nid, ['from' => $r['id']]);
        flash('success', __('Copied to %s with empty slots.', fmt_date($date)));
        redirect('/rosters/' . $nid);
    }

    public function delete(string $id): void
    {
        $r = $this->find($id);
        $this->authorizeManage($r);
        foreach (DB::col("SELECT person_id FROM {p}roster_assignments WHERE roster_id = ? AND person_id IS NOT NULL AND status IN ('pending','accepted')", [$r['id']]) as $pid) {
            if ($r['status'] === 'published' && $r['service_date'] >= date('Y-m-d')) {
                Notify::person((int)$pid, __('Roster cancelled: %s', $r['title']), '<p>' . e(__('The roster for %s on %s has been cancelled. You no longer need to serve.', $r['title'], fmt_date($r['service_date']))) . '</p>');
            }
        }
        DB::run('DELETE s FROM {p}swap_requests s JOIN {p}roster_assignments a ON a.id = s.assignment_id WHERE a.roster_id = ?', [$r['id']]);
        DB::run('DELETE FROM {p}roster_assignments WHERE roster_id = ?', [$r['id']]);
        DB::run('DELETE FROM {p}rosters WHERE id = ?', [$r['id']]);
        audit('roster.deleted', 'roster', (int)$r['id']);
        flash('success', __('Roster deleted.'));
        redirect('/rosters');
    }

    private function slot(int|string $id): array
    {
        $a = DB::row('SELECT a.*, r.group_id, r.id AS rid FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id WHERE a.id = ?', [(int)$id]) ?? abort(404);
        if (!can_manage_roster_for((int)$a['group_id'])) {
            abort(403);
        }
        return $a;
    }

    public function clearAssignment(string $id): void
    {
        $a = $this->slot($id);
        DB::update('roster_assignments', ['person_id' => null, 'status' => 'open', 'responded_at' => null], 'id = ?', [$a['id']]);
        audit('roster.cleared', 'roster', (int)$a['rid'], ['assignment_id' => $a['id']]);
        redirect('/rosters/' . $a['rid']);
    }

    public function removeSlot(string $id): void
    {
        $a = $this->slot($id);
        DB::run('DELETE FROM {p}swap_requests WHERE assignment_id = ?', [$a['id']]);
        DB::run('DELETE FROM {p}roster_assignments WHERE id = ?', [$a['id']]);
        redirect('/rosters/' . $a['rid']);
    }

    public function swaps(): void
    {
        $canAll = can('rosters.manage');
        $led = led_group_ids();
        if (!$canAll && !$led) {
            abort(403);
        }
        $rows = DB::all("SELECT s.*, r.id AS roster_id, r.title, r.service_date, po.name AS position, p1.first_name AS f1, p1.last_name AS l1, p2.first_name AS f2, p2.last_name AS l2 FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions po ON po.id = ra.position_id JOIN {p}people p1 ON p1.id = s.requested_by LEFT JOIN {p}people p2 ON p2.id = s.replacement_id WHERE s.status IN ('open','accepted') AND r.service_date >= CURDATE()" . ($canAll ? '' : ' AND r.group_id IN (' . DB::in($led) . ')') . ' ORDER BY r.service_date', $canAll ? [] : $led);
        view('rosters/swaps', ['title' => __('Swap requests'), 'rows' => $rows]);
    }

    public function decideSwap(string $id): void
    {
        $s = DB::row('SELECT s.*, ra.roster_id, ra.position_id, r.group_id, r.service_date, r.title FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id WHERE s.id = ?', [(int)$id]) ?? abort(404);
        if (!can_manage_roster_for((int)$s['group_id'])) {
            abort(403);
        }
        if (input('decision') === 'approve' && $s['replacement_id']) {
            if ($gaps = Volunteering::gaps((int)$s['replacement_id'], (int)$s['position_id'], $s['service_date'])) {
                flash('error', __('The replacement is not eligible: %s', implode('; ', $gaps)));
                redirect('/rosters/swaps');
            }
            if ($clash = Volunteering::clash((int)$s['replacement_id'], (int)$s['roster_id'])) {
                flash('error', __('The replacement is already serving at that time: %s', $clash));
                redirect('/rosters/swaps');
            }
            Rostering::completeSwap($s);
            flash('success', __('Swap approved.'));
        } else {
            DB::update('swap_requests', ['status' => 'cancelled'], 'id = ?', [$s['id']]);
            DB::run("UPDATE {p}roster_assignments SET status = 'accepted' WHERE id = ? AND status = 'swap_requested'", [$s['assignment_id']]);
            flash('success', __('Swap request closed. The original volunteer remains rostered.'));
        }
        audit('roster.swap_' . (input('decision') === 'approve' ? 'approved' : 'cancelled'), 'roster', (int)$s['roster_id']);
        redirect('/rosters/swaps');
    }
}
