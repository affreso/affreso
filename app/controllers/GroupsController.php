<?php
declare(strict_types=1);

final class GroupsController
{
    public static function types(): array
    {
        return ['team' => __('Ministry team'), 'small_group' => __('Small group'), 'ministry' => __('Ministry area')];
    }

    private function find(int|string $id): array
    {
        return DB::row('SELECT * FROM {p}groups WHERE id = ?', [(int)$id]) ?? abort(404);
    }

    private function authorizeView(array $g): void
    {
        $member = person_id() && DB::val('SELECT 1 FROM {p}group_members WHERE group_id = ? AND person_id = ?', [$g['id'], person_id()]);
        if (!can('groups.view') && !$member && !leads_group((int)$g['id'])) {
            abort(403);
        }
    }

    private function authorizeManage(int $gid): void
    {
        if (!can_manage_group($gid)) {
            abort(403);
        }
    }

    public function index(): void
    {
        $led = led_group_ids();
        if (!can('groups.view') && !$led) {
            redirect('/me/groups');
        }
        $type = (string)input('type', '');
        $where = 'g.active = 1';
        $params = [];
        if (isset(self::types()[$type])) {
            $where .= ' AND g.group_type = ?';
            $params[] = $type;
        }
        [$cw, $cp] = Campus::where('g');
        $where .= $cw;
        $params = array_merge($params, $cp);
        if (!can('groups.view')) {
            $where .= ' AND g.id IN (' . DB::in($led) . ')';
            $params = array_merge($params, $led);
        }
        $rows = DB::all("SELECT g.*, (SELECT COUNT(*) FROM {p}group_members gm WHERE gm.group_id = g.id) AS members, (SELECT GROUP_CONCAT(CONCAT(COALESCE(p.preferred_name, p.first_name), ' ', p.last_name) SEPARATOR ', ') FROM {p}group_members gm JOIN {p}people p ON p.id = gm.person_id WHERE gm.group_id = g.id AND gm.member_role = 'leader') AS leaders FROM {p}groups g WHERE $where ORDER BY g.group_type, g.name", $params);
        view('groups/index', ['title' => __('Ministries & groups'), 'rows' => $rows, 'type' => $type]);
    }

    public function create(): void
    {
        view('groups/form', ['title' => __('New group'), 'g' => ['group_type' => input('type', 'team'), 'active' => 1]]);
    }

    private function data(): array
    {
        $t = (string)input('group_type', 'team');
        return ['name' => mb_substr((string)input('name', ''), 0, 150), 'group_type' => isset(self::types()[$t]) ? $t : 'team', 'description' => input('description') ?: null, 'meeting_schedule' => mb_substr((string)input('meeting_schedule', ''), 0, 150) ?: null, 'location' => mb_substr((string)input('location', ''), 0, 150) ?: null, 'is_open' => input_bool('is_open'), 'child_related' => input_bool('child_related'), 'active' => 1, 'campus_id' => Campus::fromInput()];
    }

    public function store(): void
    {
        $d = $this->data();
        if ($d['name'] === '') {
            flash('error', __('Name is required.'));
            back('/groups/new');
        }
        $id = DB::insert('groups', $d);
        audit('group.created', 'group', $id);
        redirect('/groups/' . $id);
    }

    public function show(string $id): void
    {
        $g = $this->find($id);
        $this->authorizeView($g);
        $manage = can_manage_group((int)$g['id']);
        $members = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.email, p.mobile, gm.member_role, gm.joined_at FROM {p}group_members gm JOIN {p}people p ON p.id = gm.person_id WHERE gm.group_id = ? AND p.archived_at IS NULL ORDER BY FIELD(gm.member_role, "leader", "assistant", "member"), p.last_name', [$g['id']]);
        $meetings = DB::all('SELECT m.*, (SELECT COUNT(*) FROM {p}group_attendance a WHERE a.meeting_id = m.id AND a.present = 1) AS present FROM {p}group_meetings m WHERE m.group_id = ? ORDER BY m.meeting_date DESC LIMIT 12', [$g['id']]);
        $positions = DB::all('SELECT po.*, (SELECT COUNT(*) FROM {p}volunteer_positions vp WHERE vp.position_id = po.id AND vp.approved_at IS NOT NULL) AS volunteers FROM {p}positions po WHERE po.group_id = ? ORDER BY po.name', [$g['id']]);
        foreach ($positions as &$po) {
            $po['courses'] = array_map('intval', DB::col('SELECT course_id FROM {p}position_courses WHERE position_id = ?', [$po['id']]));
        }
        unset($po);
        $courses = array_column(DB::all("SELECT id, title FROM {p}courses WHERE status = 'published' ORDER BY title"), 'title', 'id');
        $rosters = DB::all('SELECT * FROM {p}rosters WHERE group_id = ? AND service_date >= CURDATE() ORDER BY service_date LIMIT 6', [$g['id']]);
        view('groups/show', ['title' => $g['name'], 'g' => $g, 'members' => $members, 'meetings' => $meetings, 'positions' => $positions, 'courses' => $courses, 'manage' => $manage, 'rosters' => $rosters, 'canEdit' => can('groups.manage')]);
    }

    public function update(string $id): void
    {
        $g = $this->find($id);
        if (!can('groups.manage')) {
            abort(403);
        }
        $d = $this->data();
        $d['active'] = input('archive') ? 0 : 1;
        DB::update('groups', $d, 'id = ?', [$g['id']]);
        audit('group.updated', 'group', (int)$g['id']);
        flash('success', $d['active'] ? __('Group saved.') : __('Group archived.'));
        redirect($d['active'] ? '/groups/' . $g['id'] : '/groups');
    }

    public function addMember(string $id): void
    {
        $g = $this->find($id);
        $this->authorizeManage((int)$g['id']);
        $role = in_array(input('member_role'), ['member', 'assistant', 'leader'], true) ? input('member_role') : 'member';
        if ($role !== 'member' && !can('groups.manage')) {
            $role = 'member';
        }
        foreach (input_ids('person_ids') ?: [input_int('person_id')] as $pid) {
            if (!$pid) {
                continue;
            }
            DB::run('INSERT INTO {p}group_members (group_id, person_id, member_role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE member_role = VALUES(member_role)', [$g['id'], $pid, $role]);
            audit('group.member_added', 'group', (int)$g['id'], ['person_id' => $pid, 'role' => $role]);
        }
        flash('success', __('Group membership updated.'));
        redirect('/groups/' . $g['id']);
    }

    public function removeMember(string $id, string $pid): void
    {
        $g = $this->find($id);
        $this->authorizeManage((int)$g['id']);
        DB::run('DELETE FROM {p}group_members WHERE group_id = ? AND person_id = ?', [$g['id'], (int)$pid]);
        audit('group.member_removed', 'group', (int)$g['id'], ['person_id' => (int)$pid]);
        redirect('/groups/' . $g['id']);
    }

    public function addMeeting(string $id): void
    {
        $g = $this->find($id);
        $this->authorizeManage((int)$g['id']);
        $date = input_date('meeting_date') ?? date('Y-m-d');
        $mid = DB::insert('group_meetings', ['group_id' => $g['id'], 'meeting_date' => $date, 'topic' => mb_substr((string)input('topic', ''), 0, 200) ?: null]);
        redirect('/groups/' . $g['id'] . '/meetings/' . $mid);
    }

    public function meeting(string $id, string $mid): void
    {
        $g = $this->find($id);
        $this->authorizeManage((int)$g['id']);
        $m = DB::row('SELECT * FROM {p}group_meetings WHERE id = ? AND group_id = ?', [(int)$mid, $g['id']]) ?? abort(404);
        $members = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name, a.present FROM {p}group_members gm JOIN {p}people p ON p.id = gm.person_id LEFT JOIN {p}group_attendance a ON a.person_id = p.id AND a.meeting_id = ? WHERE gm.group_id = ? ORDER BY p.last_name', [$m['id'], $g['id']]);
        view('groups/meeting', ['title' => __('Attendance'), 'g' => $g, 'm' => $m, 'members' => $members]);
    }

    public function saveAttendance(string $id, string $mid): void
    {
        $g = $this->find($id);
        $this->authorizeManage((int)$g['id']);
        $m = DB::row('SELECT * FROM {p}group_meetings WHERE id = ? AND group_id = ?', [(int)$mid, $g['id']]) ?? abort(404);
        $present = input_ids('present');
        DB::run('DELETE FROM {p}group_attendance WHERE meeting_id = ?', [$m['id']]);
        foreach (DB::col('SELECT person_id FROM {p}group_members WHERE group_id = ?', [$g['id']]) as $pid) {
            DB::insert('group_attendance', ['meeting_id' => $m['id'], 'person_id' => $pid, 'present' => in_array((int)$pid, $present, true) ? 1 : 0]);
        }
        DB::update('group_meetings', ['topic' => mb_substr((string)input('topic', ''), 0, 200) ?: null, 'notes' => input('notes') ?: null], 'id = ?', [$m['id']]);
        flash('success', __('Attendance saved: %d present.', count($present)));
        redirect('/groups/' . $g['id']);
    }

    // Positions (roster roles) ---------------------------------------------------
    private function positionData(): array
    {
        return ['name' => mb_substr((string)input('name', ''), 0, 120), 'requires_wwcc' => input_bool('requires_wwcc'), 'min_age' => input_int('min_age'), 'max_per_month' => max(1, (int)input_int('max_per_month', 3)), 'description' => mb_substr((string)input('description', ''), 0, 255) ?: null];
    }

    private function syncPositionCourses(int $posId): void
    {
        DB::run('DELETE FROM {p}position_courses WHERE position_id = ?', [$posId]);
        foreach (input_ids('course_ids') as $cid) {
            DB::insert('position_courses', ['position_id' => $posId, 'course_id' => $cid]);
        }
        DB::run('DELETE FROM {p}position_documents WHERE position_id = ?', [$posId]);
        foreach (input_ids('document_ids') as $did) {
            DB::insert('position_documents', ['position_id' => $posId, 'document_id' => $did]);
        }
    }

    public function addPosition(string $id): void
    {
        $g = $this->find($id);
        if (!can('groups.manage') && !can('rosters.manage')) {
            abort(403);
        }
        $d = $this->positionData() + ['group_id' => $g['id']];
        if ($d['name'] === '') {
            back('/groups/' . $g['id']);
        }
        if ((int)$g['child_related']) {
            $d['requires_wwcc'] = 1;
        }
        $pid = DB::insert('positions', $d);
        $this->syncPositionCourses($pid);
        audit('position.created', 'position', $pid);
        flash('success', __('Position added.'));
        redirect('/groups/' . $g['id'] . '#positions');
    }

    public function updatePosition(string $id): void
    {
        $po = DB::row('SELECT * FROM {p}positions WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!can('groups.manage') && !can('rosters.manage')) {
            abort(403);
        }
        DB::update('positions', $this->positionData(), 'id = ?', [$po['id']]);
        $this->syncPositionCourses((int)$po['id']);
        audit('position.updated', 'position', (int)$po['id']);
        flash('success', __('Position saved.'));
        redirect('/groups/' . $po['group_id'] . '#positions');
    }

    public function deletePosition(string $id): void
    {
        $po = DB::row('SELECT * FROM {p}positions WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!can('groups.manage')) {
            abort(403);
        }
        if (DB::val("SELECT 1 FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.position_id = ? AND r.service_date >= CURDATE()", [$po['id']])) {
            flash('error', __('This position is used on upcoming rosters. Remove it from those rosters first.'));
            redirect('/groups/' . $po['group_id']);
        }
        foreach (['position_courses', 'volunteer_positions'] as $t) {
            DB::run("DELETE FROM {p}$t WHERE position_id = ?", [$po['id']]);
        }
        DB::run('DELETE FROM {p}roster_assignments WHERE position_id = ?', [$po['id']]);
        DB::run('DELETE FROM {p}positions WHERE id = ?', [$po['id']]);
        audit('position.deleted', 'position', (int)$po['id']);
        redirect('/groups/' . $po['group_id']);
    }
}
