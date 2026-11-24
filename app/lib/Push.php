<?php
declare(strict_types=1);

/**
 * Web Push for the installable app (VAPID, RFC 8292).
 *
 * With setting push_content = 'title' (default) each push carries the notification title, encrypted
 * end-to-end with the device's own keys (RFC 8291, aes128gcm) so the browser vendor's push service
 * cannot read it. With push_content = 'none' pushes are empty wake-ups and the service worker fetches
 * the message from Affreso after the person is signed in.
 */
final class Push
{
    public static function supported(): bool
    {
        return function_exists('openssl_pkey_new');
    }

    /** Create (once) and return the VAPID key pair: [publicKeyBase64Url, privatePem]. */
    public static function keys(): array
    {
        $pub = (string)setting('vapid_public', '');
        $priv = (string)Crypto::decrypt((string)setting('vapid_private', ''));
        if ($pub === '' || $priv === '') {
            $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            if (!$k) {
                throw new RuntimeException('Could not create push keys');
            }
            openssl_pkey_export($k, $priv);
            $d = openssl_pkey_get_details($k);
            $pub = self::b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
            set_setting('vapid_public', $pub);
            set_setting('vapid_private', Crypto::encrypt($priv));
        }
        return [$pub, $priv];
    }

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** Convert an ASN.1 DER ECDSA signature to the raw 64-byte r||s form JWT ES256 needs. */
    public static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7F;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $parts[0] . $parts[1];
    }

    public static function vapidHeader(string $endpoint): string
    {
        [$pub, $priv] = self::keys();
        $u = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $contact = (string)setting('church_email', '') ?: 'admin@example.org';
        $h = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $p = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $contact]));
        openssl_sign("$h.$p", $sig, $priv, OPENSSL_ALGO_SHA256);
        return 'vapid t=' . "$h.$p." . self::b64u(self::derToRaw($sig)) . ', k=' . $pub;
    }

    public static function b64uDecode(string $s): string
    {
        return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** PEM SubjectPublicKeyInfo for an uncompressed P-256 point. */
    private static function pointToPem(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Encrypt a payload for one subscription (RFC 8291 / RFC 8188 aes128gcm, single record).
     * $salt and $ephemeral may be injected for tests; normally random.
     */
    public static function encrypt(string $plaintext, string $p256dhB64u, string $authB64u, ?string $salt = null, $ephemeral = null): ?string
    {
        if (!function_exists('openssl_pkey_derive')) {
            return null;
        }
        $uaPublic = self::b64uDecode($p256dhB64u);
        $authSecret = self::b64uDecode($authB64u);
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) < 16) {
            return null;
        }
        $peer = openssl_pkey_get_public(self::pointToPem($uaPublic));
        $eph = $ephemeral ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$peer || !$eph) {
            return null;
        }
        $d = openssl_pkey_get_details($eph);
        $asPublic = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $shared = openssl_pkey_derive($peer, $eph, 32);
        if ($shared === false) {
            return null;
        }
        $shared = str_pad($shared, 32, "\0", STR_PAD_LEFT);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $salt ??= random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            return null;
        }
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /** What a push says about a notification (kept short; never message bodies). */
    public static function payload(array $n): string
    {
        return (string)json_encode([
            'title' => (string)setting('church_name', 'Affreso'),
            'body' => mb_substr((string)$n['title'], 0, 160),
            'id' => (int)$n['id'],
            'url' => $n['link'] ?: url('/notifications'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Send one push. Returns the HTTP status. */
    public static function send(array $sub, ?array $notification): int
    {
        $headers = ['TTL: 86400', 'Urgency: normal', 'Authorization: ' . self::vapidHeader($sub['endpoint'])];
        $body = '';
        if ($notification && setting('push_content', 'title') !== 'none' && $sub['p256dh'] && $sub['auth_key']) {
            $enc = self::encrypt(self::payload($notification), $sub['p256dh'], $sub['auth_key']);
            if ($enc !== null) {
                $body = $enc;
                $headers[] = 'Content-Encoding: aes128gcm';
                $headers[] = 'Content-Type: application/octet-stream';
            }
        }
        $headers[] = 'Content-Length: ' . strlen($body);
        [$code] = http_request('POST', $sub['endpoint'], $body, $headers);
        return $code;
    }

    /** Wake every device of users with unsent notifications. */
    public static function flush(int $limit = 50): int
    {
        if (Demo::on()) {
            DB::run('UPDATE {p}notifications SET pushed_at = NOW() WHERE pushed_at IS NULL');
            return 0;
        }
        $users = array_map('intval', DB::col('SELECT DISTINCT n.user_id FROM {p}notifications n JOIN {p}push_subscriptions s ON s.user_id = n.user_id WHERE n.pushed_at IS NULL AND n.read_at IS NULL AND n.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT ' . max(1, $limit)));
        $sent = 0;
        foreach ($users as $uid) {
            $latest = DB::row('SELECT id, title, link FROM {p}notifications WHERE user_id = ? AND pushed_at IS NULL AND read_at IS NULL ORDER BY id DESC LIMIT 1', [$uid]);
            $count = (int)DB::val('SELECT COUNT(*) FROM {p}notifications WHERE user_id = ? AND pushed_at IS NULL AND read_at IS NULL', [$uid]);
            if ($latest && $count > 1) {
                $latest['title'] = __('%s (and %d more)', $latest['title'], $count - 1);
            }
            foreach (DB::all('SELECT * FROM {p}push_subscriptions WHERE user_id = ?', [$uid]) as $s) {
                $code = self::send($s, $latest);
                if (in_array($code, [404, 410], true)) {
                    DB::run('DELETE FROM {p}push_subscriptions WHERE id = ?', [$s['id']]);
                } elseif ($code >= 200 && $code < 300) {
                    DB::update('push_subscriptions', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
                    $sent++;
                }
            }
        }
        DB::run('UPDATE {p}notifications SET pushed_at = NOW() WHERE pushed_at IS NULL');
        return $sent;
    }
}
