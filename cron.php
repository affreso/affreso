<?php
/**
 * Affreso scheduled tasks. Run every 5–15 minutes:
 *   CLI (recommended):  php /path/to/affreso/cron.php
 *   Web (if your host only offers URL cron):  https://your-site/affreso/cron.php?key=YOUR_CRON_KEY
 * The cron key is shown in Administration → Settings → Scheduled tasks.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (empty($GLOBALS['AFFRESO_CONFIG'])) {
    exit("Affreso is not installed.\n");
}
if (PHP_SAPI !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    if ($key === '' || !hash_equals((string)setting('cron_key', ''), $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
if (PHP_SAPI === 'cli' && in_array('--demo-reset', $argv ?? [], true)) {
    if (!Demo::on()) {
        exit("Demo mode is not enabled in config/config.php.\n");
    }
    Demo::reset();
    exit('[' . date('c') . "] Demo data reset.\n");
}
$force = PHP_SAPI === 'cli' && in_array('--daily', $argv ?? [], true);
$log = Jobs::run($force);
echo '[' . date('c') . '] Affreso cron: ' . json_encode($log) . "\n";
