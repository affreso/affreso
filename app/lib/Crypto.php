<?php
declare(strict_types=1);

/**
 * Field-level encryption for highly sensitive data (pastoral notes, safeguarding
 * reference numbers, MFA secrets, integration credentials).
 *
 * Uses libsodium (XSalsa20-Poly1305) when the sodium extension is available ("v1:" values),
 * otherwise OpenSSL AES-256-GCM ("v2:" values). Both use the same 32-byte key from
 * config/config.php — back it up; without it the data cannot be read.
 */
final class Crypto
{
    private const KEY_BYTES = 32;
    private static string $key = '';

    public static function hasSodium(): bool
    {
        return function_exists('sodium_crypto_secretbox') && !defined('AFFRESO_FORCE_OPENSSL');
    }

    public static function available(): bool
    {
        return self::hasSodium() || (function_exists('openssl_encrypt') && in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true));
    }

    public static function init(string $b64Key): void
    {
        $k = base64_decode($b64Key, true);
        if ($k === false || strlen($k) !== self::KEY_BYTES) {
            throw new RuntimeException('Invalid app_key in config');
        }
        self::$key = $k;
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        if (self::hasSodium()) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::$key));
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::$key, OPENSSL_RAW_DATA, $iv, $tag, 'affreso', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed');
        }
        return 'v2:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $enc): ?string
    {
        if ($enc === null || $enc === '') {
            return $enc;
        }
        if (str_starts_with($enc, 'v2:')) {
            $raw = base64_decode(substr($enc, 3), true);
            if ($raw === false || strlen($raw) < 29) {
                return null;
            }
            $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::$key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'affreso');
            return $out === false ? null : $out;
        }
        if (!str_starts_with($enc, 'v1:')) {
            return $enc;
        }
        if (!function_exists('sodium_crypto_secretbox_open')) {
            error_log('[Affreso] Data was encrypted with sodium but the sodium extension is no longer available.');
            return null;
        }
        $raw = base64_decode(substr($enc, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::$key);
        return $out === false ? null : $out;
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
