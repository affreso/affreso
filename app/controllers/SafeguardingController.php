<?php
declare(strict_types=1);

/** Working With Children Checks and other safeguarding clearances. Reference numbers are encrypted. */
final class SafeguardingController
{
    public static function checkTypes(): array
    {
        return [
            'WWCC' => __('Working With Children Check (NSW / SA / WA / VIC)'),
            'Blue Card' => __('Blue Card (QLD)'),
            'WWVP' => __('Working with Vulnerable People (ACT / TAS)'),
            'Ochre Card' => __('Ochre Card (NT)'),
            'Police Check' => __('National Police Check'),
            'Other' => __('Other clearance'),
        ];
    }

    public function index(): void
    {
        $filter = (string)input('filter', 'all');
        $days = (int)setting('wwcc_reminder_days', 60);
        $where = '1=1';
        if ($filter === 'expiring') {
            $where = "c.status = 'verified' AND c.expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)";
        } elseif ($filter === 'expired') {
            $where = "(c.status = 'expired' OR (c.expires_on < CURDATE() AND c.status = 'verified'))";
        } elseif ($filter === 'pending') {
            $where = "c.status = 'pending'";
        }
        $person = input_int('person');
        $params = [];
        if ($person) {
            $where .= ' AND c.person_id = ?';
            $params[] = $person;
        }
        $rows = DB::all("SELECT c.*, p.first_name, p.last_name, p.preferred_name FROM {p}safeguarding_checks c JOIN {p}people p ON p.id = c.person_id WHERE $where ORDER BY c.expires_on IS NULL, c.expires_on LIMIT 500", $params);
        // Volunteers approved for WWCC positions without a valid check — the critical compliance gap.
        $gaps = DB::all("SELECT DISTINCT p.id, p.first_name, p.last_name, p.preferred_name, GROUP_CONCAT(DISTINCT po.name SEPARATOR ', ') AS positions FROM {p}volunteer_positions vp JOIN {p}positions po ON po.id = vp.position_id JOIN {p}people p ON p.id = vp.person_id WHERE po.requires_wwcc = 1 AND vp.approved_at IS NOT NULL AND p.archived_at IS NULL AND NOT EXISTS (SELECT 1 FROM {p}safeguarding_checks c WHERE c.person_id = p.id AND c.status = 'verified' AND (c.expires_on IS NULL OR c.expires_on >= CURDATE())) GROUP BY p.id, p.first_name, p.last_name, p.preferred_name");
        $edit = input_int('edit') ? DB::row('SELECT * FROM {p}safeguarding_checks WHERE id = ?', [input_int('edit')]) : null;
        if ($edit && can('safeguarding.manage')) {
            $edit['reference'] = Crypto::decrypt($edit['reference_enc']);
            audit('safeguarding.reference_viewed', 'safeguarding_check', (int)$edit['id']);
        }
        view('safeguarding/index', ['title' => __('Safeguarding'), 'rows' => $rows, 'gaps' => $gaps, 'filter' => $filter, 'person' => $person, 'edit' => $edit]);
    }

    private function data(): array
    {
        $type = (string)input('check_type', 'WWCC');
        $status = (string)input('status', 'pending');
        $ref = trim((string)input('reference', ''));
        $d = [
            'check_type' => isset(self::checkTypes()[$type]) ? $type : 'Other',
            'jurisdiction' => array_key_exists((string)input('jurisdiction'), au_states()) ? input('jurisdiction') : null,
            'status' => in_array($status, ['pending', 'verified', 'expired', 'barred', 'withdrawn'], true) ? $status : 'pending',
            'verified_on' => input_date('verified_on'),
            'expires_on' => input_date('expires_on'),
            'notes' => mb_substr((string)input('notes', ''), 0, 2000) ?: null,
        ];
        if ($ref !== '') {
            $d['reference_enc'] = Crypto::encrypt(mb_substr($ref, 0, 60));
        }
        if ($d['status'] === 'verified') {
            $d['verified_by'] = Auth::id();
            $d['verified_on'] ??= date('Y-m-d');
        }
        return $d;
    }

    public function store(): void
    {
        $pid = input_int('person_id');
        if (!$pid) {
            back('/safeguarding');
        }
        $d = $this->data() + ['person_id' => $pid];
        $id = DB::insert('safeguarding_checks', $d);
        audit('safeguarding.created', 'safeguarding_check', $id, ['person_id' => $pid, 'status' => $d['status']]);
        if ($d['status'] === 'barred') {
            $this->handleBarred($pid);
        }
        if ($d['status'] === 'verified') {
            Volunteering::onTrainingCompleted($pid);
        }
        flash('success', __('Check recorded.'));
        redirect('/safeguarding', ['person' => $pid]);
    }

    public function update(string $id): void
    {
        $c = DB::row('SELECT * FROM {p}safeguarding_checks WHERE id = ?', [(int)$id]) ?? abort(404);
        $d = $this->data();
        if ($d['status'] !== 'verified') {
            unset($d['verified_by']);
        }
        DB::update('safeguarding_checks', $d, 'id = ?', [$c['id']]);
        audit('safeguarding.updated', 'safeguarding_check', (int)$c['id'], ['status' => $d['status']]);
        if ($d['status'] === 'barred') {
            $this->handleBarred((int)$c['person_id']);
        }
        flash('success', __('Check updated.'));
        redirect('/safeguarding', ['person' => $c['person_id']]);
    }

    /** A barred or negative notice immediately removes the person from all child-related positions and future rosters. */
    private function handleBarred(int $pid): void
    {
        $positions = DB::col('SELECT id FROM {p}positions WHERE requires_wwcc = 1');
        if ($positions) {
            DB::run('DELETE FROM {p}volunteer_positions WHERE person_id = ? AND position_id IN (' . DB::in($positions) . ')', array_merge([$pid], $positions));
            DB::run("UPDATE {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id SET ra.person_id = NULL, ra.status = 'open' WHERE ra.person_id = ? AND r.service_date >= CURDATE() AND ra.position_id IN (" . DB::in($positions) . ')', array_merge([$pid], $positions));
        }
        Notify::users(users_with_permission('safeguarding.manage'), __('Safeguarding alert: barred status recorded'), '<p>' . e(__('A barred or negative notice has been recorded. The person has been removed from all child-related positions and upcoming rosters. Follow your church safeguarding policy and reporting obligations.')) . '</p>', url('/safeguarding'));
        audit('safeguarding.barred_actions', 'person', $pid);
        flash('warning', __('Barred status: the person was removed from all child-related positions and upcoming rosters.'));
    }
}
