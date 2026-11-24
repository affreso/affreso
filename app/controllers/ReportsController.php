<?php
declare(strict_types=1);

final class ReportsController
{
    private function catalogue(): array
    {
        $r = [];
        if (can('reports.view')) {
            $r['membership'] = [__('Membership statistics'), __('People by status, households, new people and age groups.')];
            $r['participation'] = [__('Volunteer participation'), __('How often each volunteer has served, declines, and burnout risk.')];
            $r['rosters'] = [__('Roster coverage'), __('Unfilled slots across all teams for the next 30 days.')];
            $r['attendance'] = [__('Service attendance'), __('Weekly headcounts and trends by campus.')];
            $r['events'] = [__('Events & attendance'), __('Registrations, capacity and attendance.')];
            $r['groups'] = [__('Group attendance'), __('Average attendance across small groups and teams.')];
        }
        if (can('training.reports') || can('reports.view')) {
            $r['training'] = [__('Training compliance'), __('Volunteers missing or with expiring required training.')];
        }
        if (can('safeguarding.view')) {
            $r['safeguarding'] = [__('Child-safety compliance'), __('WWCC status for everyone in child-related positions.')];
        }
        if (can('giving.view')) {
            $r['giving'] = [__('Giving trends'), __('Monthly giving by fund for the last 12 months.')];
        }
        return $r;
    }

    public function index(): void
    {
        $cat = $this->catalogue();
        if (!$cat) {
            abort(403);
        }
        view('reports/index', ['title' => __('Reports & insights'), 'cat' => $cat]);
    }

    public function show(string $name): void
    {
        $cat = $this->catalogue();
        if ($name === 'attendance' && isset($cat[$name])) {
            redirect('/attendance/report');
        }
        if (!isset($cat[$name])) {
            abort(isset(['membership' => 1, 'participation' => 1, 'rosters' => 1, 'events' => 1, 'groups' => 1, 'training' => 1, 'safeguarding' => 1, 'giving' => 1][$name]) ? 403 : 404);
        }
        [$header, $rows, $extra] = $this->{'report' . ucfirst($name)}();
        if (input('export')) {
            audit('report.exported', null, null, $name);
            csv_download($name . '-' . date('Y-m-d') . '.csv', $header, $rows);
        }
        view('reports/show', ['title' => $cat[$name][0], 'desc' => $cat[$name][1], 'name' => $name, 'header' => $header, 'rows' => $rows, 'extra' => $extra]);
    }

    private function reportMembership(): array
    {
        $byStatus = DB::all('SELECT membership_status AS label, COUNT(*) AS n FROM {p}people WHERE archived_at IS NULL GROUP BY membership_status ORDER BY n DESC');
        $ages = DB::all("SELECT CASE WHEN birthdate IS NULL THEN 'Unknown' WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 12 THEN '0–11' WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 18 THEN '12–17' WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 30 THEN '18–29' WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 50 THEN '30–49' WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 70 THEN '50–69' ELSE '70+' END AS label, COUNT(*) AS n FROM {p}people WHERE archived_at IS NULL GROUP BY label ORDER BY label");
        $months = DB::all("SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS n FROM {p}people WHERE created_at > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label");
        $totals = ['people' => DB::val('SELECT COUNT(*) FROM {p}people WHERE archived_at IS NULL'), 'households' => DB::val('SELECT COUNT(*) FROM {p}households'), 'new_year' => DB::val('SELECT COUNT(*) FROM {p}people WHERE YEAR(created_at) = YEAR(CURDATE())'), 'members_joined_year' => DB::val('SELECT COUNT(*) FROM {p}people WHERE YEAR(joined_on) = YEAR(CURDATE())')];
        $rows = array_map(fn($r) => [__(ucfirst($r['label'])), $r['n']], $byStatus);
        return [[__('Status'), __('People')], $rows, ['bars' => ['By status' => $byStatus, 'Age groups' => $ages, 'New records by month' => $months], 'totals' => $totals]];
    }

    private function reportParticipation(): array
    {
        $rows = DB::all("SELECT CONCAT(COALESCE(p.preferred_name, p.first_name), ' ', p.last_name) AS name, SUM(a.status = 'accepted' AND r.service_date < CURDATE()) AS served, SUM(a.status IN ('accepted','pending') AND r.service_date >= CURDATE()) AS upcoming, SUM(a.status = 'declined') AS declined, MAX(CASE WHEN a.status = 'accepted' AND r.service_date < CURDATE() THEN r.service_date END) AS last_served FROM {p}people p JOIN {p}volunteer_positions vp ON vp.person_id = p.id AND vp.approved_at IS NOT NULL LEFT JOIN {p}roster_assignments a ON a.person_id = p.id LEFT JOIN {p}rosters r ON r.id = a.roster_id AND r.service_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE p.archived_at IS NULL GROUP BY p.id, name ORDER BY served DESC, name");
        $rows = array_map(fn($r) => [$r['name'], (int)$r['served'], (int)$r['upcoming'], (int)$r['declined'], $r['last_served'] ? fmt_date($r['last_served']) : __('Not in 90 days')], $rows);
        return [[__('Volunteer'), __('Served (90 days)'), __('Upcoming (30 days)'), __('Declined'), __('Last served')], $rows, ['note' => __('People serving many times in a short period may be at risk of burnout; people not serving recently may need encouragement.')]];
    }

    private function reportRosters(): array
    {
        $rows = DB::all("SELECT r.service_date, g.name AS team, r.title, po.name AS position, a.status FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}groups g ON g.id = r.group_id JOIN {p}positions po ON po.id = a.position_id WHERE r.service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND a.status IN ('open','declined','pending','swap_requested') ORDER BY r.service_date, g.name");
        return [[__('Date'), __('Team'), __('Roster'), __('Position'), __('Status')], array_map(fn($r) => [fmt_date($r['service_date']), $r['team'], $r['title'], $r['position'], __(ucfirst(str_replace('_', ' ', $r['status'])))], $rows), ['note' => __('Open and declined slots need filling; pending means the volunteer has not responded yet.')]];
    }

    private function reportEvents(): array
    {
        $rows = DB::all("SELECT e.title, e.start_at, e.capacity, SUM(r.status IN ('registered','attended')) AS registered, SUM(r.status = 'attended') AS attended, SUM(r.status = 'waitlisted') AS waiting, SUM(r.status = 'no_show') AS no_show FROM {p}events e LEFT JOIN {p}event_registrations r ON r.event_id = e.id WHERE e.registration_enabled = 1 AND e.start_at > DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY e.id, e.title, e.start_at, e.capacity ORDER BY e.start_at");
        return [[__('Event'), __('Date'), __('Capacity'), __('Registered'), __('Attended'), __('Waiting list'), __('No-shows')], array_map(fn($r) => [$r['title'], fmt_date($r['start_at']), $r['capacity'] ?: '—', (int)$r['registered'], (int)$r['attended'], (int)$r['waiting'], (int)$r['no_show']], $rows), []];
    }

    private function reportGroups(): array
    {
        $rows = DB::all("SELECT g.name, (SELECT COUNT(*) FROM {p}group_members gm WHERE gm.group_id = g.id) AS members, COUNT(DISTINCT m.id) AS meetings, ROUND(AVG(x.present), 1) AS avg_present, MAX(m.meeting_date) AS last_meeting FROM {p}groups g LEFT JOIN {p}group_meetings m ON m.group_id = g.id AND m.meeting_date > DATE_SUB(CURDATE(), INTERVAL 90 DAY) LEFT JOIN (SELECT meeting_id, SUM(present) AS present FROM {p}group_attendance GROUP BY meeting_id) x ON x.meeting_id = m.id WHERE g.active = 1 GROUP BY g.id, g.name ORDER BY g.name");
        return [[__('Group'), __('Members'), __('Meetings (90 days)'), __('Average present'), __('Last meeting')], array_map(fn($r) => [$r['name'], (int)$r['members'], (int)$r['meetings'], $r['avg_present'] ?? '—', $r['last_meeting'] ? fmt_date($r['last_meeting']) : '—'], $rows), []];
    }

    private function reportTraining(): array
    {
        $rows = [];
        $req = DB::all("SELECT vp.person_id, CONCAT(COALESCE(p.preferred_name, p.first_name), ' ', p.last_name) AS name, po.name AS position, c.id AS course_id, c.title FROM {p}volunteer_positions vp JOIN {p}people p ON p.id = vp.person_id JOIN {p}positions po ON po.id = vp.position_id JOIN {p}position_courses pc ON pc.position_id = po.id JOIN {p}courses c ON c.id = pc.course_id WHERE vp.approved_at IS NOT NULL AND p.archived_at IS NULL ORDER BY name");
        $soon = (int)setting('wwcc_reminder_days', 60);
        $ok = 0;
        foreach ($req as $r) {
            $e = DB::row('SELECT * FROM {p}enrolments WHERE person_id = ? AND course_id = ?', [$r['person_id'], $r['course_id']]);
            if ($e && $e['status'] === 'completed' && (!$e['expires_at'] || $e['expires_at'] >= date('Y-m-d'))) {
                if ($e['expires_at'] && $e['expires_at'] <= date('Y-m-d', strtotime("+$soon days"))) {
                    $rows[] = [$r['name'], $r['position'], $r['title'], __('Expires %s', fmt_date($e['expires_at']))];
                } else {
                    $ok++;
                }
                continue;
            }
            $status = !$e ? __('Not assigned') : ($e['status'] === 'completed' ? __('Expired %s', fmt_date($e['expires_at'])) : __(ucfirst(str_replace('_', ' ', $e['status']))));
            $rows[] = [$r['name'], $r['position'], $r['title'], $status];
        }
        $total = count($req);
        return [[__('Volunteer'), __('Position'), __('Required course'), __('Status')], $rows, ['totals' => ['requirements' => $total, 'compliant' => $ok, 'rate' => $total ? round($ok / $total * 100) . '%' : '—'], 'note' => __('Only requirements needing attention are listed.')]];
    }

    private function reportSafeguarding(): array
    {
        $rows = DB::all("SELECT DISTINCT p.id, CONCAT(COALESCE(p.preferred_name, p.first_name), ' ', p.last_name) AS name FROM {p}volunteer_positions vp JOIN {p}positions po ON po.id = vp.position_id AND po.requires_wwcc = 1 JOIN {p}people p ON p.id = vp.person_id WHERE vp.approved_at IS NOT NULL AND p.archived_at IS NULL ORDER BY name");
        $out = [];
        $ok = 0;
        foreach ($rows as $r) {
            $c = DB::row('SELECT * FROM {p}safeguarding_checks WHERE person_id = ? ORDER BY (status = "verified") DESC, expires_on DESC LIMIT 1', [$r['id']]);
            $valid = $c && $c['status'] === 'verified' && (!$c['expires_on'] || $c['expires_on'] >= date('Y-m-d'));
            $ok += $valid ? 1 : 0;
            $out[] = [$r['name'], $c ? $c['check_type'] . ' ' . $c['jurisdiction'] : '—', $c ? ($valid ? __('Current') : __(ucfirst($c['status'])) . ($c['expires_on'] && $c['expires_on'] < date('Y-m-d') ? ' — ' . __('expired') : '')) : __('No check on file'), $c && $c['expires_on'] ? fmt_date($c['expires_on']) : '—'];
        }
        audit('report.safeguarding_viewed');
        return [[__('Volunteer'), __('Check'), __('Status'), __('Expires')], $out, ['totals' => ['child_related_volunteers' => count($rows), 'current_checks' => $ok, 'rate' => $rows ? round($ok / count($rows) * 100) . '%' : '—']]];
    }

    private function reportGiving(): array
    {
        $rows = DB::all("SELECT DATE_FORMAT(COALESCE(d.received_on, d.created_at), '%Y-%m') AS month, COALESCE(f.name, 'Unallocated') AS fund, SUM(d.amount_cents) AS total, COUNT(*) AS gifts, COUNT(DISTINCT d.person_id) AS donors FROM {p}donations d LEFT JOIN {p}funds f ON f.id = d.fund_id WHERE d.status = 'paid' AND COALESCE(d.received_on, d.created_at) > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month, fund ORDER BY month DESC, fund");
        $bars = DB::all("SELECT DATE_FORMAT(COALESCE(received_on, created_at), '%Y-%m') AS label, ROUND(SUM(amount_cents) / 100) AS n FROM {p}donations WHERE status = 'paid' AND COALESCE(received_on, created_at) > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label");
        audit('report.giving_viewed');
        return [[__('Month'), __('Fund'), __('Total'), __('Gifts'), __('Donors')], array_map(fn($r) => [$r['month'], $r['fund'], number_format($r['total'] / 100, 2), (int)$r['gifts'], (int)$r['donors']], $rows), ['bars' => ['Monthly total ($)' => $bars]]];
    }
}
