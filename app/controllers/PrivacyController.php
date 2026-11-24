<?php
declare(strict_types=1);

/** Privacy requests (APP 12/13), retention review and the data-breach register (Notifiable Data Breaches scheme). */
final class PrivacyController
{
    public function index(): void
    {
        $tab = (string)input('tab', 'requests');
        $requests = DB::all('SELECT r.*, p.first_name, p.last_name, p.preferred_name FROM {p}privacy_requests r LEFT JOIN {p}people p ON p.id = r.person_id ORDER BY r.status = "closed", r.due_on, r.id DESC LIMIT 200');
        $breaches = DB::all('SELECT * FROM {p}breach_register ORDER BY stage = "closed", discovered_at DESC');
        $consent = DB::row("SELECT COUNT(*) AS people, SUM(consent_contact) AS contact, SUM(consent_photo) AS photo, SUM(directory_visible) AS directory FROM {p}people WHERE archived_at IS NULL");
        $candidates = $tab === 'retention' ? Jobs::retentionCandidates() : null;
        view('admin/privacy', ['title' => __('Privacy & compliance'), 'tab' => $tab, 'requests' => $requests, 'breaches' => $breaches, 'consent' => $consent, 'candidates' => $candidates]);
    }

    public function storeRequest(): void
    {
        $type = in_array(input('request_type'), ['access', 'correction', 'deletion', 'complaint'], true) ? input('request_type') : 'access';
        $received = input_date('received_on') ?? date('Y-m-d');
        $id = DB::insert('privacy_requests', ['person_id' => input_int('person_id'), 'request_type' => $type, 'details' => input('details') ?: null, 'received_on' => $received, 'due_on' => date('Y-m-d', strtotime($received . ' +30 days')), 'handled_by' => Auth::id()]);
        audit('privacy.request_created', 'privacy_request', $id);
        redirect('/admin/privacy');
    }

    public function updateRequest(string $id): void
    {
        $status = in_array(input('status'), ['open', 'in_progress', 'closed'], true) ? input('status') : 'open';
        DB::update('privacy_requests', ['status' => $status, 'outcome' => input('outcome') ?: null, 'handled_by' => Auth::id()], 'id = ?', [(int)$id]);
        audit('privacy.request_updated', 'privacy_request', (int)$id, ['status' => $status]);
        redirect('/admin/privacy');
    }

    public function storeBreach(): void
    {
        $when = input_datetime('discovered_at') ?? date('Y-m-d H:i:s');
        $id = DB::insert('breach_register', ['discovered_at' => $when, 'summary' => mb_substr((string)input('summary', __('Suspected data breach')), 0, 255), 'details' => input('details') ?: null, 'data_involved' => input('data_involved') ?: null, 'people_affected' => input_int('people_affected'), 'assessment_due' => date('Y-m-d', strtotime($when . ' +30 days')), 'recorded_by' => Auth::id()]);
        Notify::users(users_with_permission('privacy.manage'), __('Data breach recorded: %s', (string)input('summary')), '<p>' . e(__('A suspected data breach has been recorded. Contain it now, then assess within 30 days whether it is likely to cause serious harm.')) . '</p>', url('/admin/breaches/' . $id));
        audit('privacy.breach_recorded', 'breach', $id);
        redirect('/admin/breaches/' . $id);
    }

    public function breach(string $id): void
    {
        $b = DB::row('SELECT * FROM {p}breach_register WHERE id = ?', [(int)$id]) ?? abort(404);
        view('admin/breach', ['title' => __('Data breach record'), 'b' => $b]);
    }

    public function updateBreach(string $id): void
    {
        $b = DB::row('SELECT * FROM {p}breach_register WHERE id = ?', [(int)$id]) ?? abort(404);
        $stage = in_array(input('stage'), ['contain', 'assess', 'notify', 'review', 'closed'], true) ? input('stage') : $b['stage'];
        $harm = in_array(input('likely_serious_harm'), ['unknown', 'yes', 'no'], true) ? input('likely_serious_harm') : $b['likely_serious_harm'];
        DB::update('breach_register', ['stage' => $stage, 'likely_serious_harm' => $harm, 'summary' => mb_substr((string)input('summary', $b['summary']), 0, 255), 'details' => input('details') ?: null, 'data_involved' => input('data_involved') ?: null, 'people_affected' => input_int('people_affected'), 'oaic_notified_on' => input_date('oaic_notified_on'), 'individuals_notified_on' => input_date('individuals_notified_on'), 'actions_taken' => input('actions_taken') ?: null, 'lessons_learned' => input('lessons_learned') ?: null], 'id = ?', [$b['id']]);
        audit('privacy.breach_updated', 'breach', (int)$b['id'], ['stage' => $stage, 'harm' => $harm]);
        flash('success', __('Breach record updated.'));
        redirect('/admin/breaches/' . $b['id']);
    }

    /** Apply the retention policy to the records selected by an administrator. */
    public function runRetention(): void
    {
        $archive = input_ids('archive_ids');
        $n = 0;
        foreach ($archive as $pid) {
            DB::update('people', ['archived_at' => date('Y-m-d H:i:s')], 'id = ? AND archived_at IS NULL', [$pid]);
            DB::run("UPDATE {p}users SET status = 'disabled' WHERE person_id = ?", [$pid]);
            $n++;
        }
        audit('privacy.retention_archived', null, null, ['count' => $n, 'ids' => $archive]);
        flash('success', __('%d record(s) archived. Archived records can be de-identified individually from their profile once retention periods have passed.', $n));
        redirect('/admin/privacy', ['tab' => 'retention']);
    }
}
