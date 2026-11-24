<?php
declare(strict_types=1);

final class Events
{
    public static function taken(int $eventId): int
    {
        return (int)DB::val("SELECT COALESCE(SUM(1 + guests), 0) FROM {p}event_registrations WHERE event_id = ? AND status IN ('registered','attended')", [$eventId]);
    }

    /** The open form people use to register (and pay) for this event, if it has one. */
    public static function form(int $eventId): ?array
    {
        $f = DB::row("SELECT * FROM {p}forms WHERE event_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$eventId]);
        return $f ?: null;
    }

    /**
     * A confirmed form response becomes a registration. The form's own places limit decides who gets in, so a
     * paid place is never waitlisted here. Safe to call more than once.
     */
    public static function registerFromForm(array $ev, array $sub): ?int
    {
        if ($rid = DB::val('SELECT id FROM {p}event_registrations WHERE form_submission_id = ?', [$sub['id']])) {
            DB::run("UPDATE {p}event_registrations SET status = 'registered' WHERE id = ? AND status = 'cancelled'", [$rid]);
            return (int)$rid;
        }
        // Link to the respondent's profile only if the place is theirs (a parent may register a child).
        $pid = null;
        if ($sub['person_id'] && ($p = DB::row('SELECT * FROM {p}people WHERE id = ?', [$sub['person_id']]))) {
            $norm = fn(string $x) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($x)));
            if (in_array($norm((string)$sub['name']), [$norm(person_name($p)), $norm($p['first_name'] . ' ' . $p['last_name'])], true)) {
                $pid = (int)$p['id'];
            }
        }
        $rid = DB::insert('event_registrations', ['event_id' => $ev['id'], 'person_id' => $pid, 'name' => mb_substr((string)$sub['name'], 0, 160), 'email' => $sub['email'] ?: null,
            'guests' => max(0, (int)($sub['places'] ?? 1) - 1), 'status' => 'registered', 'form_submission_id' => $sub['id']]);
        self::confirmation($rid);
        return $rid;
    }

    /** Register someone, waitlisting when full. Returns [ok, message]. */
    public static function register(array $ev, array $who): array
    {
        if (self::form((int)$ev['id'])) {
            return [false, __('Please register for this event using its registration form.')];
        }
        if (!(int)$ev['registration_enabled']) {
            return [false, __('Registration is not open for this event.')];
        }
        if ($ev['registration_closes'] && strtotime($ev['registration_closes']) < time()) {
            return [false, __('Registration for this event has closed.')];
        }
        if (strtotime($ev['end_at']) < time()) {
            return [false, __('This event has already happened.')];
        }
        if (!empty($who['person_id']) && DB::val("SELECT 1 FROM {p}event_registrations WHERE event_id = ? AND person_id = ? AND status IN ('registered','waitlisted')", [$ev['id'], $who['person_id']])) {
            return [false, __('You are already registered.')];
        }
        if (!empty($who['email']) && empty($who['person_id']) && DB::val("SELECT 1 FROM {p}event_registrations WHERE event_id = ? AND email = ? AND status IN ('registered','waitlisted')", [$ev['id'], $who['email']])) {
            return [false, __('That email address is already registered for this event.')];
        }
        $guests = (int)($who['guests'] ?? 0);
        $full = $ev['capacity'] && self::taken((int)$ev['id']) + 1 + $guests > (int)$ev['capacity'];
        $status = $full ? 'waitlisted' : 'registered';
        $rid = DB::insert('event_registrations', ['event_id' => $ev['id'], 'person_id' => $who['person_id'] ?? null, 'name' => mb_substr($who['name'], 0, 160), 'email' => $who['email'] ?? null, 'phone' => $who['phone'] ?? null, 'guests' => $guests, 'status' => $status, 'notes' => $who['notes'] ?? null]);
        self::confirmation($rid);
        Notify::processQueue(5);
        return [true, $full ? __('The event is full — you have been added to the waiting list. We will email you if a place opens up.') : __('You are registered for %s. A confirmation has been emailed to you.', $ev['title'])];
    }

    public static function confirmation(int $registrationId): void
    {
        $r = DB::row('SELECT r.*, e.title, e.start_at, e.end_at, e.location, e.joining_instructions FROM {p}event_registrations r JOIN {p}events e ON e.id = r.event_id WHERE r.id = ?', [$registrationId]);
        if (!$r || !$r['email']) {
            return;
        }
        $waiting = $r['status'] === 'waitlisted';
        $html = '<p>' . e(__('Hi %s,', $r['name'])) . '</p><p>' . e($waiting ? __('You are on the waiting list for %s.', $r['title']) : __('You are registered for %s.', $r['title'])) . '</p>'
            . '<p><strong>' . e(fmt_datetime($r['start_at'])) . '</strong>' . ($r['location'] ? '<br>' . e($r['location']) : '') . '</p>'
            . (!$waiting && $r['joining_instructions'] ? '<h3>' . e(__('Joining instructions')) . '</h3><p>' . nl2br(e($r['joining_instructions'])) . '</p>' : '');
        $subject = ($waiting ? __('Waiting list: %s', $r['title']) : __('Registration confirmed: %s', $r['title']));
        if ($r['person_id']) {
            Notify::emailPerson((int)$r['person_id'], $subject, $html, true);
        } else {
            Notify::emailAddress($r['email'], $subject, $html);
        }
    }

    /** Cancel a registration and promote from the waiting list while places remain. */
    public static function cancel(int $registrationId): void
    {
        $r = DB::row('SELECT * FROM {p}event_registrations WHERE id = ?', [$registrationId]);
        if (!$r) {
            return;
        }
        DB::update('event_registrations', ['status' => 'cancelled'], 'id = ?', [$r['id']]);
        self::promote((int)$r['event_id']);
    }

    public static function promote(int $eventId): int
    {
        $ev = DB::row('SELECT * FROM {p}events WHERE id = ?', [$eventId]);
        $n = 0;
        if (!$ev || !$ev['capacity']) {
            foreach (DB::col("SELECT id FROM {p}event_registrations WHERE event_id = ? AND status = 'waitlisted'", [$eventId]) as $wid) {
                DB::update('event_registrations', ['status' => 'registered'], 'id = ?', [$wid]);
                self::confirmation((int)$wid);
                $n++;
            }
            return $n;
        }
        foreach (DB::all("SELECT * FROM {p}event_registrations WHERE event_id = ? AND status = 'waitlisted' ORDER BY id", [$eventId]) as $w) {
            if (self::taken($eventId) + 1 + (int)$w['guests'] > (int)$ev['capacity']) {
                break;
            }
            DB::update('event_registrations', ['status' => 'registered'], 'id = ?', [$w['id']]);
            self::confirmation((int)$w['id']);
            $n++;
        }
        return $n;
    }

    /** Rooms/equipment already booked over an interval (excluding a given booking). */
    public static function roomConflicts(int $roomId, string $start, string $end, ?int $exceptBooking = null): array
    {
        return DB::all("SELECT * FROM {p}room_bookings WHERE room_id = ? AND status IN ('requested','approved') AND start_at < ? AND end_at > ? AND id <> ?", [$roomId, $end, $start, $exceptBooking ?? 0]);
    }
}
