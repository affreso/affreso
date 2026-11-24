<?php
declare(strict_types=1);

/** Rooms and equipment, with conflict-checked booking requests. Anyone signed in can request; rooms.manage approves. */
final class RoomsController
{
    public function index(): void
    {
        $date = input_date('date') ?? date('Y-m-d');
        [$cw, $cp] = Campus::where('r');
        $rooms = DB::all("SELECT r.* FROM {p}rooms r WHERE 1=1$cw ORDER BY r.active DESC, r.is_equipment, r.name", $cp);
        $weekEnd = date('Y-m-d', strtotime($date . ' +13 days'));
        $bookings = DB::all("SELECT b.*, r.name AS room, p.first_name, p.last_name, p.preferred_name FROM {p}room_bookings b JOIN {p}rooms r ON r.id = b.room_id LEFT JOIN {p}users u ON u.id = b.requested_by LEFT JOIN {p}people p ON p.id = u.person_id WHERE b.status IN ('requested','approved') AND DATE(b.start_at) BETWEEN ? AND ? ORDER BY b.start_at", [$date, $weekEnd]);
        $requests = can('rooms.manage') ? DB::all("SELECT b.*, r.name AS room FROM {p}room_bookings b JOIN {p}rooms r ON r.id = b.room_id WHERE b.status = 'requested' AND b.end_at >= NOW() ORDER BY b.start_at") : [];
        $mine = DB::all('SELECT b.*, r.name AS room FROM {p}room_bookings b JOIN {p}rooms r ON r.id = b.room_id WHERE b.requested_by = ? AND b.end_at >= NOW() ORDER BY b.start_at', [Auth::id()]);
        view('rooms/index', ['title' => __('Rooms & bookings'), 'rooms' => $rooms, 'bookings' => $bookings, 'requests' => $requests, 'mine' => $mine, 'date' => $date, 'weekEnd' => $weekEnd]);
    }

    public function store(): void
    {
        $name = trim((string)input('name', ''));
        if ($name !== '') {
            $id = DB::insert('rooms', ['name' => mb_substr($name, 0, 120), 'capacity' => input_int('capacity'), 'description' => mb_substr((string)input('description', ''), 0, 255) ?: null, 'is_equipment' => input_bool('is_equipment'), 'campus_id' => Campus::fromInput()]);
            audit('room.created', 'room', $id);
        }
        redirect('/rooms');
    }

    public function update(string $id): void
    {
        DB::update('rooms', ['name' => mb_substr((string)input('name', ''), 0, 120), 'capacity' => input_int('capacity'), 'description' => mb_substr((string)input('description', ''), 0, 255) ?: null, 'is_equipment' => input_bool('is_equipment'), 'active' => input_bool('active')], 'id = ?', [(int)$id]);
        audit('room.updated', 'room', (int)$id);
        redirect('/rooms');
    }

    public function book(): void
    {
        $roomId = input_int('room_id');
        $start = input_datetime('start_at');
        $end = input_datetime('end_at');
        $title = trim((string)input('title', ''));
        if (!$roomId || !$start || !$end || $end <= $start || $title === '') {
            flash('error', __('Choose a room, a title, and a valid time range.'));
            redirect('/rooms');
        }
        if ($clash = Events::roomConflicts($roomId, $start, $end)) {
            flash('error', __('That time clashes with "%s" (%s – %s).', $clash[0]['title'], fmt_datetime($clash[0]['start_at']), fmt_time($clash[0]['end_at'])));
            redirect('/rooms', ['date' => substr($start, 0, 10)]);
        }
        $auto = can('rooms.manage');
        $id = DB::insert('room_bookings', ['room_id' => $roomId, 'title' => mb_substr($title, 0, 200), 'start_at' => $start, 'end_at' => $end, 'status' => $auto ? 'approved' : 'requested', 'requested_by' => Auth::id(), 'approved_by' => $auto ? Auth::id() : null, 'notes' => mb_substr((string)input('notes', ''), 0, 255) ?: null]);
        if (!$auto) {
            Notify::users(users_with_permission('rooms.manage'), __('Booking request: %s', $title), '<p>' . e(__('A new room or equipment booking is waiting for approval.')) . '</p>', url('/rooms'));
        }
        audit('booking.created', 'room_booking', $id);
        flash('success', $auto ? __('Booked.') : __('Booking requested. You will be notified when it is approved.'));
        redirect('/rooms', ['date' => substr($start, 0, 10)]);
    }

    public function decide(string $id): void
    {
        $b = DB::row('SELECT * FROM {p}room_bookings WHERE id = ?', [(int)$id]) ?? abort(404);
        $decision = (string)input('decision');
        $mine = (int)$b['requested_by'] === Auth::id();
        if ($decision === 'cancel' && ($mine || can('rooms.manage'))) {
            DB::update('room_bookings', ['status' => 'cancelled'], 'id = ?', [$b['id']]);
        } elseif (can('rooms.manage') && in_array($decision, ['approved', 'declined'], true)) {
            if ($decision === 'approved' && Events::roomConflicts((int)$b['room_id'], $b['start_at'], $b['end_at'], (int)$b['id'])) {
                flash('error', __('Cannot approve: another booking now overlaps.'));
                redirect('/rooms');
            }
            DB::update('room_bookings', ['status' => $decision, 'approved_by' => Auth::id()], 'id = ?', [$b['id']]);
            notify_user((int)$b['requested_by'], $decision === 'approved' ? __('Booking approved: %s', $b['title']) : __('Booking declined: %s', $b['title']), url('/rooms'));
        } else {
            abort(403);
        }
        audit('booking.' . $decision, 'room_booking', (int)$b['id']);
        redirect('/rooms');
    }
}
