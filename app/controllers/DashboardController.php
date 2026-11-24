<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $pid = person_id();
        $d = ['title' => __('Dashboard')];
        $d['announcements'] = DB::all("SELECT * FROM {p}announcements WHERE (publish_on IS NULL OR publish_on <= CURDATE()) AND (expires_on IS NULL OR expires_on >= CURDATE()) ORDER BY id DESC LIMIT 4");
        $d['myRosters'] = $pid ? DB::all("SELECT ra.*, r.title, r.service_date, r.start_time, p.name AS position FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions p ON p.id = ra.position_id WHERE ra.person_id = ? AND r.status = 'published' AND r.service_date >= CURDATE() AND ra.status IN ('pending','accepted','swap_requested') ORDER BY r.service_date LIMIT 6", [$pid]) : [];
        $d['myTraining'] = $pid ? DB::all("SELECT e.*, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.person_id = ? AND e.status IN ('assigned','in_progress','failed') AND c.status = 'published' ORDER BY e.due_date IS NULL, e.due_date LIMIT 5", [$pid]) : [];
        $d['events'] = DB::all("SELECT * FROM {p}events WHERE status = 'scheduled' AND end_at >= NOW() ORDER BY start_at LIMIT 5");
        $d['stats'] = [];
        if (can('people.view')) {
            $d['stats'][] = [DB::val('SELECT COUNT(*) FROM {p}people WHERE archived_at IS NULL'), __('People'), '/people', false];
            $d['stats'][] = [DB::val("SELECT COUNT(*) FROM {p}people WHERE archived_at IS NULL AND membership_status = 'visitor' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"), __('New visitors (30 days)'), '/people?status=visitor', false];
        }
        if (can('care.manage')) {
            $d['stats'][] = [DB::val("SELECT COUNT(*) FROM {p}care_tasks WHERE status <> 'done' AND (assigned_to = ? OR assigned_to IS NULL)", [Auth::id()]), __('Follow-ups for you'), '/care', false];
        }
        if (can('volunteers.manage')) {
            $d['stats'][] = [DB::val("SELECT COUNT(*) FROM {p}volunteer_applications WHERE status IN ('submitted','reviewing','training')"), __('Volunteer applications in progress'), '/volunteers', false];
        }
        if (can('safeguarding.view')) {
            $n = (int)DB::val("SELECT COUNT(*) FROM {p}safeguarding_checks WHERE status = 'verified' AND expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)", [(int)setting('wwcc_reminder_days', 60)]);
            $d['stats'][] = [$n, __('WWCC expiring soon'), '/safeguarding', $n > 0];
        }
        if (can('rosters.view') || can('rosters.manage')) {
            $n = (int)DB::val("SELECT COUNT(*) FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE r.service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND ra.status IN ('open','declined')");
            $d['stats'][] = [$n, __('Unfilled roster slots (14 days)'), '/rosters', $n > 0];
        }
        if (can('attendance.record')) {
            $last = DB::row('SELECT count_date, SUM(adults + youth + children + online) AS n FROM {p}attendance_counts WHERE count_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY count_date ORDER BY count_date DESC LIMIT 1');
            $d['stats'][] = [$last['n'] ?? 0, $last ? __('Attendance %s', fmt_date($last['count_date'], 'D j M')) : __('Attendance (not recorded this week)'), '/attendance', !$last];
        }
        if (can('rooms.manage')) {
            $d['stats'][] = [DB::val("SELECT COUNT(*) FROM {p}room_bookings WHERE status = 'requested'"), __('Booking requests'), '/rooms', false];
        }
        if (can('courses.approve')) {
            $d['stats'][] = [DB::val("SELECT COUNT(*) FROM {p}courses WHERE status = 'review'"), __('Courses awaiting review'), '/courses?status=review', false];
        }
        $d['led'] = led_group_ids();
        $d['openSwaps'] = $pid ? DB::all("SELECT s.*, r.title, r.service_date, p.name AS position FROM {p}swap_requests s JOIN {p}roster_assignments ra ON ra.id = s.assignment_id JOIN {p}rosters r ON r.id = ra.roster_id JOIN {p}positions p ON p.id = ra.position_id JOIN {p}volunteer_positions vp ON vp.position_id = ra.position_id AND vp.person_id = ? AND vp.approved_at IS NOT NULL WHERE s.status = 'open' AND s.requested_by <> ? AND r.service_date >= CURDATE() LIMIT 5", [$pid, $pid]) : [];
        view('dashboard/index', $d);
    }
}
