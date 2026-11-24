<?php
declare(strict_types=1);

// ---------------------------------------------------------------- config & settings
function config(string $key, mixed $default = null): mixed
{
    $c = $GLOBALS['AFFRESO_CONFIG'] ?? [];
    foreach (explode('.', $key) as $k) {
        if (!is_array($c) || !array_key_exists($k, $c)) {
            return $default;
        }
        $c = $c[$k];
    }
    return $c;
}

function setting(string $k, mixed $default = null): mixed
{
    static $cache = null;
    if ($k === '__flush') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        if (!empty($GLOBALS['AFFRESO_CONFIG'])) {
            foreach (DB::all('SELECT k, v FROM {p}settings') as $r) {
                $cache[$r['k']] = $r['v'];
            }
        }
    }
    return array_key_exists($k, $cache) && $cache[$k] !== null ? $cache[$k] : $default;
}

function set_setting(string $k, ?string $v): void
{
    DB::run('INSERT INTO {p}settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
    setting('__flush');
}

// ---------------------------------------------------------------- output & urls
function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Interface language: the signed-in person's choice, else the church default. */
function current_language(): string
{
    $lang = '';
    if (!empty($GLOBALS['AFFRESO_CONFIG']) && PHP_SAPI !== 'cli') {
        $u = Auth::peek();
        $lang = (string)($u['language'] ?? '');
    }
    if ($lang === '' && !empty($GLOBALS['AFFRESO_CONFIG'])) {
        $lang = (string)setting('language', 'en');
    }
    $lang = preg_replace('/[^A-Za-z_\-]/', '', $lang) ?: 'en';
    return is_file(BASE_DIR . '/lang/' . $lang . '.php') ? $lang : 'en';
}

function is_rtl(): bool
{
    return in_array(strtolower(substr(current_language(), 0, 2)), ['ar', 'fa', 'he', 'ur', 'ps', 'ku'], true);
}

/** Languages available to choose from (files in /lang, excluding the template). */
function available_languages(): array
{
    $names = ['en' => 'English', 'zh-CN' => '简体中文', 'zh-TW' => '繁體中文', 'ko' => '한국어', 'ja' => '日本語', 'vi' => 'Tiếng Việt', 'id' => 'Bahasa Indonesia', 'es' => 'Español', 'pt' => 'Português', 'fa' => 'فارسی', 'ar' => 'العربية', 'hi' => 'हिन्दी', 'tl' => 'Tagalog', 'sm' => 'Gagana Samoa', 'to' => 'Lea faka-Tonga', 'el' => 'Ελληνικά', 'it' => 'Italiano', 'de' => 'Deutsch', 'fr' => 'Français'];
    $out = ['en' => 'English'];
    foreach (glob(BASE_DIR . '/lang/*.php') as $f) {
        $code = basename($f, '.php');
        if ($code !== 'TEMPLATE') {
            $out[$code] = $names[$code] ?? $code;
        }
    }
    return $out;
}

/** Translate a UI string. English text is the key; other languages live in /lang/<code>.php. */
function __(string $s, mixed ...$args): string
{
    static $dicts = [];
    $lang = current_language();
    if (!isset($dicts[$lang])) {
        $f = BASE_DIR . '/lang/' . $lang . '.php';
        $dicts[$lang] = $lang !== 'en' && is_file($f) ? (require $f) : [];
    }
    $dict = $dicts[$lang];
    $t = isset($dict[$s]) && $dict[$s] !== '' ? $dict[$s] : $s;
    return $args ? vsprintf($t, $args) : $t;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir === '.' ? '' : $dir, '/');
}

function base_url(): string
{
    $configured = (string)config('base_url', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host . base_path();
}

function url(string $path = '/', array $query = [], bool $absolute = false): string
{
    $path = '/' . ltrim($path, '/');
    $root = $absolute ? base_url() : base_path();
    if (config('pretty_urls', false)) {
        $u = $root . $path;
        return $query ? $u . '?' . http_build_query($query) : $u;
    }
    $q = array_merge(['r' => $path], $query);
    return $root . '/index.php?' . http_build_query($q);
}

/** url() for a path that may include a query string, e.g. "/people?status=visitor". */
function url_from(string $pathWithQuery, bool $absolute = false): string
{
    [$path, $qs] = array_pad(explode('?', $pathWithQuery, 2), 2, '');
    parse_str($qs, $q);
    return url($path, $q, $absolute);
}

function asset(string $p): string
{
    $file = BASE_DIR . '/assets/' . ltrim($p, '/');
    $v = is_file($file) ? filemtime($file) : AFFRESO_VERSION;
    return base_path() . '/assets/' . ltrim($p, '/') . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    $frag = '';
    if (str_contains($path, '#')) {
        [$path, $frag] = explode('#', $path, 2);
        $frag = '#' . $frag;
    }
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path, $query)) . $frag);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => __('Access denied'), 404 => __('Page not found'), 405 => __('Method not allowed'), 419 => __('Session expired'), 429 => __('Too many attempts'), 500 => __('Something went wrong')];
    $title = $titles[$code] ?? __('Error');
    $message = $message ?: ($code === 403 ? __('You do not have permission to view this page. If you think you should, contact your church administrator.') : ($code === 419 ? __('Your form expired. Please go back, refresh the page and try again.') : ''));
    try {
        if (empty($GLOBALS['AFFRESO_CONFIG'])) {
            throw new RuntimeException('not installed');
        }
        ob_start();
        view('error', ['code' => $code, 'title' => $title, 'message' => $message], user() ? 'layout' : 'layout_public');
        ob_end_flush();
    } catch (Throwable) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title><div style="font-family:sans-serif;max-width:560px;margin:60px auto"><h1>' . e($title) . '</h1><p>' . e($message) . '</p></div>';
    }
    exit;
}

function view(string $tpl, array $data = [], ?string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $tpl . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require APP_DIR . '/views/' . $layout . '.php';
    } else {
        echo $content;
    }
}

function partial(string $tpl, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/' . $tpl . '.php';
}

// ---------------------------------------------------------------- request
function input(string $k, mixed $default = null): mixed
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $k, ?int $default = null): ?int
{
    $v = input($k);
    return ($v === null || $v === '' || !is_numeric($v)) ? $default : (int)$v;
}

function input_date(string $k): ?string
{
    $v = (string)input($k, '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function input_datetime(string $k): ?string
{
    $v = str_replace('T', ' ', (string)input($k, ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v)) {
        return strlen($v) === 16 ? $v . ':00' : $v;
    }
    return null;
}

function input_bool(string $k): int
{
    return !empty($_POST[$k]) ? 1 : 0;
}

function input_ids(string $k): array
{
    $v = $_POST[$k] ?? [];
    return array_values(array_unique(array_filter(array_map('intval', is_array($v) ? $v : [$v]))));
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------- flash & old input
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ---------------------------------------------------------------- csrf
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
        abort(419);
    }
}

// ---------------------------------------------------------------- auth helpers
function user(): ?array
{
    return Auth::user();
}

function person_id(): ?int
{
    $u = Auth::user();
    return $u && $u['person_id'] ? (int)$u['person_id'] : null;
}

function can(string $perm): bool
{
    return Auth::can($perm);
}

function require_login(): void
{
    if (!Auth::user()) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['_intended'] = (string)($GLOBALS['AFFRESO_ROUTE'] ?? '/');
        }
        redirect('/login');
    }
    Auth::enforceMfaSetup();
}

function require_perm(string $perm): void
{
    if (!can($perm)) {
        abort(403);
    }
}

/** Does the current user lead (leader or assistant) the given group? */
function leads_group(int $groupId): bool
{
    $pid = person_id();
    if (!$pid) {
        return false;
    }
    return (bool)DB::val("SELECT 1 FROM {p}group_members WHERE group_id = ? AND person_id = ? AND member_role IN ('leader','assistant')", [$groupId, $pid]);
}

function led_group_ids(): array
{
    $pid = person_id();
    return $pid ? array_map('intval', DB::col("SELECT group_id FROM {p}group_members WHERE person_id = ? AND member_role IN ('leader','assistant')", [$pid])) : [];
}

function can_manage_group(int $groupId): bool
{
    return can('groups.manage') || leads_group($groupId);
}

function can_manage_roster_for(int $groupId): bool
{
    return can('rosters.manage') || leads_group($groupId);
}

// ---------------------------------------------------------------- audit & notifications
function audit(string $action, ?string $entity = null, ?int $entityId = null, mixed $details = null): void
{
    DB::insert('audit_log', [
        'user_id' => Auth::id(),
        'action' => $action,
        'entity' => $entity,
        'entity_id' => $entityId,
        'details' => $details === null ? null : (is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE)),
        'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
    ]);
}

function notify_user(?int $userId, string $title, ?string $link = null): void
{
    if ($userId) {
        DB::insert('notifications', ['user_id' => $userId, 'title' => mb_substr($title, 0, 200), 'link' => $link]);
    }
}

function user_id_for_person(?int $personId): ?int
{
    if (!$personId) {
        return null;
    }
    $id = DB::val("SELECT id FROM {p}users WHERE person_id = ? AND status = 'active'", [$personId]);
    return $id ? (int)$id : null;
}

/** User ids holding a permission (for routing alerts to the right people). */
function users_with_permission(string $perm): array
{
    return array_map('intval', DB::col("SELECT DISTINCT u.id FROM {p}users u JOIN {p}user_roles ur ON ur.user_id = u.id JOIN {p}role_permissions rp ON rp.role_id = ur.role_id WHERE rp.permission = ? AND u.status = 'active'", [$perm]));
}

// ---------------------------------------------------------------- formatting
/** Format a timestamp with a PHP date() pattern, localised to the interface language when intl is available. */
/** ICU locale for dates: always the Gregorian calendar and Western digits, to match the rest of the interface. */
function icu_locale(string $lang): string
{
    return str_replace('-', '_', $lang) . '@calendar=gregorian;numbers=latn';
}

function local_date(string $fmt, int $ts): string
{
    $lang = current_language();
    if ($lang === 'en' || !class_exists('IntlDateFormatter') || $fmt === 'j' || $fmt === 'd') {
        return date($fmt, $ts);
    }
    // Prefer the language's own date order (e.g. 11月29日周日, 11월 29일 (일)): turn the PHP format into an
    // ICU skeleton and let ICU choose the best local pattern.
    if (class_exists('IntlDatePatternGenerator') && preg_match('/^[djDlmnMFYyHGhgisaA ,:\/.\-–]+$/u', $fmt)) {
        static $sk = ['d' => 'dd', 'j' => 'd', 'D' => 'EEE', 'l' => 'EEEE', 'm' => 'MM', 'n' => 'M', 'M' => 'MMM', 'F' => 'MMMM', 'Y' => 'y', 'y' => 'yy', 'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h', 'i' => 'mm', 's' => 'ss'];
        $skeleton = '';
        foreach (str_split($fmt) as $ch) {
            $skeleton .= $sk[$ch] ?? '';
        }
        static $gens = [];
        $loc = icu_locale($lang);
        $gens[$loc] ??= new IntlDatePatternGenerator($loc);
        $best = $skeleton !== '' ? $gens[$loc]->getBestPattern($skeleton) : false;
        if ($best) {
            $f = new IntlDateFormatter($loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), null, $best);
            $out = $f->format($ts);
            if ($out !== false) {
                return $out;
            }
        }
    }
    static $map = ['d' => 'dd', 'j' => 'd', 'D' => 'EEE', 'l' => 'EEEE', 'm' => 'MM', 'n' => 'M', 'M' => 'MMM', 'F' => 'MMMM', 'Y' => 'y', 'y' => 'yy', 'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h', 'i' => 'mm', 's' => 'ss', 'a' => 'a', 'A' => 'a'];
    $pattern = '';
    foreach (str_split($fmt) as $ch) {
        $pattern .= $map[$ch] ?? (ctype_alpha($ch) ? "'" . $ch . "'" : ($ch === "'" ? "''" : $ch));
    }
    $f = new IntlDateFormatter(icu_locale($lang), IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), null, $pattern);
    $out = $f->format($ts);
    return $out === false ? date($fmt, $ts) : $out;
}

function fmt_date(?string $d, string $fmt = 'j M Y'): string
{
    return $d ? local_date($fmt, strtotime($d)) : '';
}

function fmt_datetime(?string $d): string
{
    return $d ? local_date('D j M Y, g:ia', strtotime($d)) : '';
}

function fmt_time(?string $t): string
{
    return $t ? local_date('g:ia', strtotime($t)) : '';
}

function fmt_money(int $cents, string $cur = 'AUD'): string
{
    return ($cur === 'AUD' ? '$' : $cur . ' ') . number_format($cents / 100, 2);
}

function person_name(?array $p): string
{
    if (!$p) {
        return '';
    }
    $first = !empty($p['preferred_name']) ? $p['preferred_name'] : ($p['first_name'] ?? '');
    return trim($first . ' ' . ($p['last_name'] ?? ''));
}

function age_on(?string $birthdate, ?string $onDate = null): ?int
{
    if (!$birthdate) {
        return null;
    }
    return (new DateTime($birthdate))->diff(new DateTime($onDate ?? 'today'))->y;
}

/** Translatable labels for status values shown in badges (listed so the string extractor finds them). */
function status_labels(): array
{
    static $l = null;
    return $l ??= [
        'accepted' => __('Accepted'), 'active' => __('Active'), 'actioned' => __('Actioned'), 'answered' => __('Answered'), 'approved' => __('Approved'),
        'archived' => __('Archived'), 'assigned' => __('Assigned'), 'attended' => __('Attended'), 'barred' => __('Barred'), 'cancelled' => __('Cancelled'),
        'changes_requested' => __('Changes requested'), 'closed' => __('Closed'), 'completed' => __('Completed'), 'critical' => __('Critical'), 'declined' => __('Declined'),
        'disabled' => __('Disabled'), 'done' => __('Done'), 'draft' => __('Draft'), 'expired' => __('Expired'), 'failed' => __('Failed'), 'high' => __('High'),
        'in_progress' => __('In progress'), 'inactive' => __('Inactive'), 'invited' => __('Invited'), 'low' => __('Low'), 'medium' => __('Medium'), 'member' => __('Member'),
        'no_show' => __('No show'), 'open' => __('Open'), 'paid' => __('Paid'), 'past_due' => __('Past due'), 'pending' => __('Pending'), 'published' => __('Published'),
        'queued' => __('Queued'), 'refunded' => __('Refunded'), 'registered' => __('Registered'), 'regular' => __('Regular'), 'rejected' => __('Rejected'), 'reported' => __('Reported'),
        'requested' => __('Requested'), 'review' => __('In review'), 'reviewing' => __('Reviewing'), 'running' => __('Running'), 'scheduled' => __('Scheduled'),
        'awaiting_payment' => __('Not yet paid'), 'part_paid' => __('Paying in instalments'), 'sending' => __('Sending'), 'sent' => __('Sent'), 'skipped' => __('Skipped'), 'submitted' => __('Submitted'), 'swap_requested' => __('Swap requested'),
        'training' => __('Training'), 'verified' => __('Verified'), 'visitor' => __('Visitor'), 'waitlisted' => __('Waitlisted'), 'willing' => __('Willing'),
        'withdrawn' => __('Withdrawn'), 'paired' => __('Paired'), 'waiting' => __('Waiting'), 'not paired' => __('Not paired'), 'preferred' => __('Preferred'),
        'contain' => __('Contain'), 'assess' => __('Assess'), 'notify' => __('Notify'), 'final' => __('Final'), 'overdue' => __('Overdue'), 'internal' => __('Internal'),
    ];
}

function badge(string $status): string
{
    $map = ['active' => 'green', 'accepted' => 'green', 'approved' => 'green', 'published' => 'green', 'verified' => 'green', 'completed' => 'green', 'paid' => 'green', 'done' => 'green', 'registered' => 'green', 'attended' => 'green', 'member' => 'green', 'sent' => 'green',
        'pending' => 'amber', 'review' => 'amber', 'requested' => 'amber', 'submitted' => 'amber', 'reviewing' => 'amber', 'training' => 'amber', 'in_progress' => 'amber', 'assigned' => 'amber', 'waitlisted' => 'amber', 'swap_requested' => 'amber', 'queued' => 'amber', 'open' => 'amber', 'regular' => 'blue', 'visitor' => 'blue', 'draft' => 'grey', 'paired' => 'green', 'waiting' => 'amber', 'awaiting_payment' => 'amber', 'part_paid' => 'blue', 'not paired' => 'grey',
        'declined' => 'red', 'expired' => 'red', 'overdue' => 'red', 'barred' => 'red', 'failed' => 'red', 'cancelled' => 'grey', 'disabled' => 'grey', 'inactive' => 'grey', 'archived' => 'grey', 'withdrawn' => 'grey', 'closed' => 'grey'];
    $c = $map[$status] ?? 'grey';
    return '<span class="badge badge-' . $c . '">' . e(status_labels()[$status] ?? __(ucfirst(str_replace('_', ' ', $status)))) . '</span>';
}

function options(array $items, mixed $selected = null, ?string $placeholder = null): string
{
    $h = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';
    $sel = is_array($selected) ? array_map('strval', $selected) : [(string)$selected];
    foreach ($items as $v => $label) {
        $h .= '<option value="' . e($v) . '"' . (in_array((string)$v, $sel, true) ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h;
}

function people_options(): array
{
    $out = [];
    foreach (DB::all('SELECT id, first_name, last_name, preferred_name FROM {p}people WHERE archived_at IS NULL ORDER BY last_name, first_name') as $p) {
        $out[$p['id']] = person_name($p);
    }
    return $out;
}

/**
 * People a user may pick from a list. Staff with people.view see everyone; leaders without it
 * only see people who opted into the directory, members of the teams they lead, and applicants to them.
 */
function people_options_scoped(): array
{
    if (can('people.view') || can('volunteers.manage') || can('groups.manage')) {
        return people_options();
    }
    $led = led_group_ids();
    $params = $led;
    $sql = 'SELECT DISTINCT p.id, p.first_name, p.last_name, p.preferred_name FROM {p}people p WHERE p.archived_at IS NULL AND (p.directory_visible = 1'
        . ($led ? ' OR p.id IN (SELECT person_id FROM {p}group_members WHERE group_id IN (' . DB::in($led) . '))' : '')
        . ') ORDER BY p.last_name, p.first_name';
    $out = [];
    foreach (DB::all($sql, $params) as $p) {
        $out[$p['id']] = person_name($p);
    }
    return $out;
}

function au_states(): array
{
    return ['NSW' => 'NSW', 'VIC' => 'VIC', 'QLD' => 'QLD', 'WA' => 'WA', 'SA' => 'SA', 'TAS' => 'TAS', 'ACT' => 'ACT', 'NT' => 'NT'];
}

// ---------------------------------------------------------------- http & csv
function http_request(string $method, string $url, ?string $body = null, array $headers = [], ?string $basicAuth = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($basicAuth !== null) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $resp = json_encode(['error' => ['message' => curl_error($ch)]]);
    }
    curl_close($ch);
    return [$code, $resp];
}

function csv_download(string $filename, array $header, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) {
        // Neutralise spreadsheet formula injection.
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, array_values($r)));
    }
    fclose($out);
    exit;
}

function paginate(string $countSql, array $params, int $perPage = 50): array
{
    $total = (int)DB::val($countSql, $params);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
}
