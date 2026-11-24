<?php
declare(strict_types=1);

/** Church service planning: run sheets with timings, songs (with CCLI numbers) and the people serving. */
final class ServicesController
{
    public static function itemTypes(): array
    {
        return ['song' => __('Song'), 'reading' => __('Bible reading'), 'sermon' => __('Sermon'), 'prayer' => __('Prayer'), 'announcement' => __('Announcements'), 'communion' => __('Communion'), 'offering' => __('Offering'), 'video' => __('Video'), 'other' => __('Other')];
    }

    private function find(int|string $id): array
    {
        $p = DB::row('SELECT * FROM {p}service_plans WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!can('services.plan') && !can('rosters.view') && !(person_id() && DB::val("SELECT 1 FROM {p}roster_assignments ra JOIN {p}rosters r ON r.id = ra.roster_id WHERE ra.person_id = ? AND r.service_date = ? AND ra.status IN ('pending','accepted')", [person_id(), $p['service_date']]))) {
            abort(403);
        }
        return $p;
    }

    public function index(): void
    {
        if (!can('services.plan') && !can('rosters.view')) {
            abort(403);
        }
        [$cw, $cp] = Campus::where('s');
        $plans = DB::all("SELECT s.*, (SELECT COUNT(*) FROM {p}plan_items i WHERE i.plan_id = s.id) AS items, (SELECT COALESCE(SUM(duration_minutes), 0) FROM {p}plan_items i WHERE i.plan_id = s.id) AS minutes FROM {p}service_plans s WHERE s.service_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)$cw ORDER BY s.service_date, s.start_time LIMIT 60", $cp);
        view('services/index', ['title' => __('Service planning'), 'plans' => $plans]);
    }

    public function store(): void
    {
        $date = input_date('service_date') ?? date('Y-m-d', strtotime('next sunday'));
        $id = DB::insert('service_plans', ['title' => mb_substr((string)input('title', '') ?: __('Sunday Service'), 0, 200), 'service_date' => $date, 'start_time' => input('start_time') ?: null, 'theme' => mb_substr((string)input('theme', ''), 0, 200) ?: null, 'campus_id' => Campus::fromInput(), 'created_by' => Auth::id()]);
        if (input_bool('template')) {
            $order = [['other', __('Welcome'), 3], ['song', __('Song'), 4], ['song', __('Song'), 4], ['prayer', __('Prayer'), 3], ['announcement', __('Announcements'), 3], ['reading', __('Bible reading'), 3], ['sermon', __('Sermon'), 30], ['song', __('Response song'), 4], ['other', __('Benediction'), 2]];
            foreach ($order as $i => [$t, $title, $m]) {
                DB::insert('plan_items', ['plan_id' => $id, 'sort' => $i + 1, 'item_type' => $t, 'title' => $title, 'duration_minutes' => $m]);
            }
        }
        audit('service.created', 'service_plan', $id);
        redirect('/services/' . $id);
    }

    public function show(string $id): void
    {
        $p = $this->find($id);
        $items = DB::all('SELECT i.*, s.title AS song_title, s.ccli_number, s.author, s.link_url, pe.first_name, pe.last_name, pe.preferred_name FROM {p}plan_items i LEFT JOIN {p}songs s ON s.id = i.song_id LEFT JOIN {p}people pe ON pe.id = i.person_id WHERE i.plan_id = ? ORDER BY i.sort, i.id', [$p['id']]);
        $t = $p['start_time'] ? strtotime($p['service_date'] . ' ' . $p['start_time']) : null;
        foreach ($items as &$it) {
            $it['at'] = $t ? date('H:i:s', $t) : null;
            if ($t) {
                $t += (int)$it['duration_minutes'] * 60;
            }
        }
        unset($it);
        $rosters = DB::all("SELECT r.id, r.title, g.name AS team, po.name AS position, pe.first_name, pe.last_name, pe.preferred_name, ra.status FROM {p}rosters r JOIN {p}groups g ON g.id = r.group_id JOIN {p}roster_assignments ra ON ra.roster_id = r.id JOIN {p}positions po ON po.id = ra.position_id LEFT JOIN {p}people pe ON pe.id = ra.person_id WHERE r.service_date = ? ORDER BY g.name, po.name", [$p['service_date']]);
        $people = [];
        foreach ($rosters as $r) {
            if ($r['first_name']) {
                $people[] = person_name($r);
            }
        }
        $songs = [];
        foreach (DB::all('SELECT id, title, default_key FROM {p}songs WHERE active = 1 ORDER BY title') as $s) {
            $songs[$s['id']] = $s['title'] . ($s['default_key'] ? ' (' . $s['default_key'] . ')' : '');
        }
        $print = input('print') === '1';
        view('services/show', ['title' => $p['title'], 'p' => $p, 'items' => $items, 'rosters' => $rosters, 'songs' => $songs, 'canEdit' => can('services.plan'), 'end' => $t, 'print' => $print], $print ? 'layout_print' : 'layout');
    }

    public function update(string $id): void
    {
        $p = $this->find($id);
        if (!can('services.plan')) {
            abort(403);
        }
        DB::update('service_plans', ['title' => mb_substr((string)input('title', $p['title']), 0, 200), 'service_date' => input_date('service_date') ?? $p['service_date'], 'start_time' => input('start_time') ?: null, 'theme' => mb_substr((string)input('theme', ''), 0, 200) ?: null, 'notes' => input('notes') ?: null, 'status' => input('status') === 'final' ? 'final' : 'draft'], 'id = ?', [$p['id']]);
        audit('service.updated', 'service_plan', (int)$p['id']);
        redirect('/services/' . $p['id']);
    }

    public function addItem(string $id): void
    {
        $p = $this->find($id);
        if (!can('services.plan')) {
            abort(403);
        }
        $type = isset(self::itemTypes()[input('item_type')]) ? input('item_type') : 'other';
        $song = $type === 'song' ? input_int('song_id') : null;
        $title = trim((string)input('title', '')) ?: ($song ? (string)DB::val('SELECT title FROM {p}songs WHERE id = ?', [$song]) : self::itemTypes()[$type]);
        DB::insert('plan_items', ['plan_id' => $p['id'], 'sort' => (int)DB::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM {p}plan_items WHERE plan_id = ?', [$p['id']]), 'item_type' => $type, 'title' => mb_substr($title, 0, 200), 'song_id' => $song, 'song_key' => mb_substr((string)input('song_key', ''), 0, 10) ?: ($song ? DB::val('SELECT default_key FROM {p}songs WHERE id = ?', [$song]) : null), 'duration_minutes' => max(0, (int)input_int('duration_minutes', 0)), 'person_text' => mb_substr((string)input('person_text', ''), 0, 120) ?: null, 'notes' => input('notes') ?: null]);
        redirect('/services/' . $p['id']);
    }

    public function updateItem(string $id): void
    {
        $it = DB::row('SELECT * FROM {p}plan_items WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!can('services.plan')) {
            abort(403);
        }
        $move = (string)input('move', '');
        if ($move === 'delete') {
            DB::run('DELETE FROM {p}plan_items WHERE id = ?', [$it['id']]);
        } elseif ($move === 'up' || $move === 'down') {
            $items = DB::col('SELECT id FROM {p}plan_items WHERE plan_id = ? ORDER BY sort, id', [$it['plan_id']]);
            $i = array_search($it['id'], $items);
            $j = $move === 'up' ? $i - 1 : $i + 1;
            if (isset($items[$j])) {
                [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
            }
            foreach ($items as $n => $iid) {
                DB::update('plan_items', ['sort' => $n + 1], 'id = ?', [$iid]);
            }
        } else {
            DB::update('plan_items', ['title' => mb_substr((string)input('title', $it['title']), 0, 200), 'song_key' => mb_substr((string)input('song_key', ''), 0, 10) ?: null, 'duration_minutes' => max(0, (int)input_int('duration_minutes', 0)), 'person_text' => mb_substr((string)input('person_text', ''), 0, 120) ?: null, 'notes' => input('notes') ?: null], 'id = ?', [$it['id']]);
        }
        redirect('/services/' . $it['plan_id']);
    }

    public function copy(string $id): void
    {
        $p = $this->find($id);
        if (!can('services.plan')) {
            abort(403);
        }
        $nid = DB::insert('service_plans', ['title' => $p['title'], 'service_date' => date('Y-m-d', strtotime($p['service_date'] . ' +7 days')), 'start_time' => $p['start_time'], 'campus_id' => $p['campus_id'], 'created_by' => Auth::id()]);
        foreach (DB::all('SELECT * FROM {p}plan_items WHERE plan_id = ? ORDER BY sort', [$p['id']]) as $it) {
            DB::insert('plan_items', ['plan_id' => $nid, 'sort' => $it['sort'], 'item_type' => $it['item_type'], 'title' => $it['item_type'] === 'song' ? __('Song') : $it['title'], 'duration_minutes' => $it['duration_minutes']]);
        }
        flash('success', __('Copied the order of service to next week (songs left blank).'));
        redirect('/services/' . $nid);
    }

    // Songs & CCLI -------------------------------------------------------------
    public function songs(): void
    {
        if (!can('services.plan')) {
            abort(403);
        }
        $songs = DB::all('SELECT s.*, (SELECT COUNT(*) FROM {p}plan_items i JOIN {p}service_plans p ON p.id = i.plan_id WHERE i.song_id = s.id AND p.service_date > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND p.service_date <= CURDATE()) AS uses, (SELECT MAX(p.service_date) FROM {p}plan_items i JOIN {p}service_plans p ON p.id = i.plan_id WHERE i.song_id = s.id AND p.service_date <= CURDATE()) AS last_used FROM {p}songs s ORDER BY s.active DESC, s.title');
        view('services/songs', ['title' => __('Songs'), 'songs' => $songs, 'edit' => input_int('edit') ? DB::row('SELECT * FROM {p}songs WHERE id = ?', [input_int('edit')]) : null]);
    }

    public function saveSong(): void
    {
        $url = trim((string)input('link_url', ''));
        $d = ['title' => mb_substr((string)input('title', ''), 0, 200), 'author' => mb_substr((string)input('author', ''), 0, 200) ?: null, 'ccli_number' => preg_replace('/\D/', '', (string)input('ccli_number', '')) ?: null, 'default_key' => mb_substr((string)input('default_key', ''), 0, 10) ?: null, 'tempo' => mb_substr((string)input('tempo', ''), 0, 20) ?: null, 'link_url' => preg_match('#^https?://#', $url) ? mb_substr($url, 0, 500) : null, 'notes' => input('notes') ?: null, 'active' => input('id') ? input_bool('active') : 1];
        if ($d['title'] === '') {
            redirect('/services/songs');
        }
        if ($id = input_int('id')) {
            DB::update('songs', $d, 'id = ?', [$id]);
        } else {
            DB::insert('songs', $d);
        }
        redirect('/services/songs');
    }

    /** Songs used in a period, for CCLI copy reporting. */
    public function ccli(): void
    {
        if (!can('services.plan')) {
            abort(403);
        }
        $from = input_date('from') ?? date('Y-m-d', strtotime('-6 months'));
        $to = input_date('to') ?? date('Y-m-d');
        $rows = DB::all('SELECT s.title, s.author, s.ccli_number, COUNT(*) AS times, MIN(p.service_date) AS first_used, MAX(p.service_date) AS last_used FROM {p}plan_items i JOIN {p}service_plans p ON p.id = i.plan_id JOIN {p}songs s ON s.id = i.song_id WHERE p.service_date BETWEEN ? AND ? GROUP BY s.id, s.title, s.author, s.ccli_number ORDER BY times DESC, s.title', [$from, $to]);
        if (input('export')) {
            csv_download("ccli-$from-to-$to.csv", ['title', 'author', 'ccli_number', 'times_used', 'first_used', 'last_used'], $rows);
        }
        view('services/ccli', ['title' => __('CCLI song usage'), 'rows' => $rows, 'from' => $from, 'to' => $to]);
    }
}
