<?php
declare(strict_types=1);

final class VolunteersController
{
    /** Position ids the current user may approve for (all, or positions in teams they lead). */
    private function manageablePositions(): ?array
    {
        if (can('volunteers.manage')) {
            return null;
        }
        $led = led_group_ids();
        if (!$led) {
            abort(403);
        }
        return array_map('intval', DB::col('SELECT id FROM {p}positions WHERE group_id IN (' . DB::in($led) . ')', $led));
    }

    private function canDecide(array $app): bool
    {
        $scope = $this->manageablePositions();
        if ($scope === null) {
            return true;
        }
        return (bool)array_intersect($scope, array_map('intval', explode(',', (string)$app['position_ids'])));
    }

    public function index(): void
    {
        $scope = $this->manageablePositions();
        $status = (string)input('status', 'active');
        $where = $status === 'active' ? "a.status IN ('submitted','reviewing','training')" : (in_array($status, ['approved', 'declined'], true) ? 'a.status = ' . DB::pdo()->quote($status) : '1=1');
        $apps = DB::all("SELECT a.*, p.first_name, p.last_name, p.preferred_name FROM {p}volunteer_applications a JOIN {p}people p ON p.id = a.person_id WHERE $where ORDER BY a.created_at DESC LIMIT 200");
        if ($scope !== null) {
            $apps = array_values(array_filter($apps, fn($a) => array_intersect($scope, array_map('intval', explode(',', (string)$a['position_ids'])))));
        }
        $posWhere = $scope === null ? '1=1' : 'po.id IN (' . DB::in($scope) . ')';
        $positions = DB::all("SELECT po.*, g.name AS group_name FROM {p}positions po JOIN {p}groups g ON g.id = po.group_id WHERE g.active = 1 AND $posWhere ORDER BY g.name, po.name", $scope ?? []);
        $filterPerson = input_int('person');
        foreach ($positions as &$po) {
            $po['people'] = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name, vp.preference FROM {p}volunteer_positions vp JOIN {p}people p ON p.id = vp.person_id WHERE vp.position_id = ? AND vp.approved_at IS NOT NULL AND p.archived_at IS NULL' . ($filterPerson ? ' AND p.id = ' . (int)$filterPerson : '') . ' ORDER BY p.last_name', [$po['id']]);
            foreach ($po['people'] as &$pp) {
                $pp['gaps'] = Volunteering::gaps((int)$pp['id'], (int)$po['id']);
            }
            unset($pp);
        }
        unset($po);
        $posNames = [];
        foreach (DB::all('SELECT po.id, po.name, g.name AS g FROM {p}positions po JOIN {p}groups g ON g.id = po.group_id') as $x) {
            $posNames[$x['id']] = $x['g'] . ' — ' . $x['name'];
        }
        view('volunteers/index', ['title' => __('Volunteers'), 'apps' => $apps, 'positions' => $positions, 'status' => $status, 'posNames' => $posNames, 'filterPerson' => $filterPerson]);
    }

    public function application(string $id): void
    {
        $a = DB::row('SELECT * FROM {p}volunteer_applications WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!$this->canDecide($a)) {
            abort(403);
        }
        $person = DB::row('SELECT * FROM {p}people WHERE id = ?', [$a['person_id']]);
        $positions = [];
        foreach (array_filter(array_map('intval', explode(',', (string)$a['position_ids']))) as $pid) {
            $po = DB::row('SELECT po.*, g.name AS group_name FROM {p}positions po JOIN {p}groups g ON g.id = po.group_id WHERE po.id = ?', [$pid]);
            if ($po) {
                $po['gaps'] = Volunteering::gaps((int)$a['person_id'], $pid);
                $po['courses'] = Volunteering::requiredCourses($pid);
                $po['approved'] = Volunteering::isApproved((int)$a['person_id'], $pid);
                $positions[] = $po;
            }
        }
        $training = DB::all('SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.person_id = ?', [$a['person_id']]);
        $check = Volunteering::validCheck((int)$a['person_id']);
        view('volunteers/application', ['title' => __('Volunteer application'), 'a' => $a, 'person' => $person, 'positions' => $positions, 'training' => $training, 'check' => $check]);
    }

    public function decide(string $id): void
    {
        $a = DB::row('SELECT * FROM {p}volunteer_applications WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!$this->canDecide($a)) {
            abort(403);
        }
        $pid = (int)$a['person_id'];
        $positionIds = array_filter(array_map('intval', explode(',', (string)$a['position_ids'])));
        $action = (string)input('action');
        $notes = trim((string)input('reviewer_notes', ''));
        $upd = ['updated_at' => date('Y-m-d H:i:s'), 'reviewer_id' => Auth::id()];
        if ($notes !== '') {
            $upd['reviewer_notes'] = trim(($a['reviewer_notes'] ?? '') . "\n[" . date('j M Y') . '] ' . $notes);
        }
        switch ($action) {
            case 'review':
                $upd['status'] = 'reviewing';
                flash('success', __('Application marked as under review. Initiate any required safeguarding checks now.'));
                break;
            case 'training':
                $upd['status'] = 'training';
                $n = Volunteering::assignTrainingFor($pid, $positionIds, Auth::id());
                flash('success', __('%d course(s) assigned. You will be notified when training is complete.', $n));
                DB::update('volunteer_applications', $upd, 'id = ?', [$a['id']]);
                Volunteering::onTrainingCompleted($pid);
                audit('volunteer_application.training', 'volunteer_application', (int)$a['id']);
                redirect('/volunteers/applications/' . $a['id']);
            case 'approve':
                $scope = $this->manageablePositions();
                $approved = [];
                $blocked = [];
                foreach ($positionIds as $posId) {
                    if ($scope !== null && !in_array($posId, $scope, true)) {
                        continue;
                    }
                    $gaps = Volunteering::gaps($pid, $posId);
                    $name = (string)DB::val('SELECT name FROM {p}positions WHERE id = ?', [$posId]);
                    if ($gaps) {
                        $blocked[] = $name;
                        continue;
                    }
                    DB::run('INSERT INTO {p}volunteer_positions (person_id, position_id, approved_by, approved_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE approved_by = VALUES(approved_by), approved_at = VALUES(approved_at)', [$pid, $posId, Auth::id()]);
                    $gid = (int)DB::val('SELECT group_id FROM {p}positions WHERE id = ?', [$posId]);
                    DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id) VALUES (?, ?)', [$gid, $pid]);
                    $approved[] = $name;
                }
                if ($approved) {
                    $upd['status'] = $blocked ? 'training' : 'approved';
                    $uid = user_id_for_person($pid);
                    if ($uid && !DB::val("SELECT 1 FROM {p}user_roles ur JOIN {p}roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = 'volunteer'", [$uid])) {
                        DB::insert('user_roles', ['user_id' => $uid, 'role_id' => (int)DB::val("SELECT id FROM {p}roles WHERE slug = 'volunteer'")]);
                    }
                    Notify::person($pid, __('You have been approved to serve'), '<p>' . e(__('Welcome to the team! You are approved for: %s.', implode(', ', $approved))) . '</p><p>' . e(__('You will receive roster invitations, reminders and access to team resources. Please keep your availability up to date.')) . '</p><p><a href="' . e(url('/me/availability', [], true)) . '">' . e(__('Set your availability')) . '</a></p>', __('You are approved to serve as %s. Check Affreso for rosters.', implode(', ', $approved)), url('/me/rosters'));
                    flash('success', __('Approved for: %s.', implode(', ', $approved)));
                }
                if ($blocked) {
                    flash('warning', __('Not yet approved (requirements outstanding): %s.', implode(', ', $blocked)));
                }
                break;
            case 'decline':
                $upd['status'] = 'declined';
                flash('success', __('Application declined.'));
                if (input_bool('notify')) {
                    Notify::person($pid, __('Your volunteer application'), '<p>' . e(__('Thank you for offering to serve. A ministry leader will be in touch to talk about next steps.')) . '</p>');
                }
                break;
            default:
                if ($notes === '') {
                    back('/volunteers');
                }
        }
        DB::update('volunteer_applications', $upd, 'id = ?', [$a['id']]);
        audit('volunteer_application.' . ($action ?: 'note'), 'volunteer_application', (int)$a['id']);
        redirect('/volunteers/applications/' . $a['id']);
    }

    public function approvePosition(): void
    {
        $scope = $this->manageablePositions();
        $pid = input_int('person_id');
        $posId = input_int('position_id');
        if (!$pid || !$posId || ($scope !== null && !in_array($posId, $scope, true))) {
            abort(403);
        }
        $pref = input('preference') === 'preferred' ? 'preferred' : 'willing';
        DB::run('INSERT INTO {p}volunteer_positions (person_id, position_id, approved_by, approved_at, preference) VALUES (?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE approved_by = VALUES(approved_by), approved_at = VALUES(approved_at), preference = VALUES(preference)', [$pid, $posId, Auth::id(), $pref]);
        DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id) SELECT group_id, ? FROM {p}positions WHERE id = ?', [$pid, $posId]);
        audit('volunteer.position_approved', 'person', $pid, ['position_id' => $posId]);
        $gaps = Volunteering::gaps($pid, $posId);
        if ($gaps) {
            if (input_bool('assign_training')) {
                Volunteering::assignTrainingFor($pid, [$posId], Auth::id());
            }
            flash('warning', __('Approved, but they cannot be rostered until these are resolved: %s', implode('; ', $gaps)));
        } else {
            flash('success', __('Approved and eligible to be rostered.'));
        }
        back('/volunteers');
    }

    public function removePosition(): void
    {
        $scope = $this->manageablePositions();
        $pid = input_int('person_id');
        $posId = input_int('position_id');
        if ($scope !== null && !in_array($posId, $scope, true)) {
            abort(403);
        }
        DB::run('DELETE FROM {p}volunteer_positions WHERE person_id = ? AND position_id = ?', [$pid, $posId]);
        audit('volunteer.position_removed', 'person', $pid, ['position_id' => $posId]);
        flash('success', __('Removed from position.'));
        back('/volunteers');
    }
}
