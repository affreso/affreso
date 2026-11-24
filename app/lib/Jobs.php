<?php
declare(strict_types=1);

/** Scheduled work: run by cron.php (recommended every 5–15 minutes). Daily tasks run once per day. */
final class Jobs
{
    private static function oncePerDay(string $name): bool
    {
        $ran = DB::val('SELECT ran_at FROM {p}jobs_lock WHERE name = ?', [$name]);
        if ($ran && substr((string)$ran, 0, 10) === date('Y-m-d')) {
            return false;
        }
        DB::run('INSERT INTO {p}jobs_lock (name, ran_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE ran_at = NOW()', [$name]);
        return true;
    }

    public static function run(bool $forceDaily = false): array
    {
        $log = [];
        DB::run('INSERT INTO {p}jobs_lock (name, ran_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE ran_at = NOW()', ['cron']);
        if (Demo::on() && self::oncePerDay('demo_reset')) {
            Demo::reset();
            return ['demo_reset' => true];
        }
        if ($forceDaily || self::oncePerDay('daily')) {
            $log['roster_reminders'] = self::rosterReminders();
            $log['wwcc'] = self::safeguardingExpiry();
            $log['training'] = self::trainingExpiry();
            $log['housekeeping'] = self::housekeeping();
            $log['event_series'] = EventSeries::topUpAll();
            if (Mailchimp::configured()) {
                try {
                    $log['mailchimp'] = Mailchimp::sync();
                } catch (Throwable $e) {
                    $log['mailchimp'] = 'error: ' . $e->getMessage();
                }
            }
        }
        if (CalendarSync::anyConfigured()) {
            $log['calendar_sync'] = CalendarSync::syncDue();
        }
        [$sent, $failed] = Notify::processQueue(200);
        $log['messages_sent'] = $sent;
        $log['messages_failed'] = $failed;
        return $log;
    }

    public static function rosterReminders(): int
    {
        $days = max(1, (int)setting('roster_reminder_days', 2));
        $n = 0;
        $rows = DB::all("SELECT a.*, r.title, r.service_date, r.start_time, po.name AS position FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id WHERE r.status = 'published' AND a.person_id IS NOT NULL AND a.status IN ('pending','accepted','swap_requested') AND a.reminded_at IS NULL AND r.service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)", [$days]);
        foreach ($rows as $a) {
            $when = fmt_date($a['service_date'], 'l j F') . ($a['start_time'] ? ' ' . fmt_time($a['start_time']) : '');
            $pending = $a['status'] === 'pending';
            $subject = $pending ? __('Please respond: %s on %s', $a['position'], fmt_date($a['service_date'], 'D j M')) : __('Reminder: you are serving %s', fmt_date($a['service_date'], 'D j M'));
            $html = '<p>' . e($pending ? __('You have not yet accepted your roster as %s for %s on %s.', $a['position'], $a['title'], $when) : __('This is a reminder that you are serving as %s for %s on %s. Thank you!', $a['position'], $a['title'], $when)) . '</p><p><a href="' . e(url('/me/rosters', [], true)) . '">' . e(__('View my rosters')) . '</a></p>';
            Notify::person((int)$a['person_id'], $subject, $html, __('Reminder: %s, %s.', $a['position'], $when), url('/me/rosters'));
            DB::update('roster_assignments', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$a['id']]);
            $n++;
        }
        return $n;
    }

    public static function safeguardingExpiry(): int
    {
        $n = 0;
        $officers = users_with_permission('safeguarding.manage');
        $lead = (int)setting('wwcc_reminder_days', 60);
        foreach (array_unique([$lead, 30, 7]) as $d) {
            foreach (DB::all("SELECT c.*, p.first_name, p.last_name, p.preferred_name FROM {p}safeguarding_checks c JOIN {p}people p ON p.id = c.person_id WHERE c.status = 'verified' AND c.expires_on = DATE_ADD(CURDATE(), INTERVAL ? DAY)", [$d]) as $c) {
                Notify::person((int)$c['person_id'], __('Your %s expires in %d days', $c['check_type'], $d), '<p>' . e(__('Your %s expires on %s. Please renew it with your state screening agency and let the church office know your updated details, so you can keep serving.', $c['check_type'], fmt_date($c['expires_on']))) . '</p>');
                Notify::users($officers, __('%s: %s expires in %d days', person_name($c), $c['check_type'], $d), '<p>' . e(__('Follow up renewal before %s.', fmt_date($c['expires_on']))) . '</p>', url('/safeguarding', ['filter' => 'expiring']));
                $n++;
            }
        }
        foreach (DB::all("SELECT c.*, p.first_name, p.last_name, p.preferred_name FROM {p}safeguarding_checks c JOIN {p}people p ON p.id = c.person_id WHERE c.status = 'verified' AND c.expires_on < CURDATE()") as $c) {
            DB::update('safeguarding_checks', ['status' => 'expired'], 'id = ?', [$c['id']]);
            Notify::users($officers, __('Expired: %s for %s', $c['check_type'], person_name($c)), '<p>' . e(__('This person can no longer be rostered in child-related positions until a current check is verified.')) . '</p>', url('/safeguarding', ['filter' => 'expired']));
            audit('safeguarding.auto_expired', 'safeguarding_check', (int)$c['id']);
            $n++;
        }
        return $n;
    }

    public static function trainingExpiry(): int
    {
        $n = 0;
        foreach (DB::all("SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.status = 'completed' AND e.expires_at = DATE_ADD(CURDATE(), INTERVAL 30 DAY)") as $e) {
            Notify::person((int)$e['person_id'], __('Training renewal due: %s', $e['title']), '<p>' . e(__('Your "%s" training expires on %s. You will be asked to complete it again.', $e['title'], fmt_date($e['expires_at']))) . '</p>');
            $n++;
        }
        foreach (DB::all("SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.status = 'completed' AND e.expires_at < CURDATE() AND c.status = 'published'") as $e) {
            DB::update('enrolments', ['status' => 'assigned', 'due_date' => date('Y-m-d', strtotime('+30 days')), 'score' => null], 'id = ?', [$e['id']]);
            DB::run('DELETE FROM {p}lesson_progress WHERE enrolment_id = ?', [$e['id']]);
            Notify::person((int)$e['person_id'], __('Please renew your training: %s', $e['title']), '<p>' . e(__('Your "%s" training has expired. Please complete it again within 30 days.', $e['title'])) . '</p><p><a href="' . e(url('/me/training/' . $e['course_id'], [], true)) . '">' . e(__('Start renewal')) . '</a></p>', null, url('/me/training'));
            audit('training.auto_reassigned', 'enrolment', (int)$e['id']);
            $n++;
        }
        return $n;
    }

    /** Routine clean-up of operational data. People records are never deleted automatically. */
    public static function housekeeping(): array
    {
        $out = [];
        $out['login_attempts'] = DB::run('DELETE FROM {p}login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)')->rowCount();
        $out['password_resets'] = DB::run('DELETE FROM {p}password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')->rowCount();
        $out['notifications'] = DB::run('DELETE FROM {p}notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)')->rowCount();
        $msgMonths = max(1, (int)setting('retention_messages_months', 24));
        $out['message_bodies'] = DB::run("UPDATE {p}message_recipients SET body = NULL WHERE body IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL $msgMonths MONTH)")->rowCount();
        $auditYears = max(1, (int)setting('retention_audit_years', 7));
        $out['audit'] = DB::run("DELETE FROM {p}audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL $auditYears YEAR)")->rowCount();
        return $out;
    }

    /** Records that the retention policy says should be reviewed (archived/de-identified). */
    public static function retentionCandidates(): array
    {
        $vm = max(1, (int)setting('retention_visitor_months', 24));
        $ay = max(1, (int)setting('retention_archived_years', 7));
        return [
            'stale_visitors' => DB::all("SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.created_at FROM {p}people p WHERE p.archived_at IS NULL AND p.membership_status = 'visitor' AND p.created_at < DATE_SUB(NOW(), INTERVAL $vm MONTH) AND NOT EXISTS (SELECT 1 FROM {p}event_registrations r WHERE r.person_id = p.id AND r.created_at > DATE_SUB(NOW(), INTERVAL $vm MONTH)) AND NOT EXISTS (SELECT 1 FROM {p}group_members gm WHERE gm.person_id = p.id) AND NOT EXISTS (SELECT 1 FROM {p}users u WHERE u.person_id = p.id AND u.last_login_at > DATE_SUB(NOW(), INTERVAL $vm MONTH))"),
            'old_archived' => DB::all("SELECT p.id, p.first_name, p.last_name, p.preferred_name, p.archived_at, (SELECT COUNT(*) FROM {p}safeguarding_checks c WHERE c.person_id = p.id) AS safeguarding FROM {p}people p WHERE p.archived_at IS NOT NULL AND p.archived_at < DATE_SUB(NOW(), INTERVAL $ay YEAR) AND p.first_name <> 'Erased'"),
        ];
    }
}
