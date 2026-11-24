<?php
/**
 * Affreso Church Management System
 * Front controller — every request goes through this file.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$route = $_GET['r'] ?? null;
if ($route === null) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $route = preg_replace('#^/index\.php#', '', $path) ?: '/';
}
$route = '/' . trim((string)$route, '/');
$GLOBALS['AFFRESO_ROUTE'] = $route;

try {
    if (empty($GLOBALS['AFFRESO_CONFIG'])) {
        require APP_DIR . '/install.php';
        exit;
    }
    $router = new Router();
    require APP_DIR . '/routes.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $route);
} catch (Throwable $e) {
    error_log('[Affreso] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (config('debug')) {
        http_response_code(500);
        echo '<pre>' . e((string)$e) . '</pre>';
        exit;
    }
    abort(500, 'An unexpected error occurred. The details have been logged for your administrator.');
}
