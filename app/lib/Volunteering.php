<?php
declare(strict_types=1);

/**
 * Volunteer eligibility, roster suggestions and the onboarding workflow that ties
 * training, safeguarding checks and rostering together.
 */
final class Volunteering
{
    public static function requiredCourses(int $positionId): array
    {
        return DB::all('SELECT c.id, c.title FROM {p}position_courses pc JOIN {p}courses c ON c.id = pc.course_id WHERE pc.position_id = ?', [$positionId]);
    }

    public static function validCheck(int $personId, ?string $onDate = null): ?array
    {
        $d = $onDate ?? date('Y-m-d');
        return DB::row("SELECT * FROM {p}safeguarding_checks WHERE person_id = ? AND status = 'verified' AND (expires_on IS NULL OR expires_on >= ?) ORDER BY expires_on DESC LIMIT 1", [$personId, $d]);
    }

    public static function hasCompleted(int $personId, int $courseId, ?string $onDate = null): bool
    {
        $d = $onDate ?? date('Y-m-d');
        return (bool)DB::val("SELECT 1 FROM {p}enrolments WHERE person_id = ? AND course_id = ? AND status = 'completed' AND (expires_at IS NULL OR expires_at >= ?)", [$personId, $courseId, $d]);
    }

    /** Requirements a person still needs for a position: list of human-readable gaps. */
    public static function gaps(int $personId, int $positionId, ?string $onDate = null): array
    {
        $pos = DB::row('SELECT * FROM {p}positions WHERE id = ?', [$positionId]);
        if (!$pos) {
            return ['Unknown position'];
        }
        $gaps = [];
        if ((int)$pos['requires_wwcc'] && !self::validCheck($personId, $onDate)) {
            $gaps[] = __('No verified, current Working With Children Check');
        }
        foreach (self::requiredCourses($positionId) as $c) {
            if (!self::hasCompleted($personId, (int)$c['id'], $onDate)) {
                $gaps[] = __('Training not complete: %s', $c['title']);
            }
        }
        foreach (DB::all('SELECT d.* FROM {p}position_documents pd JOIN {p}documents d ON d.id = pd.document_id WHERE pd.position_id = ? AND d.archived = 0 AND d.requires_ack = 1', [$positionId]) as $doc) {
            if (!LibraryController::validAck($doc, $personId)) {
                $gaps[] = __('Policy not signed: %s', $doc['title']);
            }
        }
        if ($pos['min_age']) {
            $bd = DB::val('SELECT birthdate FROM {p}people WHERE id = ?', [$personId]);
            $age = age_on($bd ? (string)$bd : null, $onDate);
            if ($age === null || $age < (int)$pos['min_age']) {
                $gaps[] = __('Minimum age %d', (int)$pos['min_age']);
            }
        }
        return $gaps;
    }

    /** Is the person already serving at an overlapping time on the same day (other than this roster)? */
    public static function clash(int $personId, int $rosterId): ?string
    {
        $r = DB::row('SELECT * FROM {p}rosters WHERE id = ?', [$rosterId]);
        if (!$r) {
            return null;
        }
        $t = DB::val("SELECT r.title FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.person_id = ? AND r.service_date = ? AND ra.status IN ('pending','accepted') AND ra.roster_id <> ? AND (r.start_time IS NULL OR ? IS NULL OR (r.start_time < COALESCE(?, '23:59:59') AND COALESCE(r.end_time, '23:59:59') > ?)) LIMIT 1", [$personId, $r['service_date'], $rosterId, $r['start_time'], $r['end_time'], $r['start_time']]);
        return $t ? (string)$t : null;
    }

    public static function isApproved(int $personId, int $positionId): bool
    {
        return (bool)DB::val('SELECT 1 FROM {p}volunteer_positions WHERE person_id = ? AND position_id = ? AND approved_at IS NOT NULL', [$personId, $positionId]);
    }

    /**
     * Rank candidates for an open roster slot.
     * Returns rows: person_id, name, eligible, warnings[], recent (assignments in last 30 days), preferred.
     */
    public static function suggest(int $rosterId, int $positionId): array
    {
        $roster = DB::row('SELECT * FROM {p}rosters WHERE id = ?', [$rosterId]);
        $pos = DB::row('SELECT * FROM {p}positions WHERE id = ?', [$positionId]);
        if (!$roster || !$pos) {
            return [];
        }
        $date = $roster['service_date'];
        $people = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name, vp.preference FROM {p}volunteer_positions vp JOIN {p}people p ON p.id = vp.person_id WHERE vp.position_id = ? AND vp.approved_at IS NOT NULL AND p.archived_at IS NULL', [$positionId]);
        $out = [];
        foreach ($people as $p) {
            $pid = (int)$p['id'];
            $warn = self::gaps($pid, $positionId, $date);
            $blocking = (bool)$warn;
            if (DB::val('SELECT 1 FROM {p}unavailability WHERE person_id = ? AND ? BETWEEN start_date AND end_date', [$pid, $date])) {
                $warn[] = __('Marked unavailable on this date');
                $blocking = true;
            }
            $clash = DB::row("SELECT r.title FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.person_id = ? AND r.service_date = ? AND ra.status IN ('pending','accepted') AND ra.roster_id <> ? AND (r.start_time IS NULL OR ? IS NULL OR (r.start_time < COALESCE(?, '23:59:59') AND COALESCE(r.end_time, '23:59:59') > ?)) LIMIT 1", [$pid, $date, $rosterId, $roster['start_time'], $roster['end_time'], $roster['start_time']]);
            if ($clash) {
                $warn[] = __('Already serving: %s', $clash['title']);
                $blocking = true;
            }
            if ($busy = CalendarSync::busyReason($pid, $date, $roster['start_time'], $roster['end_time'])) {
                $warn[] = $busy;
            }
            if (DB::val("SELECT 1 FROM {p}roster_assignments WHERE roster_id = ? AND person_id = ? AND status IN ('pending','accepted')", [$rosterId, $pid])) {
                $warn[] = __('Already on this roster');
                $blocking = true;
            }
            $recent = (int)DB::val("SELECT COUNT(*) FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.person_id = ? AND ra.status IN ('pending','accepted') AND r.service_date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_ADD(?, INTERVAL 30 DAY)", [$pid, $date, $date]);
            $burnout = $recent >= (int)$pos['max_per_month'];
            if ($burnout) {
                $warn[] = __('Burnout risk: %d assignments within 30 days of this date', $recent);
            }
            $declines = (int)DB::val("SELECT COUNT(*) FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.person_id = ? AND ra.status = 'declined' AND r.service_date > DATE_SUB(?, INTERVAL 90 DAY)", [$pid, $date]);
            $score = ($blocking ? -100 : 0) - ($burnout ? 20 : 0) + ($p['preference'] === 'preferred' ? 3 : 0) - $recent * 2 - $declines;
            $out[] = ['person_id' => $pid, 'name' => person_name($p), 'eligible' => !$blocking, 'warnings' => $warn, 'recent' => $recent, 'burnout' => $burnout, 'preferred' => $p['preference'] === 'preferred', 'score' => $score];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['name'], $b['name']));
        return $out;
    }

    /** After training is completed, move onboarding forward and alert reviewers. */
    public static function onTrainingCompleted(int $personId): void
    {
        $apps = DB::all("SELECT * FROM {p}volunteer_applications WHERE person_id = ? AND status = 'training'", [$personId]);
        foreach ($apps as $a) {
            $positionIds = array_filter(array_map('intval', explode(',', (string)$a['position_ids'])));
            $outstanding = 0;
            foreach ($positionIds as $posId) {
                foreach (self::requiredCourses($posId) as $c) {
                    if (!self::hasCompleted($personId, (int)$c['id'])) {
                        $outstanding++;
                    }
                }
            }
            if ($outstanding === 0) {
                $name = person_name(DB::row('SELECT * FROM {p}people WHERE id = ?', [$personId]));
                $link = url('/volunteers/applications/' . $a['id']);
                $recipients = users_with_permission('volunteers.manage');
                if ($a['reviewer_id']) {
                    $recipients[] = (int)$a['reviewer_id'];
                }
                foreach ($positionIds as $posId) {
                    $gid = (int)DB::val('SELECT group_id FROM {p}positions WHERE id = ?', [$posId]);
                    foreach (DB::col("SELECT person_id FROM {p}group_members WHERE group_id = ? AND member_role = 'leader'", [$gid]) as $lp) {
                        if ($uid = user_id_for_person((int)$lp)) {
                            $recipients[] = $uid;
                        }
                    }
                }
                Notify::users($recipients, __('%s has completed required training', $name), '<p>' . e(__('%s has finished all training required for their volunteer application and is ready for final approval.', $name)) . '</p><p><a href="' . e(url('/volunteers/applications/' . $a['id'], [], true)) . '">' . e(__('Review the application')) . '</a></p>', $link);
                audit('workflow.training_complete', 'volunteer_application', (int)$a['id']);
            }
        }
    }

    /** Assign every course required for the given positions (skipping ones already done). */
    public static function assignTrainingFor(int $personId, array $positionIds, ?int $byUser = null): int
    {
        $n = 0;
        foreach ($positionIds as $posId) {
            foreach (self::requiredCourses((int)$posId) as $c) {
                $existing = DB::row('SELECT * FROM {p}enrolments WHERE person_id = ? AND course_id = ?', [$personId, $c['id']]);
                if (!$existing) {
                    DB::insert('enrolments', ['course_id' => $c['id'], 'person_id' => $personId, 'assigned_by' => $byUser, 'due_date' => date('Y-m-d', strtotime('+30 days'))]);
                    $n++;
                }
            }
        }
        if ($n) {
            Notify::person($personId, __('New training has been assigned to you'), '<p>' . e(__('You have %d new course(s) to complete as part of your volunteer onboarding.', $n)) . '</p><p><a href="' . e(url('/me/training', [], true)) . '">' . e(__('Start training')) . '</a></p>', null, url('/me/training'));
        }
        return $n;
    }
}
