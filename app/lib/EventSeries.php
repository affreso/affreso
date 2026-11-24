<?php
declare(strict_types=1);

/**
 * Repeating events. A series holds the repeat rule; each occurrence is an ordinary row in events
 * (so registrations, rooms, rosters and iCal feeds work unchanged). Occurrences are created up to
 * a rolling horizon and topped up nightly.
 */
final class EventSeries
{
    public const FREQS = ['weekly', 'monthly_date', 'monthly_weekday'];

    public static function horizonMonths(): int
    {
        return max(1, min(24, (int)setting('event_series_horizon', 12)));
    }

    /** Read the repeat fields from the event form. Returns null for "does not repeat". */
    public static function ruleFromInput(string $firstStart): ?array
    {
        $freq = (string)input('repeat', 'none');
        if (!in_array($freq, self::FREQS, true)) {
            return null;
        }
        $days = array_values(array_filter(array_map('intval', (array)($_POST['repeat_days'] ?? [])), fn($d) => $d >= 1 && $d <= 7));
        if ($freq === 'weekly' && !$days) {
            $days = [(int)date('N', strtotime($firstStart))];
        }
        sort($days);
        $until = input_date('repeat_until');
        $count = input_int('repeat_count');
        return [
            'freq' => $freq,
            'interval_n' => max(1, min(12, (int)input_int('repeat_interval', 1))),
            'weekdays' => $freq === 'weekly' ? implode(',', $days) : null,
            'until_date' => input('repeat_end') === 'until' ? $until : null,
            'max_count' => input('repeat_end') === 'count' && $count ? max(1, min(500, $count)) : null,
        ];
    }

    public static function sameRule(array $a, array $b): bool
    {
        foreach (['freq', 'interval_n', 'weekdays', 'until_date', 'max_count'] as $k) {
            if ((string)($a[$k] ?? '') !== (string)($b[$k] ?? '')) {
                return false;
            }
        }
        return true;
    }

    /** Plain-English description, e.g. "Every 2 weeks on Sunday, until 30 Jun 2027". */
    public static function describe(array $s, ?string $firstStart = null): string
    {
        $n = (int)$s['interval_n'];
        $names = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')];
        if ($s['freq'] === 'weekly') {
            $days = implode(', ', array_map(fn($d) => $names[(int)$d] ?? '', array_filter(explode(',', (string)$s['weekdays']))));
            $txt = $n === 1 ? __('Every week on %s', $days) : __('Every %d weeks on %s', $n, $days);
        } elseif ($s['freq'] === 'monthly_date') {
            $day = $firstStart ? (int)date('j', strtotime($firstStart)) : 1;
            $txt = $n === 1 ? __('Monthly on day %d', $day) : __('Every %d months on day %d', $n, $day);
        } else {
            $ts = $firstStart ? strtotime($firstStart) : time();
            $txt = ($n === 1 ? __('Monthly on the %s %s', self::ordinal(self::weekOfMonth($ts)), $names[(int)date('N', $ts)]) : __('Every %d months on the %s %s', $n, self::ordinal(self::weekOfMonth($ts)), $names[(int)date('N', $ts)]));
        }
        if ($s['until_date']) {
            $txt .= ', ' . __('until %s', fmt_date($s['until_date']));
        } elseif ($s['max_count']) {
            $txt .= ', ' . __('%d times', (int)$s['max_count']);
        }
        return $txt;
    }

    /** 1–4 for the nth weekday of the month; a 5th weekday becomes -1 ("last"). */
    public static function weekOfMonth(int $ts): int
    {
        $n = intdiv((int)date('j', $ts) - 1, 7) + 1;
        return $n >= 5 ? -1 : $n;
    }

    public static function ordinal(int $n): string
    {
        return [1 => __('first'), 2 => __('second'), 3 => __('third'), 4 => __('fourth'), -1 => __('last')][$n] ?? (string)$n;
    }

    /**
     * Start dates (Y-m-d) of every occurrence from the series' first start, in order,
     * up to and including $to. Pure function — used by generate() and the tests.
     */
    public static function dates(array $s, string $firstStart, string $to): array
    {
        $first = new DateTimeImmutable(substr($firstStart, 0, 10));
        $end = new DateTimeImmutable($to);
        if (!empty($s['until_date']) && new DateTimeImmutable($s['until_date']) < $end) {
            $end = new DateTimeImmutable($s['until_date']);
        }
        $max = !empty($s['max_count']) ? (int)$s['max_count'] : PHP_INT_MAX;
        $n = max(1, (int)$s['interval_n']);
        $out = [];
        if ($s['freq'] === 'weekly') {
            $days = array_map('intval', array_filter(explode(',', (string)$s['weekdays']))) ?: [(int)$first->format('N')];
            $weekStart = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
            for ($w = 0; count($out) < $max; $w += $n) {
                $wk = $weekStart->modify("+$w weeks");
                if ($wk > $end) {
                    break;
                }
                foreach ($days as $d) {
                    $day = $wk->modify('+' . ($d - 1) . ' days');
                    if ($day < $first || $day > $end || count($out) >= $max) {
                        continue;
                    }
                    $out[] = $day->format('Y-m-d');
                }
            }
            return $out;
        }
        $dom = (int)$first->format('j');
        $nth = self::weekOfMonth($first->getTimestamp());
        $dow = (int)$first->format('N');
        $monthStart = $first->modify('first day of this month');
        for ($m = 0; count($out) < $max; $m += $n) {
            $ms = $monthStart->modify("+$m months");
            if ($ms > $end) {
                break;
            }
            if ($s['freq'] === 'monthly_date') {
                if ($dom > (int)$ms->format('t')) {
                    continue; // e.g. the 31st in a 30-day month: skipped rather than moved
                }
                $day = $ms->setDate((int)$ms->format('Y'), (int)$ms->format('n'), $dom);
            } else {
                $names = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
                $ord = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', -1 => 'last'][$nth];
                $day = $ms->modify("$ord {$names[$dow]} of this month");
            }
            if ($day >= $first && $day <= $end) {
                $out[] = $day->format('Y-m-d');
            }
        }
        return $out;
    }

    /** Create the series for a just-saved event and generate its occurrences. Returns occurrences created. */
    public static function create(int $eventId, array $rule): int
    {
        $rule['created_by'] = Auth::id();
        $sid = DB::insert('event_series', $rule);
        DB::update('events', ['series_id' => $sid], 'id = ?', [$eventId]);
        return self::generate($sid);
    }

    /** The occurrence new ones are copied from: the latest one not edited on its own. */
    public static function template(int $seriesId): ?array
    {
        return DB::row('SELECT * FROM {p}events WHERE series_id = ? ORDER BY series_changed, start_at DESC LIMIT 1', [$seriesId])
            ?? null;
    }

    /** Top up occurrences to the rolling horizon. Idempotent. */
    public static function generate(int $seriesId): int
    {
        $s = DB::row('SELECT * FROM {p}event_series WHERE id = ?', [$seriesId]);
        $first = DB::row('SELECT * FROM {p}events WHERE series_id = ? ORDER BY start_at LIMIT 1', [$seriesId]);
        $tpl = self::template($seriesId);
        if (!$s || !$first || !$tpl) {
            return 0;
        }
        $horizon = date('Y-m-d', strtotime('+' . self::horizonMonths() . ' months'));
        $existing = array_flip(array_map(fn($d) => substr((string)$d, 0, 10), DB::col('SELECT start_at FROM {p}events WHERE series_id = ?', [$seriesId])));
        $skipped = array_flip(array_filter(explode(',', (string)$s['skip_dates'])));
        $time = substr($tpl['start_at'], 11);
        $dur = strtotime($tpl['end_at']) - strtotime($tpl['start_at']);
        $regCloseOffset = $tpl['registration_closes'] ? strtotime($tpl['start_at']) - strtotime($tpl['registration_closes']) : null;
        $rooms = DB::col("SELECT room_id FROM {p}room_bookings WHERE event_id = ? AND status IN ('requested','approved')", [$tpl['id']]);
        $made = 0;
        foreach (self::dates($s, $first['start_at'], $horizon) as $day) {
            if (isset($existing[$day]) || isset($skipped[$day]) || $day < date('Y-m-d')) {
                continue;
            }
            $start = $day . ' ' . $time;
            $row = [];
            foreach (['title', 'description', 'category', 'location', 'is_public', 'registration_enabled', 'capacity', 'joining_instructions', 'campus_id', 'created_by'] as $k) {
                $row[$k] = $tpl[$k];
            }
            $row += ['series_id' => $seriesId, 'start_at' => $start, 'end_at' => date('Y-m-d H:i:s', strtotime($start) + $dur), 'status' => 'scheduled',
                'registration_closes' => $regCloseOffset !== null ? date('Y-m-d H:i:s', strtotime($start) - $regCloseOffset) : null];
            $eid = DB::insert('events', $row);
            foreach ($rooms as $roomId) {
                if (!Events::roomConflicts((int)$roomId, $row['start_at'], $row['end_at'], null)) {
                    DB::insert('room_bookings', ['room_id' => $roomId, 'event_id' => $eid, 'title' => $row['title'], 'start_at' => $row['start_at'], 'end_at' => $row['end_at'], 'status' => 'approved', 'requested_by' => $tpl['created_by'], 'approved_by' => $tpl['created_by']]);
                }
            }
            $made++;
        }
        DB::update('event_series', ['generated_until' => $horizon], 'id = ?', [$seriesId]);
        return $made;
    }

    /** Nightly: extend every open-ended series. */
    public static function topUpAll(): int
    {
        $n = 0;
        foreach (DB::col('SELECT id FROM {p}event_series WHERE until_date IS NULL OR until_date >= CURDATE()') as $sid) {
            $n += self::generate((int)$sid);
        }
        return $n;
    }

    /** Remember a date was removed from a series so top-ups don't recreate it. */
    public static function skip(int $seriesId, string $date): void
    {
        $cur = array_filter(explode(',', (string)DB::val('SELECT skip_dates FROM {p}event_series WHERE id = ?', [$seriesId])));
        $cur[] = $date;
        DB::update('event_series', ['skip_dates' => implode(',', array_unique($cur))], 'id = ?', [$seriesId]);
    }

    /**
     * End a series before $fromStart and remove its later, untouched occurrences
     * (ones with registrations are kept and detached so nobody loses a booking).
     */
    public static function endBefore(int $seriesId, string $fromStart, int $keepEventId): void
    {
        DB::update('event_series', ['until_date' => date('Y-m-d', strtotime($fromStart . ' -1 day')), 'max_count' => null], 'id = ?', [$seriesId]);
        foreach (DB::all('SELECT id FROM {p}events WHERE series_id = ? AND start_at >= ? AND id <> ?', [$seriesId, $fromStart, $keepEventId]) as $ev) {
            if (DB::val("SELECT 1 FROM {p}event_registrations WHERE event_id = ? AND status IN ('registered','waitlisted','attended')", [$ev['id']])) {
                DB::update('events', ['series_id' => null], 'id = ?', [$ev['id']]);
                continue;
            }
            DB::run('DELETE FROM {p}room_bookings WHERE event_id = ?', [$ev['id']]);
            DB::run('DELETE FROM {p}events WHERE id = ?', [$ev['id']]);
        }
    }
}
