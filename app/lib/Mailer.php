<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free SMTP client (STARTTLS / SSL / plain, AUTH LOGIN).
 * Falls back to PHP mail() when SMTP is not configured.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $text = null): array
    {
        if (Demo::on()) {
            return [false, 'Demo site: email is switched off'];
        }
        $to = self::clean($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Invalid recipient address'];
        }
        $fromEmail = self::clean((string)setting('mail_from_email', ''));
        $fromName = self::clean((string)setting('mail_from_name', setting('church_name', 'Affreso')));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Sender email address is not configured (Admin → Settings → Email)'];
        }
        $subject = self::clean($subject);
        $text ??= trim(html_entity_decode(strip_tags(preg_replace(['/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '/<br\s*\/?>|<\/p>|<\/h\d>|<\/tr>/i'], ['$2: $1', "\n"], $html)), ENT_QUOTES, 'UTF-8'));
        $boundary = 'af_' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($fromEmail, '@'), 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $replyTo = self::clean((string)setting('mail_reply_to', ''));
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode(self::wrapHtml($html)))
            . "--$boundary--\r\n";

        $host = (string)setting('smtp_host', '');
        if ($host === '') {
            $ok = @mail($to, self::encodeHeader($subject), $body, implode("\r\n", array_filter($headers, fn($h) => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:'))), '-f' . $fromEmail);
            return [$ok, $ok ? null : 'PHP mail() failed — configure SMTP in settings'];
        }
        try {
            self::smtp($host, (int)setting('smtp_port', 587), (string)setting('smtp_security', 'tls'), (string)setting('smtp_user', ''), (string)Crypto::decrypt((string)setting('smtp_pass', '')), $fromEmail, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
            return [true, null];
        } catch (Throwable $e) {
            return [false, substr($e->getMessage(), 0, 250)];
        }
    }

    private static function clean(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $s));
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    public static function wrapHtml(string $inner): string
    {
        $church = e((string)setting('church_name', 'Affreso'));
        return '<!doctype html><html><body style="margin:0;background:#f4f1ea;font-family:Helvetica,Arial,sans-serif;color:#1f2a2e">'
            . '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:10px;overflow:hidden">'
            . '<div style="background:#1d4e57;color:#fff;padding:16px 24px;font-size:18px;font-weight:bold">' . $church . '</div>'
            . '<div style="padding:24px;line-height:1.55;font-size:15px">' . $inner . '</div>'
            . '<div style="padding:12px 24px;font-size:12px;color:#6b7478;border-top:1px solid #eee">Sent by ' . $church . ' via Affreso. You can change your notification preferences in your account.</div>'
            . '</div></body></html>';
    }

    private static function smtp(string $host, int $port, string $security, string $user, string $pass, string $from, string $to, string $data): void
    {
        $remote = ($security === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($fp, 30);
        $read = function () use ($fp): string {
            $resp = '';
            while (($line = fgets($fp, 515)) !== false) {
                $resp .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $resp;
        };
        $cmd = function (string $c, array $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int)substr($r, 0, 3), $expect, true)) {
                throw new RuntimeException('SMTP error after "' . preg_replace('/^(AUTH|PASS).*/', '$1 ***', explode(' ', $c)[0]) . '": ' . trim($r));
            }
            return $r;
        };
        $greet = $read();
        if ((int)substr($greet, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greet));
        }
        $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($security === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $data));
        $data = str_replace("\n", "\r\n", $data);
        $cmd($data . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
    }
}
