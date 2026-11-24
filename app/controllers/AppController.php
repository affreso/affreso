<?php
declare(strict_types=1);

/** Installable web app (PWA): manifest, offline page, push subscriptions and the notification feed. */
final class AppController
{
    public function manifest(): void
    {
        $b = base_path();
        header('Content-Type: application/manifest+json');
        echo json_encode([
            'name' => (string)setting('church_name', 'Affreso'),
            'short_name' => mb_strimwidth((string)setting('church_name', 'Affreso'), 0, 12, ''),
            'description' => 'Rosters, events, training and church life',
            'start_url' => $b . '/index.php?r=%2F',
            'scope' => $b . '/',
            'display' => 'standalone',
            'background_color' => '#f7f5f0',
            'theme_color' => '#1d4e57',
            'lang' => current_language(),
            'dir' => is_rtl() ? 'rtl' : 'ltr',
            'icons' => [
                ['src' => $b . '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $b . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $b . '/assets/icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('My rosters'), 'url' => $b . '/index.php?r=%2Fme%2Frosters'],
                ['name' => __('Events'), 'url' => $b . '/index.php?r=%2Fme%2Fevents'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function offline(): void
    {
        view('offline', ['title' => __('Offline')], 'layout_auth');
    }

    public function feed(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $items = DB::all('SELECT id, title, link FROM {p}notifications WHERE user_id = ? AND read_at IS NULL ORDER BY id DESC LIMIT 3', [Auth::id()]);
        echo json_encode(['church' => setting('church_name'), 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function subscribe(): void
    {
        $j = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $endpoint = (string)($j['endpoint'] ?? '');
        if (!preg_match('#^https://[a-z0-9.\-]+(:\d+)?/#i', $endpoint) || strlen($endpoint) > 700) {
            http_response_code(422);
            exit;
        }
        DB::run('DELETE FROM {p}push_subscriptions WHERE endpoint = ?', [$endpoint]);
        DB::insert('push_subscriptions', ['user_id' => Auth::id(), 'endpoint' => $endpoint, 'p256dh' => mb_substr((string)($j['keys']['p256dh'] ?? ''), 0, 200), 'auth_key' => mb_substr((string)($j['keys']['auth'] ?? ''), 0, 100)]);
        audit('push.subscribed', 'user', Auth::id());
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public function unsubscribe(): void
    {
        $j = json_decode((string)file_get_contents('php://input'), true) ?: [];
        DB::run('DELETE FROM {p}push_subscriptions WHERE user_id = ? AND endpoint = ?', [Auth::id(), (string)($j['endpoint'] ?? '')]);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }
}
