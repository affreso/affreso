<?php
declare(strict_types=1);

/**
 * Self check-in kiosks. A tablet is paired once by a staff member using a short-lived pairing code;
 * it then holds a long random device token in a cookie (only its SHA-256 hash is stored). Kiosks can
 * only find families by their full mobile number and only ever show first names and initials.
 */
final class Kiosk
{
    public const COOKIE = 'affreso_kiosk';
    private const PAIR_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private static ?array $current = null;

    public static function pairingCode(): string
    {
        $c = '';
        for ($i = 0; $i < 8; $i++) {
            $c .= self::PAIR_CHARS[random_int(0, strlen(self::PAIR_CHARS) - 1)];
        }
        return $c;
    }

    public static function normaliseCode(string $c): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c));
    }

    /** The kiosk this device is paired as, or null. */
    public static function current(): ?array
    {
        if (self::$current !== null) {
            return self::$current ?: null;
        }
        $tok = (string)($_COOKIE[self::COOKIE] ?? '');
        $k = strlen($tok) >= 40 ? DB::row('SELECT * FROM {p}kiosks WHERE token_hash = ? AND active = 1', [hash('sha256', $tok)]) : null;
        if ($k && (!$k['last_seen_at'] || strtotime($k['last_seen_at']) < time() - 300)) {
            DB::update('kiosks', ['last_seen_at' => date('Y-m-d H:i:s')], 'id = ?', [$k['id']]);
        }
        self::$current = $k ?: [];
        return $k ?: null;
    }

    public static function setCookie(string $token): void
    {
        setcookie(self::COOKIE, $token, ['expires' => $token === '' ? time() - 3600 : time() + 400 * 86400, 'path' => base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    }

    /** Pair this device using a pairing code. Returns the kiosk or null. */
    public static function pair(string $code): ?array
    {
        $code = self::normaliseCode($code);
        if (strlen($code) !== 8) {
            return null;
        }
        $k = DB::row('SELECT * FROM {p}kiosks WHERE pair_hash = ? AND pair_expires > NOW() AND active = 1', [hash('sha256', $code)]);
        if (!$k) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        DB::update('kiosks', ['token_hash' => hash('sha256', $token), 'pair_hash' => null, 'pair_expires' => null, 'paired_at' => date('Y-m-d H:i:s'), 'last_seen_at' => date('Y-m-d H:i:s')], 'id = ?', [$k['id']]);
        self::setCookie($token);
        self::$current = null;
        return $k;
    }

    /** Today's open check-in session for the kiosk's campus. */
    public static function session(array $k): ?array
    {
        return DB::row("SELECT * FROM {p}checkin_sessions WHERE status = 'open' AND session_date = CURDATE() AND (campus_id IS NULL OR ? IS NULL OR campus_id = ?) ORDER BY start_time IS NULL, ABS(TIMEDIFF(COALESCE(start_time, '00:00:00'), CURTIME())) LIMIT 1", [$k['campus_id'], $k['campus_id']]);
    }

    /** Throttle family lookups per kiosk (and per network address). */
    public static function throttled(array $k): bool
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)", ['kiosk:' . $k['id']]);
        $ip = (int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email LIKE 'kiosk:%' AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)", [client_ip()]);
        return $n >= 30 || $ip >= 15;
    }

    public static function logLookup(array $k, bool $found): void
    {
        DB::insert('login_attempts', ['email' => 'kiosk:' . $k['id'], 'ip' => client_ip(), 'success' => $found ? 1 : 0]);
    }

    /** Households with an adult whose mobile ends with the same last 9 digits. */
    public static function findHouseholds(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 8) {
            return [];
        }
        $tail = substr($digits, -9);
        $out = [];
        foreach (DB::all("SELECT household_id, mobile FROM {p}people WHERE archived_at IS NULL AND household_id IS NOT NULL AND household_role <> 'child' AND mobile IS NOT NULL AND mobile <> ''") as $p) {
            $m = preg_replace('/\D/', '', (string)$p['mobile']);
            if (strlen($m) >= 8 && substr($m, -9) === $tail) {
                $out[(int)$p['household_id']] = true;
            }
        }
        return array_keys($out);
    }

    /** "Lily N." — never full surnames on a shared screen. */
    public static function shortName(array $p): string
    {
        $first = trim((string)($p['preferred_name'] ?: $p['first_name']));
        return $first . ' ' . mb_strtoupper(mb_substr((string)$p['last_name'], 0, 1)) . '.';
    }
}
