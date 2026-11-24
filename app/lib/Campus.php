<?php
declare(strict_types=1);

/** Multi-campus support. Campus controls only appear once a church has more than one campus. */
final class Campus
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = array_column(DB::all('SELECT id, name FROM {p}campuses WHERE active = 1 ORDER BY id'), 'name', 'id');
        }
        return self::$all;
    }

    public static function multi(): bool
    {
        return count(self::all()) > 1;
    }

    /** The campus a user is restricted to (set on their account), or null for all campuses. */
    public static function restriction(): ?int
    {
        $u = user();
        return $u && !empty($u['campus_id']) ? (int)$u['campus_id'] : null;
    }

    /** The campus currently being viewed: the user's restriction, or their chosen filter (null = all). */
    public static function current(): ?int
    {
        if (!self::multi()) {
            return null;
        }
        if ($r = self::restriction()) {
            return $r;
        }
        $c = (int)($_SESSION['campus'] ?? 0);
        return isset(self::all()[$c]) ? $c : null;
    }

    /** SQL fragment limiting a table alias to the current campus (records without a campus are shared). */
    public static function where(string $alias): array
    {
        $c = self::current();
        return $c ? [" AND ($alias.campus_id = ? OR $alias.campus_id IS NULL)", [$c]] : ['', []];
    }

    public static function name(?int $id): string
    {
        return $id ? (self::all()[$id] ?? '') : '';
    }

    /** Value to store from a form: the chosen campus, forced to the user's own campus if restricted. */
    public static function fromInput(): ?int
    {
        if ($r = self::restriction()) {
            return $r;
        }
        $c = input_int('campus_id');
        if ($c && isset(self::all()[$c])) {
            return $c;
        }
        return self::current() ?? (self::multi() ? null : (array_key_first(self::all()) ?: null));
    }

    public static function field(?int $selected = null): string
    {
        if (!self::multi()) {
            return '';
        }
        if ($r = self::restriction()) {
            return '<input type="hidden" name="campus_id" value="' . $r . '">';
        }
        return '<label>' . e(__('Campus')) . '<select name="campus_id">' . options(self::all(), $selected ?? self::current(), __('All campuses')) . '</select></label>';
    }
}
