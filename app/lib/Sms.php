<?php
declare(strict_types=1);

/** SMS via ClickSend (Australian) or Twilio. Credentials are stored encrypted in settings. */
final class Sms
{
    public static function enabled(): bool
    {
        return in_array(setting('sms_provider', 'none'), ['clicksend', 'twilio'], true);
    }

    /** Normalise Australian numbers to E.164 (+614xxxxxxxx). */
    public static function normalise(string $n): ?string
    {
        $n = preg_replace('/[^\d+]/', '', $n);
        if ($n === '') {
            return null;
        }
        if (str_starts_with($n, '+')) {
            return strlen($n) >= 9 ? $n : null;
        }
        $cc = (string)setting('sms_default_country_code', '61');
        if (str_starts_with($n, '0')) {
            return '+' . $cc . substr($n, 1);
        }
        if (str_starts_with($n, $cc)) {
            return '+' . $n;
        }
        return '+' . $cc . $n;
    }

    public static function send(string $to, string $body): array
    {
        if (Demo::on()) {
            return [false, 'Demo site: SMS is switched off'];
        }
        $to = self::normalise($to);
        if (!$to) {
            return [false, 'Invalid mobile number'];
        }
        $provider = setting('sms_provider', 'none');
        $user = (string)setting('sms_username', '');
        $key = (string)Crypto::decrypt((string)setting('sms_api_key', ''));
        $from = (string)setting('sms_from', '');
        if ($provider === 'clicksend') {
            $msg = ['source' => 'affreso', 'body' => $body, 'to' => $to];
            if ($from !== '') {
                $msg['from'] = $from;
            }
            [$code, $resp] = http_request('POST', 'https://rest.clicksend.com/v3/sms/send', json_encode(['messages' => [$msg]]), ['Content-Type: application/json'], $user . ':' . $key);
            $j = json_decode((string)$resp, true);
            $ok = $code === 200 && (($j['data']['messages'][0]['status'] ?? '') === 'SUCCESS');
            return [$ok, $ok ? null : ('ClickSend: ' . ($j['data']['messages'][0]['status'] ?? $j['response_msg'] ?? "HTTP $code"))];
        }
        if ($provider === 'twilio') {
            $fields = ['To' => $to, 'Body' => $body];
            if (str_starts_with($from, 'MG')) {
                $fields['MessagingServiceSid'] = $from;
            } else {
                $fields['From'] = $from;
            }
            [$code, $resp] = http_request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($user) . '/Messages.json', http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded'], $user . ':' . $key);
            $j = json_decode((string)$resp, true);
            $ok = $code >= 200 && $code < 300;
            return [$ok, $ok ? null : ('Twilio: ' . ($j['message'] ?? "HTTP $code"))];
        }
        return [false, 'SMS is not configured'];
    }
}
