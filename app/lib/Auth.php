<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    private static bool $loading = false;
    private static array $perms = [];

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (PHP_SAPI === 'cli' || empty($_SESSION['uid'])) {
            return null;
        }
        $timeout = max(5, (int)setting('session_timeout_minutes', 60)) * 60;
        if (time() - (int)($_SESSION['last_activity'] ?? 0) > $timeout) {
            self::logout();
            flash('info', __('You were signed out after a period of inactivity.'));
            return null;
        }
        $_SESSION['last_activity'] = time();
        $u = DB::row("SELECT u.*, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE u.id = ? AND u.status = 'active'", [(int)$_SESSION['uid']]);
        if (!$u || (int)($_SESSION['pwv'] ?? 0) !== crc32((string)$u['password_hash'])) {
            self::logout();
            return null;
        }
        self::$user = $u;
        self::$perms = array_flip(DB::col('SELECT DISTINCT rp.permission FROM {p}role_permissions rp JOIN {p}user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?', [$u['id']]));
        return $u;
    }

    /** The current user if already loaded, without triggering a load (safe inside translation calls). */
    public static function peek(): ?array
    {
        if (!self::$loaded && !empty($_SESSION['uid']) && !self::$loading) {
            self::$loading = true;
            self::user();
            self::$loading = false;
        }
        return self::$user;
    }

    public static function can(string $perm): bool
    {
        return self::user() !== null && isset(self::$perms[$perm]);
    }

    public static function permissions(): array
    {
        self::user();
        return array_keys(self::$perms);
    }

    public static function roleSlugs(int $userId): array
    {
        return DB::col('SELECT r.slug FROM {p}roles r JOIN {p}user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$userId]);
    }

    /** Throttle: max 5 failures per account and 20 per IP in 15 minutes. */
    public static function throttled(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $byIp = (int)DB::val('SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [client_ip(), $since]);
        $byEmail = (int)DB::val('SELECT COUNT(*) FROM {p}login_attempts WHERE email = ? AND success = 0 AND created_at > ?', [$email, $since]);
        return $byIp >= 20 || $byEmail >= 5;
    }

    public static function recordAttempt(string $email, bool $ok): void
    {
        DB::insert('login_attempts', ['email' => mb_substr($email, 0, 190), 'ip' => client_ip(), 'success' => $ok ? 1 : 0]);
    }

    public static function attempt(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        if (self::throttled($email)) {
            return ['throttled', null];
        }
        $u = DB::row('SELECT * FROM {p}users WHERE email = ?', [$email]);
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, $hash) && $u && $u['status'] === 'active';
        if ($ok && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $ok = false;
        }
        self::recordAttempt($email, $ok);
        if (!$ok) {
            if ($u) {
                audit('login.failed', 'user', (int)$u['id']);
            }
            return ['invalid', null];
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        return ['ok', DB::row('SELECT * FROM {p}users WHERE id = ?', [$u['id']])];
    }

    public static function login(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int)$u['id'], 'pwv' => crc32((string)$u['password_hash']), 'last_activity' => time(), '_csrf' => bin2hex(random_bytes(32))];
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'failed_logins' => 0], 'id = ?', [$u['id']]);
        self::$loaded = false;
        audit('login.success', 'user', (int)$u['id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = true;
    }

    public static function needsMfa(): bool
    {
        $u = self::user();
        if (!$u || (int)$u['mfa_enabled'] === 1 || !setting('mfa_required_for_staff', '1') || Demo::isDemoUser((int)$u['id'])) {
            return false;
        }
        return (bool)array_intersect(self::permissions(), sensitive_permissions());
    }

    /** Would this user be forced to use MFA (ignoring whether it is currently on)? */
    public static function needsMfaIfDisabled(): bool
    {
        return self::user() !== null && !Demo::isDemoUser(self::id()) && (bool)setting('mfa_required_for_staff', '1') && (bool)array_intersect(self::permissions(), sensitive_permissions());
    }

    public static function enforceMfaSetup(): void
    {
        if (!self::needsMfa()) {
            return;
        }
        $r = '/' . trim((string)($GLOBALS['AFFRESO_ROUTE'] ?? ''), '/');
        if (!in_array($r, ['/account/mfa', '/account/mfa/enable', '/logout'], true)) {
            flash('warning', __('Your role can see sensitive information, so you need to turn on two-step verification before continuing.'));
            redirect('/account/mfa');
        }
    }

    public static function validatePassword(string $pw): ?string
    {
        if (mb_strlen($pw) < 10) {
            return __('Passwords must be at least 10 characters.');
        }
        if (preg_match('/^(.)\1+$/', $pw) || in_array(strtolower($pw), ['password123', '1234567890', 'qwertyuiop', 'church12345'], true)) {
            return __('Please choose a less predictable password.');
        }
        return null;
    }

    /** Create a single-use set-password link (invites and resets). */
    public static function resetLink(int $userId, int $hours = 2): string
    {
        $token = Crypto::token(32);
        DB::insert('password_resets', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600)]);
        return url('/password/reset', ['token' => $token], true);
    }
}
