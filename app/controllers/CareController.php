<?php
declare(strict_types=1);

/** Newcomer follow-up, pastoral contact requests, baptism and membership enquiries, prayer requests. */
final class CareController
{
    public static function types(): array
    {
        return ['newcomer' => __('Newcomer follow-up'), 'pastoral_contact' => __('Pastoral contact request'), 'baptism' => __('Baptism enquiry'), 'membership_class' => __('Membership class'), 'prayer' => __('Prayer request'), 'hospital' => __('Hospital / home visit'), 'other' => __('Other')];
    }

    public function index(): void
    {
        $scope = input('scope', 'mine');
        $status = (string)input('status', 'active');
        $type = (string)input('type', '');
        $where = ['(t.is_confidential = 0 OR ? = 1)'];
        $params = [can('pastoral.view') ? 1 : 0];
        if ($scope === 'mine') {
            $where[] = '(t.assigned_to = ? OR t.assigned_to IS NULL)';
            $params[] = Auth::id();
        }
        if ($status === 'active') {
            $where[] = "t.status <> 'done'";
        } elseif (in_array($status, ['open', 'in_progress', 'done'], true)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if (isset(self::types()[$type])) {
            $where[] = 't.task_type = ?';
            $params[] = $type;
        }
        $rows = DB::all('SELECT t.*, p.first_name, p.last_name, p.preferred_name, ap.first_name AS a_first, ap.last_name AS a_last FROM {p}care_tasks t LEFT JOIN {p}people p ON p.id = t.person_id LEFT JOIN {p}users au ON au.id = t.assigned_to LEFT JOIN {p}people ap ON ap.id = au.person_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.status = \'done\', t.due_date IS NULL, t.due_date, t.id DESC LIMIT 300', $params);
        $staff = [];
        foreach (DB::all("SELECT DISTINCT u.id, p.first_name, p.last_name, p.preferred_name, u.email FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id JOIN {p}user_roles ur ON ur.user_id = u.id JOIN {p}role_permissions rp ON rp.role_id = ur.role_id WHERE rp.permission = 'care.manage' AND u.status = 'active'") as $s) {
            $staff[$s['id']] = person_name($s) ?: $s['email'];
        }
        view('care/index', ['title' => __('Care & follow-up'), 'rows' => $rows, 'scope' => $scope, 'status' => $status, 'type' => $type, 'staff' => $staff]);
    }

    public function store(): void
    {
        $title = trim((string)input('title', ''));
        if ($title === '') {
            back('/care');
        }
        $type = (string)input('task_type', 'other');
        $confidential = input_bool('is_confidential') && can('pastoral.view') ? 1 : 0;
        $assigned = input_int('assigned_to') ?: Auth::id();
        $id = DB::insert('care_tasks', ['person_id' => input_int('person_id'), 'task_type' => isset(self::types()[$type]) ? $type : 'other', 'title' => mb_substr($title, 0, 200), 'details' => input('details') ?: null, 'is_confidential' => $confidential, 'assigned_to' => $assigned, 'due_date' => input_date('due_date'), 'created_by' => Auth::id()]);
        if ($assigned !== Auth::id()) {
            notify_user($assigned, __('Follow-up assigned to you: %s', $title), url('/care'));
        }
        audit('care.created', 'care_task', $id);
        flash('success', __('Follow-up added.'));
        back('/care');
    }

    public function update(string $id): void
    {
        $t = DB::row('SELECT * FROM {p}care_tasks WHERE id = ?', [(int)$id]) ?? abort(404);
        if ((int)$t['is_confidential'] && !can('pastoral.view')) {
            abort(403);
        }
        $status = in_array(input('status'), ['open', 'in_progress', 'done'], true) ? input('status') : $t['status'];
        $data = ['status' => $status, 'completed_at' => $status === 'done' ? ($t['completed_at'] ?: date('Y-m-d H:i:s')) : null];
        if (input('assigned_to') !== null) {
            $data['assigned_to'] = input_int('assigned_to');
            if ($data['assigned_to'] && (int)$data['assigned_to'] !== (int)$t['assigned_to']) {
                notify_user($data['assigned_to'], __('Follow-up assigned to you: %s', $t['title']), url('/care'));
            }
        }
        if (($note = trim((string)input('add_note', ''))) !== '') {
            $data['details'] = trim(($t['details'] ?? '') . "\n[" . date('j M Y') . '] ' . $note);
        }
        DB::update('care_tasks', $data, 'id = ?', [$t['id']]);
        audit('care.updated', 'care_task', (int)$t['id'], ['status' => $status]);
        back('/care');
    }
}
