<?php
declare(strict_types=1);

/**
 * One-way sync to a Mailchimp audience. Only people who consented to contact and to email are
 * added; people who withdraw are unsubscribed. Mailchimp unsubscribes are never overridden
 * (existing members keep their status).
 */
final class Mailchimp
{
    public static function configured(): bool
    {
        return setting('mailchimp_api_key', '') !== '' && setting('mailchimp_list_id', '') !== '' && !Demo::on();
    }

    private static function call(string $method, string $path, ?array $body = null): array
    {
        $key = (string)Crypto::decrypt((string)setting('mailchimp_api_key', ''));
        $dc = substr(strrchr($key, '-') ?: '-us1', 1);
        if (!preg_match('/^[a-z]+\d+$/', $dc)) {
            throw new RuntimeException('Mailchimp API key looks wrong (it should end in -us1, -us21 etc.)');
        }
        [$code, $resp] = http_request($method, "https://$dc.api.mailchimp.com/3.0/" . ltrim($path, '/'), $body === null ? null : json_encode($body), ['Content-Type: application/json'], 'affreso:' . $key);
        return [$code, json_decode((string)$resp, true) ?: []];
    }

    public static function sync(int $limit = 500): array
    {
        if (!self::configured()) {
            return ['skipped' => 'not configured'];
        }
        $list = rawurlencode((string)setting('mailchimp_list_id'));
        $added = $removed = $errors = 0;
        $people = DB::all('SELECT id, first_name, last_name, preferred_name, email, consent_contact, notify_email, archived_at FROM {p}people WHERE email IS NOT NULL AND (updated_at IS NULL OR updated_at > ? OR consent_updated_at > ? OR created_at > ?) ORDER BY id LIMIT ' . max(1, $limit), array_fill(0, 3, (string)setting('mailchimp_last_sync', '2000-01-01 00:00:00')));
        foreach ($people as $p) {
            $hash = md5(mb_strtolower($p['email']));
            $wants = (int)$p['consent_contact'] && (int)$p['notify_email'] && !$p['archived_at'];
            if ($wants) {
                [$code] = self::call('PUT', "lists/$list/members/$hash", ['email_address' => $p['email'], 'status_if_new' => 'subscribed', 'merge_fields' => ['FNAME' => $p['preferred_name'] ?: $p['first_name'], 'LNAME' => $p['last_name']]]);
                $code < 300 ? $added++ : $errors++;
            } else {
                [$code] = self::call('PATCH', "lists/$list/members/$hash", ['status' => 'unsubscribed']);
                $code < 300 ? $removed++ : ($code === 404 ? null : $errors++);
            }
        }
        set_setting('mailchimp_last_sync', date('Y-m-d H:i:s'));
        $out = ['updated' => $added, 'unsubscribed' => $removed, 'errors' => $errors];
        audit('mailchimp.synced', null, null, $out);
        return $out;
    }
}
