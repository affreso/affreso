<?php
declare(strict_types=1);

/**
 * Incident reporting: anyone signed in can report; only incidents.manage can see all reports.
 * Details are encrypted. Child-safety incidents alert safeguarding officers immediately.
 */
final class IncidentsController
{
    public static function types(): array
    {
        return ['child_safety' => __('Child safety concern'), 'whs' => __('Injury / work health & safety'), 'behaviour' => __('Behaviour or conduct'), 'property' => __('Property damage or theft'), 'privacy' => __('Privacy or data incident'), 'other' => __('Other')];
    }

    private function find(int|string $id): array
    {
        $i = DB::row('SELECT * FROM {p}incidents WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!can('incidents.manage') && (int)$i['reported_by'] !== Auth::id()) {
            abort(403);
        }
        return $i;
    }

    public function index(): void
    {
        $manage = can('incidents.manage');
        $status = (string)input('status', 'open');
        $where = $manage ? '1=1' : 'i.reported_by = ' . (int)Auth::id();
        if ($status === 'open') {
            $where .= " AND i.status <> 'closed'";
        }
        [$cw, $cp] = Campus::where('i');
        $rows = DB::all("SELECT i.*, u.email, p.first_name, p.last_name, p.preferred_name FROM {p}incidents i LEFT JOIN {p}users u ON u.id = i.reported_by LEFT JOIN {p}people p ON p.id = u.person_id WHERE $where$cw ORDER BY FIELD(i.severity, 'critical', 'high', 'medium', 'low'), i.occurred_at DESC LIMIT 200", $cp);
        view('incidents/index', ['title' => __('Incident reports'), 'rows' => $rows, 'manage' => $manage, 'status' => $status]);
    }

    public function create(): void
    {
        view('incidents/create', ['title' => __('Report an incident')]);
    }

    public function store(): void
    {
        $type = (string)input('incident_type', 'other');
        $sev = (string)input('severity', 'low');
        $summary = trim((string)input('summary', ''));
        $when = input_datetime('occurred_at') ?? date('Y-m-d H:i:s');
        if ($summary === '') {
            flash('error', __('Please give a short summary.'));
            back('/incidents/new');
        }
        $child = input_bool('child_involved') || $type === 'child_safety' ? 1 : 0;
        $id = DB::insert('incidents', [
            'incident_type' => isset(self::types()[$type]) ? $type : 'other', 'occurred_at' => $when, 'location' => mb_substr((string)input('location', ''), 0, 200) ?: null,
            'campus_id' => Campus::fromInput(), 'summary' => mb_substr($summary, 0, 255), 'details_enc' => Crypto::encrypt((string)input('details', '')),
            'people_enc' => Crypto::encrypt((string)input('people', '')), 'injury_enc' => Crypto::encrypt((string)input('injury', '')), 'action_enc' => Crypto::encrypt((string)input('action', '')),
            'severity' => in_array($sev, ['low', 'medium', 'high', 'critical'], true) ? $sev : 'low', 'child_involved' => $child, 'reported_by' => Auth::id(),
        ]);
        $recipients = users_with_permission('incidents.manage');
        if ($child) {
            $recipients = array_merge($recipients, users_with_permission('safeguarding.manage'));
        }
        Notify::users(array_diff(array_unique($recipients), [Auth::id()]), ($child ? '⚠ ' : '') . __('New incident report: %s', $summary), '<p>' . e(__('An incident has been reported (%s, severity %s). Details are in Affreso and are not included in this email.', self::types()[$type] ?? $type, $sev)) . '</p>', url('/incidents/' . $id));
        audit('incident.reported', 'incident', $id, ['type' => $type, 'severity' => $sev]);
        flash('success', $child ? __('Thank you. Your report has been sent to the safeguarding team. If a child is in immediate danger, call 000 now.') : __('Thank you. Your report has been recorded and the responsible people have been notified.'));
        redirect('/incidents/' . $id);
    }

    public function show(string $id): void
    {
        $i = $this->find($id);
        foreach (['details', 'people', 'injury', 'action', 'outcome'] as $f) {
            $i[$f] = (string)Crypto::decrypt($i[$f . '_enc']);
        }
        $updates = DB::all('SELECT u.*, us.email, p.first_name, p.last_name, p.preferred_name FROM {p}incident_updates u LEFT JOIN {p}users us ON us.id = u.user_id LEFT JOIN {p}people p ON p.id = us.person_id WHERE u.incident_id = ? ORDER BY u.id', [$i['id']]);
        foreach ($updates as &$u) {
            $u['note'] = (string)Crypto::decrypt($u['note_enc']);
        }
        unset($u);
        $reporter = DB::row('SELECT u.email, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE u.id = ?', [$i['reported_by']]);
        $staff = [];
        foreach (users_with_permission('incidents.manage') as $uid) {
            $r = DB::row('SELECT u.email, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE u.id = ?', [$uid]);
            $staff[$uid] = person_name($r) ?: $r['email'];
        }
        audit('incident.viewed', 'incident', (int)$i['id']);
        view('incidents/show', ['title' => $i['summary'], 'i' => $i, 'updates' => $updates, 'reporter' => $reporter, 'staff' => $staff, 'manage' => can('incidents.manage')]);
    }

    public function update(string $id): void
    {
        $i = $this->find($id);
        $note = trim((string)input('note', ''));
        if ($note !== '') {
            DB::insert('incident_updates', ['incident_id' => $i['id'], 'user_id' => Auth::id(), 'note_enc' => Crypto::encrypt($note)]);
        }
        if (can('incidents.manage')) {
            $status = in_array(input('status'), ['reported', 'reviewing', 'actioned', 'closed'], true) ? input('status') : $i['status'];
            $sev = in_array(input('severity'), ['low', 'medium', 'high', 'critical'], true) ? input('severity') : $i['severity'];
            DB::update('incidents', ['status' => $status, 'severity' => $sev, 'assigned_to' => input_int('assigned_to'), 'reportable_conduct' => input_bool('reportable_conduct'), 'police_notified_on' => input_date('police_notified_on'), 'child_protection_notified_on' => input_date('child_protection_notified_on'), 'regulator_notified_on' => input_date('regulator_notified_on'), 'insurer_notified_on' => input_date('insurer_notified_on'), 'outcome_enc' => Crypto::encrypt((string)input('outcome', '')), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$i['id']]);
            if (input_int('assigned_to') && input_int('assigned_to') !== (int)$i['assigned_to']) {
                notify_user(input_int('assigned_to'), __('Incident assigned to you: %s', $i['summary']), url('/incidents/' . $i['id']));
            }
            audit('incident.updated', 'incident', (int)$i['id'], ['status' => $status, 'severity' => $sev]);
        } else {
            audit('incident.note_added', 'incident', (int)$i['id']);
        }
        flash('success', __('Incident updated.'));
        redirect('/incidents/' . $i['id']);
    }
}
