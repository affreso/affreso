<?php
declare(strict_types=1);

/**
 * Outgoing messages go through a queue (message_recipients) so large sends never
 * time out on shared hosting. A small batch is sent straight away; cron sends the rest.
 */
final class Notify
{
    /** Queue an email to a person (respects notification preferences unless $essential). */
    public static function emailPerson(int $personId, string $subject, string $html, bool $essential = false, ?int $messageId = null): bool
    {
        $p = DB::row('SELECT email, notify_email FROM {p}people WHERE id = ? AND archived_at IS NULL', [$personId]);
        if (!$p || !$p['email'] || (!$essential && !(int)$p['notify_email'])) {
            return false;
        }
        self::queue('email', $p['email'], $subject, $html, $personId, $messageId);
        return true;
    }

    public static function smsPerson(int $personId, string $body, bool $essential = false, ?int $messageId = null): bool
    {
        $p = DB::row('SELECT mobile, notify_sms FROM {p}people WHERE id = ? AND archived_at IS NULL', [$personId]);
        if (!$p || !$p['mobile'] || !Sms::enabled() || (!$essential && !(int)$p['notify_sms'])) {
            return false;
        }
        self::queue('sms', $p['mobile'], null, $body, $personId, $messageId);
        return true;
    }

    /** Email + SMS + in-app notification in one call, used by workflows. */
    public static function person(int $personId, string $subject, string $html, ?string $sms = null, ?string $link = null): void
    {
        self::emailPerson($personId, $subject, $html);
        if ($sms) {
            self::smsPerson($personId, $sms);
        }
        notify_user(user_id_for_person($personId), $subject, $link);
    }

    public static function emailAddress(string $email, string $subject, string $html): void
    {
        self::queue('email', $email, $subject, $html, null, null);
    }

    public static function users(array $userIds, string $subject, string $html, ?string $link = null): void
    {
        foreach (array_unique($userIds) as $uid) {
            notify_user($uid, $subject, $link);
            $pid = DB::val('SELECT person_id FROM {p}users WHERE id = ?', [$uid]);
            if ($pid) {
                self::emailPerson((int)$pid, $subject, $html);
            } else {
                $email = DB::val('SELECT email FROM {p}users WHERE id = ?', [$uid]);
                if ($email) {
                    self::emailAddress((string)$email, $subject, $html);
                }
            }
        }
    }

    private static function queue(string $channel, string $address, ?string $subject, string $body, ?int $personId, ?int $messageId): void
    {
        DB::insert('message_recipients', ['message_id' => $messageId, 'person_id' => $personId, 'channel' => $channel, 'address' => mb_substr($address, 0, 190), 'subject' => $subject ? mb_substr($subject, 0, 200) : null, 'body' => $body]);
    }

    /** Send up to $limit queued messages. Returns [sent, failed]. */
    public static function processQueue(int $limit = 25): array
    {
        $sent = $failed = 0;
        if (Demo::on()) {
            DB::run("UPDATE {p}message_recipients SET status = 'skipped', error = 'Demo site: not sent' WHERE status = 'queued'");
            DB::run("UPDATE {p}messages SET status = 'sent' WHERE status IN ('queued','sending')");
            return [0, 0];
        }
        $rows = DB::all("SELECT * FROM {p}message_recipients WHERE status = 'queued' AND attempts < 3 ORDER BY id LIMIT " . max(1, $limit));
        foreach ($rows as $r) {
            DB::run('UPDATE {p}message_recipients SET attempts = attempts + 1 WHERE id = ?', [$r['id']]);
            [$ok, $err] = $r['channel'] === 'email'
                ? Mailer::send($r['address'], (string)$r['subject'], (string)$r['body'])
                : Sms::send($r['address'], (string)$r['body']);
            if ($ok) {
                $sent++;
                DB::update('message_recipients', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error' => null], 'id = ?', [$r['id']]);
            } else {
                $failed++;
                $final = ((int)$r['attempts'] + 1) >= 3;
                DB::update('message_recipients', ['status' => $final ? 'failed' : 'queued', 'error' => mb_substr((string)$err, 0, 255)], 'id = ?', [$r['id']]);
            }
        }
        if (class_exists('Push') && $limit >= 20) {
            try {
                Push::flush();
            } catch (Throwable $e) {
                error_log('[Affreso] push: ' . $e->getMessage());
            }
        }
        foreach (DB::col("SELECT DISTINCT message_id FROM {p}message_recipients WHERE message_id IS NOT NULL AND id IN (" . DB::in(array_column($rows, 'id')) . ')', array_column($rows, 'id')) as $mid) {
            $left = (int)DB::val("SELECT COUNT(*) FROM {p}message_recipients WHERE message_id = ? AND status = 'queued'", [$mid]);
            DB::update('messages', ['status' => $left ? 'sending' : 'sent'], 'id = ?', [$mid]);
        }
        return [$sent, $failed];
    }
}
