<?php
declare(strict_types=1);

/**
 * Applies database upgrades in database/migrations (NNN_name.sql, then NNN_name.php) in order.
 * Runs automatically on the first request after new files are uploaded, so upgrading is just
 * "upload the new version over the old one".
 */
final class Migrator
{
    public static function latest(): int
    {
        $max = 1;
        foreach (glob(BASE_DIR . '/database/migrations/*.{sql,php}', GLOB_BRACE) as $f) {
            $max = max($max, (int)basename($f));
        }
        return $max;
    }

    public static function current(): int
    {
        return (int)setting('db_version', '1');
    }

    public static function pending(): bool
    {
        return self::current() < self::latest();
    }

    public static function run(): array
    {
        $done = [];
        if (!self::pending()) {
            return $done;
        }
        $lock = DB::$prefix . 'migrate';
        if (!DB::val('SELECT GET_LOCK(?, 30)', [$lock])) {
            return $done;
        }
        try {
            setting('__flush');
            for ($v = self::current() + 1; $v <= self::latest(); $v++) {
                $num = str_pad((string)$v, 3, '0', STR_PAD_LEFT);
                foreach (glob(BASE_DIR . "/database/migrations/{$num}_*.sql") as $sqlFile) {
                    $sql = str_replace('{p}', DB::$prefix, (string)file_get_contents($sqlFile));
                    $sql = preg_replace('/^--.*$/m', '', $sql);
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        try {
                            DB::pdo()->exec($stmt);
                        } catch (PDOException $e) {
                            // Re-running a partly applied upgrade: ignore "already exists" errors only.
                            if (!in_array((int)($e->errorInfo[1] ?? 0), [1050, 1060, 1061, 1091], true)) {
                                throw $e;
                            }
                        }
                    }
                }
                foreach (glob(BASE_DIR . "/database/migrations/{$num}_*.php") as $phpFile) {
                    $fn = require $phpFile;
                    if (is_callable($fn)) {
                        $fn();
                    }
                }
                set_setting('db_version', (string)$v);
                $done[] = $v;
            }
            if ($done && PHP_SAPI !== 'cli' && class_exists('Auth', false)) {
                audit('system.upgraded', null, null, ['versions' => $done]);
            }
        } finally {
            DB::val('SELECT RELEASE_LOCK(?)', [$lock]);
        }
        return $done;
    }
}
