<?php
/**
 * Affreso Church Management System — bootstrap.
 * Loads configuration, libraries, session and security headers.
 */
declare(strict_types=1);

define('AFFRESO_VERSION', '4.3.0');
define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/app');
define('STORAGE_DIR', BASE_DIR . '/storage');
define('CONFIG_FILE', BASE_DIR . '/config/config.php');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Affreso requires PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . '.');
}

foreach (glob(APP_DIR . '/lib/*.php') as $f) {
    require_once $f;
}
require_once APP_DIR . '/helpers.php';
require_once APP_DIR . '/permissions.php';

spl_autoload_register(function (string $class): void {
    $file = APP_DIR . '/controllers/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
    $svc = APP_DIR . '/services/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($svc)) {
        require_once $svc;
    }
});

$GLOBALS['AFFRESO_CONFIG'] = is_file(CONFIG_FILE) ? (require CONFIG_FILE) : null;

$debug = (bool)($GLOBALS['AFFRESO_CONFIG']['debug'] ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_DIR . '/logs/php-errors.log');

date_default_timezone_set($GLOBALS['AFFRESO_CONFIG']['timezone'] ?? 'Australia/Sydney');

if ($GLOBALS['AFFRESO_CONFIG']) {
    try {
        DB::connect($GLOBALS['AFFRESO_CONFIG']['db']);
        Crypto::init($GLOBALS['AFFRESO_CONFIG']['app_key']);
        if (Migrator::pending()) {
            Migrator::run();
        }
    } catch (Throwable $e) {
        error_log('[Affreso] Startup failed: ' . $e->getMessage());
        http_response_code(503);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Affreso could not start: ' . $e->getMessage() . "\n");
            exit(1);
        }
        echo '<!doctype html><meta charset="utf-8"><title>Temporarily unavailable</title><div style="font-family:sans-serif;max-width:560px;margin:60px auto"><h1>Temporarily unavailable</h1><p>The system cannot reach its database right now. Please try again in a few minutes. If this continues, contact your church administrator.</p></div>';
        exit;
    }
}

if (PHP_SAPI !== 'cli') {
    $secure = is_https();
    session_name('AFFRESOSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-src https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com; form-action 'self' https://checkout.stripe.com; base-uri 'self'; frame-ancestors 'self'");
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
