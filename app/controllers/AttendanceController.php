<?php
declare(strict_types=1);

/** Service attendance headcounts (adults, youth, children, online, first-time visitors) per campus. */
final class AttendanceController
{
    private function total(array $r): int
    {
        return (int)$r['adults'] + (int)$r['youth'] + (int)$r['children'] + (int)$r['online'];
    }

    /** Children checked in on a date (and campus), to prefill the children's count. */
    public static function checkedIn(string $date, ?int $campusId): int
    {
        $sql = 'SELECT COUNT(DISTINCT c.child_id) FROM {p}checkins c JOIN {p}checkin_sessions s ON s.id = c.session_id WHERE s.session_date = ?';
        $p = [$date];
        if ($campusId) {
            $sql .= ' AND (s.campus_id = ? OR s.campus_id IS NULL)';
            $p[] = $campusId;
        }
        return (int)DB::val($sql, $p);
    }

    public function index(): void
    {
        [$cw, $cp] = Campus::where('a');
        $date = input_date('date') ?? date('Y-m-d', strtotime(date('N') == 7 ? 'today' : 'last sunday'));
        $campus = Campus::current() ?: (int)DB::val('SELECT MIN(id) FROM {p}campuses') ?: null;
        $services = DB::all("SELECT e.id, e.title, e.start_at, e.campus_id FROM {p}events e WHERE DATE(e.start_at) = ? AND e.status = 'scheduled' AND (e.category = 'Service' OR e.title LIKE ?)" . str_replace('a.', 'e.', $cw) . ' ORDER BY e.start_at', array_merge([$date, '%' . __('Service') . '%'], $cp));
        $rows = DB::all("SELECT a.*, c.name AS campus, TRIM(CONCAT(COALESCE(pp.preferred_name, pp.first_name, ''), ' ', COALESCE(pp.last_name, ''))) AS recorder FROM {p}attendance_counts a LEFT JOIN {p}campuses c ON c.id = a.campus_id LEFT JOIN {p}users u ON u.id = a.recorded_by LEFT JOIN {p}people pp ON pp.id = u.person_id WHERE 1=1$cw ORDER BY a.count_date DESC, a.service_label LIMIT 60", $cp);
        $edit = input_int('edit') ? DB::row('SELECT * FROM {p}attendance_counts WHERE id = ?', [input_int('edit')]) : null;
        view('attendance/index', [
            'title' => __('Attendance'), 'rows' => $rows, 'date' => $date, 'services' => $services, 'edit' => $edit,
            'kids' => self::checkedIn($edit['count_date'] ?? $date, $edit ? ((int)$edit['campus_id'] ?: null) : $campus),
            'campus' => $campus, 'summary' => $this->summary(),
        ]);
    }

    private function data(): array
    {
        $date = input_date('count_date');
        $label = trim(mb_substr((string)input('service_label', ''), 0, 120));
        if (!$date || $label === '') {
            flash('error', __('Enter the date and which service the count is for.'));
            back('/attendance');
        }
        if ($date > date('Y-m-d', strtotime('+1 day'))) {
            flash('error', __('Attendance can only be recorded for services that have happened.'));
            back('/attendance');
        }
        if (Campus::multi() && !Campus::fromInput()) {
            flash('error', __('Choose which campus the count is for.'));
            back('/attendance');
        }
        $n = fn($k) => max(0, min(100000, (int)input_int($k, 0)));
        return ['count_date' => $date, 'service_label' => $label, 'campus_id' => Campus::fromInput(), 'event_id' => input_int('event_id') ?: null,
            'adults' => $n('adults'), 'youth' => $n('youth'), 'children' => $n('children'), 'online' => $n('online'), 'visitors' => $n('visitors'),
            'notes' => mb_substr((string)input('notes', ''), 0, 255) ?: null];
    }

    public function store(): void
    {
        $d = $this->data();
        $dup = DB::val('SELECT id FROM {p}attendance_counts WHERE count_date = ? AND service_label = ? AND ' . ($d['campus_id'] ? 'campus_id = ?' : 'campus_id IS NULL'), array_filter([$d['count_date'], $d['service_label'], $d['campus_id']], fn($v) => $v !== null));
        if ($dup) {
            DB::update('attendance_counts', $d + ['recorded_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$dup]);
            audit('attendance.updated', 'attendance', (int)$dup);
            flash('success', __('That service already had a count, so it was updated.'));
        } else {
            $id = DB::insert('attendance_counts', $d + ['recorded_by' => Auth::id()]);
            audit('attendance.recorded', 'attendance', $id);
            flash('success', __('Attendance recorded: %d people.', $this->total($d)));
        }
        redirect('/attendance');
    }

    public function update(string $id): void
    {
        $row = DB::row('SELECT * FROM {p}attendance_counts WHERE id = ?', [(int)$id]) ?? abort(404);
        DB::update('attendance_counts', $this->data() + ['recorded_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['id']]);
        audit('attendance.updated', 'attendance', (int)$row['id']);
        flash('success', __('Attendance updated.'));
        redirect('/attendance');
    }

    public function delete(string $id): void
    {
        $row = DB::row('SELECT * FROM {p}attendance_counts WHERE id = ?', [(int)$id]) ?? abort(404);
        DB::run('DELETE FROM {p}attendance_counts WHERE id = ?', [$row['id']]);
        audit('attendance.deleted', 'attendance', (int)$row['id'], ['date' => $row['count_date'], 'service' => $row['service_label']]);
        flash('success', __('Count deleted.'));
        redirect('/attendance');
    }

    /** Weekly totals (Monday-start weeks) for the last $weeks weeks, honouring the campus filter. */
    public static function weekly(int $weeks = 26, ?int $campusId = null): array
    {
        $from = date('Y-m-d', strtotime('monday this week -' . ($weeks - 1) . ' weeks'));
        $sql = 'SELECT DATE_SUB(count_date, INTERVAL WEEKDAY(count_date) DAY) AS wk, SUM(adults) adults, SUM(youth) youth, SUM(children) children, SUM(online) online, SUM(visitors) visitors FROM {p}attendance_counts WHERE count_date >= ?';
        $p = [$from];
        if ($campusId) {
            $sql .= ' AND campus_id = ?';
            $p[] = $campusId;
        } else {
            [$cw, $cp] = Campus::where('x');
            $sql .= str_replace('x.', '', $cw);
            $p = array_merge($p, $cp);
        }
        $have = [];
        foreach (DB::all($sql . ' GROUP BY wk', $p) as $r) {
            $have[$r['wk']] = $r;
        }
        $out = [];
        for ($i = 0; $i < $weeks; $i++) {
            $wk = date('Y-m-d', strtotime($from . " +$i weeks"));
            $r = $have[$wk] ?? ['adults' => 0, 'youth' => 0, 'children' => 0, 'online' => 0, 'visitors' => 0];
            $out[] = ['week' => $wk, 'adults' => (int)$r['adults'], 'youth' => (int)$r['youth'], 'children' => (int)$r['children'], 'online' => (int)$r['online'], 'visitors' => (int)$r['visitors'],
                'total' => (int)$r['adults'] + (int)$r['youth'] + (int)$r['children'] + (int)$r['online'], 'recorded' => isset($have[$wk])];
        }
        return $out;
    }

    /** Headline numbers: last week, 4-week average and change against the 4 weeks before. */
    public function summary(): array
    {
        $w = self::weekly(9);
        $recorded = array_values(array_filter($w, fn($r) => $r['recorded']));
        $last = $recorded ? end($recorded) : null;
        $avg = function (array $rows) {
            $rows = array_filter($rows, fn($r) => $r['recorded']);
            return $rows ? (int)round(array_sum(array_column($rows, 'total')) / count($rows)) : null;
        };
        $recent = $avg(array_slice($w, -4));
        $before = $avg(array_slice($w, -8, 4));
        return ['last' => $last, 'avg4' => $recent, 'change' => ($recent !== null && $before) ? round(($recent - $before) / $before * 100) : null,
            'visitors4' => array_sum(array_column(array_slice($w, -4), 'visitors'))];
    }

    public function report(): void
    {
        if (!can('attendance.record') && !can('reports.view')) {
            abort(403);
        }
        $weeks = in_array((int)input('weeks'), [13, 26, 52], true) ? (int)input('weeks') : 26;
        $w = self::weekly($weeks);
        $byCampus = [];
        if (Campus::multi()) {
            foreach (Campus::all() as $cid => $cname) {
                $rows = self::weekly($weeks, (int)$cid);
                $rec = array_filter($rows, fn($r) => $r['recorded']);
                $byCampus[] = ['name' => $cname, 'avg' => $rec ? (int)round(array_sum(array_column($rec, 'total')) / count($rec)) : 0, 'visitors' => array_sum(array_column($rows, 'visitors')), 'weeks' => count($rec)];
            }
        }
        $lastYear = (int)DB::val('SELECT ROUND(SUM(adults + youth + children + online) / GREATEST(COUNT(DISTINCT YEARWEEK(count_date, 3)), 1)) FROM {p}attendance_counts WHERE count_date BETWEEN DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), INTERVAL ? WEEK) AND DATE_SUB(CURDATE(), INTERVAL 1 YEAR)', [$weeks]);
        if (input('export')) {
            audit('report.exported', null, null, 'attendance');
            csv_download('attendance-' . date('Y-m-d') . '.csv', ['week_starting', 'adults', 'youth', 'children', 'online', 'total', 'first_time_visitors'], array_map(fn($r) => [$r['week'], $r['adults'], $r['youth'], $r['children'], $r['online'], $r['total'], $r['visitors']], array_filter($w, fn($r) => $r['recorded'])));
        }
        view('attendance/report', ['title' => __('Attendance trends'), 'weeksN' => $weeks, 'weeks' => $w, 'byCampus' => $byCampus, 'lastYear' => $lastYear, 'summary' => $this->summary()]);
    }
}
