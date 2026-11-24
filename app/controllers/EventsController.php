<?php
declare(strict_types=1);

final class EventsController
{
    private function find(int|string $id): array
    {
        return DB::row('SELECT * FROM {p}events WHERE id = ?', [(int)$id]) ?? abort(404);
    }

    public function index(): void
    {
        if (!can('events.manage')) {
            redirect('/me/events');
        }
        $month = preg_match('/^\d{4}-\d{2}$/', (string)input('month', '')) ? input('month') : date('Y-m');
        $first = new DateTime($month . '-01');
        $start = (clone $first)->modify('monday this week');
        if ($start > $first) {
            $start->modify('-7 days');
        }
        $end = (clone $first)->modify('last day of this month')->modify('sunday this week');
        [$cw, $cp] = Campus::where('e');
        $events = DB::all("SELECT e.* FROM {p}events e WHERE e.start_at <= ? AND e.end_at >= ? AND e.status = 'scheduled'$cw ORDER BY e.start_at", array_merge([$end->format('Y-m-d 23:59:59'), $start->format('Y-m-d 00:00:00')], $cp));
        $bookings = DB::all("SELECT b.*, r.name AS room FROM {p}room_bookings b JOIN {p}rooms r ON r.id = b.room_id WHERE b.event_id IS NULL AND b.status = 'approved' AND b.start_at <= ? AND b.end_at >= ?", [$end->format('Y-m-d 23:59:59'), $start->format('Y-m-d 00:00:00')]);
        $byDay = [];
        foreach ($events as $ev) {
            $byDay[substr($ev['start_at'], 0, 10)][] = ['type' => 'event', 'row' => $ev];
        }
        foreach ($bookings as $b) {
            $byDay[substr($b['start_at'], 0, 10)][] = ['type' => 'booking', 'row' => $b];
        }
        $upcoming = DB::all("SELECT e.*, (SELECT COALESCE(SUM(1 + guests), 0) FROM {p}event_registrations r WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken, (SELECT COUNT(*) FROM {p}event_registrations r WHERE r.event_id = e.id AND r.status = 'waitlisted') AS waiting FROM {p}events e WHERE e.end_at >= NOW()$cw ORDER BY e.start_at LIMIT 30", $cp);
        view('events/index', ['title' => __('Events & calendar'), 'first' => $first, 'start' => $start, 'end' => $end, 'byDay' => $byDay, 'upcoming' => $upcoming, 'month' => $month]);
    }

    public function create(): void
    {
        $date = input_date('date') ?? date('Y-m-d', strtotime('next sunday'));
        view('events/form', ['title' => __('New event'), 'ev' => ['start_at' => $date . ' 10:00:00', 'end_at' => $date . ' 11:30:00', 'is_public' => 1], 'rooms' => $this->rooms(), 'roomIds' => []]);
    }

    public function edit(string $id): void
    {
        $ev = $this->find($id);
        $roomIds = array_map('intval', DB::col("SELECT room_id FROM {p}room_bookings WHERE event_id = ? AND status IN ('requested','approved')", [$ev['id']]));
        $series = $ev['series_id'] ? DB::row('SELECT * FROM {p}event_series WHERE id = ?', [$ev['series_id']]) : null;
        view('events/form', ['title' => __('Edit event'), 'ev' => $ev, 'rooms' => $this->rooms(), 'roomIds' => $roomIds, 'series' => $series]);
    }

    private function rooms(): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM {p}rooms WHERE active = 1 ORDER BY is_equipment, name') as $r) {
            $out[$r['id']] = $r['name'] . ($r['capacity'] ? ' (' . $r['capacity'] . ')' : '');
        }
        return $out;
    }

    private function data(): array
    {
        $start = input_datetime('start_at');
        $end = input_datetime('end_at');
        if (!$start || !$end || $end <= $start) {
            flash('error', __('Enter a valid start and end time (end after start).'));
            back('/events');
        }
        return [
            'title' => mb_substr((string)input('title', ''), 0, 200), 'description' => input('description') ?: null, 'category' => mb_substr((string)input('category', ''), 0, 60) ?: null,
            'location' => mb_substr((string)input('location', ''), 0, 200) ?: null, 'start_at' => $start, 'end_at' => $end, 'is_public' => input_bool('is_public'),
            'registration_enabled' => input_bool('registration_enabled'), 'registration_closes' => input_datetime('registration_closes'), 'capacity' => input_int('capacity') ?: null,
            'joining_instructions' => input('joining_instructions') ?: null, 'status' => input('status') === 'cancelled' ? 'cancelled' : 'scheduled', 'campus_id' => Campus::fromInput(),
        ];
    }

    /** Book requested rooms/equipment for the event; conflicts are reported, not double-booked. */
    private function syncRooms(int $eventId, array $d): array
    {
        $want = input_ids('room_ids');
        $conflicts = [];
        DB::run("UPDATE {p}room_bookings SET status = 'cancelled' WHERE event_id = ? AND room_id NOT IN (" . DB::in($want) . ')', array_merge([$eventId], $want));
        foreach ($want as $roomId) {
            $existing = DB::row("SELECT * FROM {p}room_bookings WHERE event_id = ? AND room_id = ? AND status IN ('requested','approved')", [$eventId, $roomId]);
            $clash = Events::roomConflicts($roomId, $d['start_at'], $d['end_at'], $existing ? (int)$existing['id'] : null);
            if ($clash) {
                $conflicts[] = DB::val('SELECT name FROM {p}rooms WHERE id = ?', [$roomId]) . ' (' . $clash[0]['title'] . ')';
                continue;
            }
            $status = can('rooms.manage') ? 'approved' : 'requested';
            if ($existing) {
                DB::update('room_bookings', ['start_at' => $d['start_at'], 'end_at' => $d['end_at'], 'title' => $d['title']], 'id = ?', [$existing['id']]);
            } else {
                DB::insert('room_bookings', ['room_id' => $roomId, 'event_id' => $eventId, 'title' => $d['title'], 'start_at' => $d['start_at'], 'end_at' => $d['end_at'], 'status' => $status, 'requested_by' => Auth::id(), 'approved_by' => $status === 'approved' ? Auth::id() : null]);
            }
        }
        return $conflicts;
    }

    public function store(): void
    {
        $d = $this->data();
        if ($d['title'] === '') {
            flash('error', __('Title is required.'));
            back('/events/new');
        }
        $d['created_by'] = Auth::id();
        $id = DB::insert('events', $d);
        $conf = $this->syncRooms($id, $d);
        audit('event.created', 'event', $id);
        if ($rule = EventSeries::ruleFromInput($d['start_at'])) {
            $made = EventSeries::create($id, $rule);
            audit('event.series_created', 'event', $id, ['occurrences' => $made + 1]);
            flash('success', __('Repeating event created, with %d dates scheduled so far. More are added automatically.', $made + 1));
        } else {
            flash('success', __('Event created.'));
        }
        if ($conf) {
            flash('warning', __('Not booked because of a clash: %s', implode(', ', $conf)));
        }
        redirect('/events/' . $id);
    }

    public function update(string $id): void
    {
        $ev = $this->find($id);
        $d = $this->data();
        $scope = $ev['series_id'] && input('scope') === 'following' ? 'following' : 'one';
        $conf = $this->apply($ev, $d + ['series_changed' => $ev['series_id'] && $scope === 'one' ? 1 : 0]);
        if ($ev['series_id'] && $scope === 'following') {
            $sid = (int)$ev['series_id'];
            $series = DB::row('SELECT * FROM {p}event_series WHERE id = ?', [$sid]);
            $rule = EventSeries::ruleFromInput($d['start_at']);
            if (!$rule) {
                // "Does not repeat" chosen for this and following: stop the series here.
                EventSeries::endBefore($sid, $ev['start_at'], (int)$ev['id']);
                DB::update('event_series', ['until_date' => substr($d['start_at'], 0, 10)], 'id = ?', [$sid]);
                flash('success', __('This event no longer repeats after this date.'));
            } elseif (!EventSeries::sameRule($rule, $series) || substr($d['start_at'], 0, 10) !== substr($ev['start_at'], 0, 10)) {
                // New pattern: end the old series before this date and start a new one from here.
                EventSeries::endBefore($sid, $ev['start_at'], (int)$ev['id']);
                $first = (int)DB::val('SELECT id FROM {p}events WHERE series_id = ? ORDER BY start_at LIMIT 1', [$sid]);
                if ($first === (int)$ev['id']) {
                    DB::run('DELETE FROM {p}event_series WHERE id = ?', [$sid]);
                }
                DB::update('events', ['series_id' => null], 'id = ?', [$ev['id']]);
                $made = EventSeries::create((int)$ev['id'], $rule);
                flash('success', __('Repeat pattern changed: %d dates scheduled from this event onwards.', $made + 1));
            } else {
                // Same pattern: copy the changes (and new times) to every following date.
                $time = substr($d['start_at'], 11);
                $dur = strtotime($d['end_at']) - strtotime($d['start_at']);
                $n = 0;
                foreach (DB::all('SELECT * FROM {p}events WHERE series_id = ? AND start_at > ? AND id <> ? ORDER BY start_at', [$sid, $ev['start_at'], $ev['id']]) as $o) {
                    $start = substr($o['start_at'], 0, 10) . ' ' . $time;
                    $d2 = array_merge($d, ['start_at' => $start, 'end_at' => date('Y-m-d H:i:s', strtotime($start) + $dur), 'series_changed' => 0]);
                    if ($d['registration_closes']) {
                        $d2['registration_closes'] = date('Y-m-d H:i:s', strtotime($start) - (strtotime($d['start_at']) - strtotime($d['registration_closes'])));
                    }
                    $conf = array_merge($conf, $this->apply($o, $d2));
                    $n++;
                }
                flash('success', __('Saved this event and %d following dates.', $n));
            }
        } elseif (!$ev['series_id'] && ($rule = EventSeries::ruleFromInput($d['start_at']))) {
            $made = EventSeries::create((int)$ev['id'], $rule);
            flash('success', __('Event saved and now repeats: %d more dates scheduled.', $made));
        } else {
            flash('success', __('Event saved.'));
        }
        if ($conf) {
            flash('warning', __('Not booked because of a clash: %s', implode(', ', array_unique($conf))));
        }
        redirect('/events/' . $ev['id']);
    }

    /** Save one event: rooms, cancellation notices and waiting-list promotion. Returns room clashes. */
    private function apply(array $ev, array $d): array
    {
        DB::update('events', $d, 'id = ?', [$ev['id']]);
        $conf = $d['status'] === 'cancelled' ? [] : $this->syncRooms((int)$ev['id'], $d);
        if ($d['status'] === 'cancelled' && $ev['status'] !== 'cancelled') {
            DB::run("UPDATE {p}room_bookings SET status = 'cancelled' WHERE event_id = ?", [$ev['id']]);
            foreach (DB::all("SELECT * FROM {p}event_registrations WHERE event_id = ? AND status IN ('registered','waitlisted')", [$ev['id']]) as $r) {
                $html = '<p>' . e(__('Unfortunately %s on %s has been cancelled.', $ev['title'], fmt_datetime($ev['start_at']))) . '</p>';
                $r['person_id'] ? Notify::emailPerson((int)$r['person_id'], __('Cancelled: %s', $ev['title']), $html, true) : ($r['email'] ? Notify::emailAddress($r['email'], __('Cancelled: %s', $ev['title']), $html) : null);
            }
        }
        if ($d['capacity'] === null || (int)$d['capacity'] > (int)$ev['capacity']) {
            Events::promote((int)$ev['id']);
        }
        audit('event.updated', 'event', (int)$ev['id']);
        return $conf;
    }

    /** Remove one date from a repeating event (only when nobody has registered). */
    public function removeOccurrence(string $id): void
    {
        $ev = $this->find($id);
        if (!$ev['series_id']) {
            abort(404);
        }
        if (DB::val("SELECT 1 FROM {p}event_registrations WHERE event_id = ? AND status IN ('registered','waitlisted','attended')", [$ev['id']])) {
            flash('error', __('People have registered for this date. Cancel it instead, so they are told.'));
            redirect('/events/' . $ev['id']);
        }
        EventSeries::skip((int)$ev['series_id'], substr($ev['start_at'], 0, 10));
        DB::run('DELETE FROM {p}room_bookings WHERE event_id = ?', [$ev['id']]);
        DB::run('DELETE FROM {p}events WHERE id = ?', [$ev['id']]);
        audit('event.occurrence_removed', 'event', (int)$ev['id'], ['date' => substr($ev['start_at'], 0, 10)]);
        flash('success', __('That date was removed from the series.'));
        redirect('/events');
    }

    public function show(string $id): void
    {
        $ev = $this->find($id);
        if (!can('events.manage')) {
            redirect('/me/events');
        }
        $regs = DB::all('SELECT * FROM {p}event_registrations WHERE event_id = ? ORDER BY FIELD(status, "registered", "attended", "waitlisted", "no_show", "cancelled"), id', [$ev['id']]);
        $bookings = DB::all('SELECT b.*, r.name AS room FROM {p}room_bookings b JOIN {p}rooms r ON r.id = b.room_id WHERE b.event_id = ? AND b.status <> "cancelled"', [$ev['id']]);
        $series = $ev['series_id'] ? DB::row('SELECT * FROM {p}event_series WHERE id = ?', [$ev['series_id']]) : null;
        $siblings = $series ? DB::all("SELECT id, start_at, status FROM {p}events WHERE series_id = ? AND start_at >= CURDATE() ORDER BY start_at LIMIT 8", [$series['id']]) : [];
        $firstStart = $series ? (string)DB::val('SELECT MIN(start_at) FROM {p}events WHERE series_id = ?', [$series['id']]) : null;
        view('events/show', ['title' => $ev['title'], 'ev' => $ev, 'regs' => $regs, 'taken' => Events::taken((int)$ev['id']), 'bookings' => $bookings, 'series' => $series, 'siblings' => $siblings, 'firstStart' => $firstStart,
            'form' => DB::row('SELECT * FROM {p}forms WHERE event_id = ? ORDER BY status = \'open\' DESC, id DESC LIMIT 1', [$ev['id']])]);
    }

    public function addRegistration(string $id): void
    {
        $ev = $this->find($id);
        $pid = input_int('person_id');
        $p = $pid ? DB::row('SELECT * FROM {p}people WHERE id = ?', [$pid]) : null;
        $name = $p ? person_name($p) : trim((string)input('name', ''));
        if ($name === '') {
            back('/events/' . $ev['id']);
        }
        $ev['registration_enabled'] = 1;
        $ev['registration_closes'] = null;
        [$ok, $msg] = Events::register($ev, ['person_id' => $pid, 'name' => $name, 'email' => $p['email'] ?? (input('email') ?: null), 'phone' => $p['mobile'] ?? (input('phone') ?: null), 'guests' => max(0, (int)input_int('guests', 0))]);
        flash($ok ? 'success' : 'error', $msg);
        redirect('/events/' . $ev['id']);
    }

    public function updateRegistration(string $id): void
    {
        $r = DB::row('SELECT * FROM {p}event_registrations WHERE id = ?', [(int)$id]) ?? abort(404);
        $status = (string)input('status');
        if ($status === 'cancelled') {
            Events::cancel((int)$r['id']);
        } elseif (in_array($status, ['registered', 'attended', 'no_show', 'waitlisted'], true)) {
            DB::update('event_registrations', ['status' => $status], 'id = ?', [$r['id']]);
        }
        Notify::processQueue(5);
        redirect('/events/' . $r['event_id']);
    }

    public function export(string $id): void
    {
        $ev = $this->find($id);
        audit('event.exported', 'event', (int)$ev['id']);
        csv_download('event-' . $ev['id'] . '-registrations.csv', ['name', 'email', 'phone', 'guests', 'status', 'registered_at'], DB::all('SELECT name, email, phone, guests, status, created_at FROM {p}event_registrations WHERE event_id = ? ORDER BY id', [$ev['id']]));
    }
}
