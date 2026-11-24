<?php
declare(strict_types=1);

/**
 * Two-way sync with Google Calendar and Microsoft 365 / Outlook.
 *
 * Out: each connected person's rosters and event registrations are created, updated and removed in
 *      their own calendar.
 * In:  busy times from their calendar (next 90 days) are read so leaders see "busy in their calendar"
 *      when rostering. Only start and end times are stored — never titles or details.
 *
 * Each church registers its own OAuth app (Google Cloud / Microsoft Entra) so tokens stay with the
 * church. Tokens are encrypted at rest.
 */
final class CalendarSync
{
    public const PROVIDERS = ['google' => 'Google Calendar', 'microsoft' => 'Microsoft 365 / Outlook'];
    private const WINDOW_DAYS = 90;

    /** Service endpoints. A test harness may override them through config 'calendar_endpoints'. */
    public static function endpoint(string $name): string
    {
        $default = [
            'google_auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'google_token' => 'https://oauth2.googleapis.com/token',
            'google_revoke' => 'https://oauth2.googleapis.com/revoke',
            'google_api' => 'https://www.googleapis.com/calendar/v3',
            'microsoft_auth' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
            'microsoft_token' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'microsoft_api' => 'https://graph.microsoft.com/v1.0',
        ];
        $over = (array)(config('calendar_endpoints') ?? []);
        $url = $over[$name] ?? $default[$name];
        return str_replace('{tenant}', rawurlencode((string)setting('ms_tenant', '') ?: 'common'), $url);
    }

    public static function configured(string $provider): bool
    {
        return $provider === 'google'
            ? setting('google_client_id', '') !== '' && setting('google_client_secret', '') !== ''
            : setting('ms_client_id', '') !== '' && setting('ms_client_secret', '') !== '';
    }

    public static function anyConfigured(): bool
    {
        return self::configured('google') || self::configured('microsoft');
    }

    public static function redirectUri(): string
    {
        return url('/account/calendar/callback', [], true);
    }

    private static function client(string $provider): array
    {
        return $provider === 'google'
            ? [(string)setting('google_client_id'), (string)Crypto::decrypt((string)setting('google_client_secret'))]
            : [(string)setting('ms_client_id'), (string)Crypto::decrypt((string)setting('ms_client_secret'))];
    }

    // ------------------------------------------------------------------ OAuth

    /** Start the OAuth flow (authorisation code with PKCE). Returns the URL to send the person to. */
    public static function authUrl(string $provider): string
    {
        [$clientId] = self::client($provider);
        $state = bin2hex(random_bytes(16));
        $verifier = Push::b64u(random_bytes(48));
        $_SESSION['cal_oauth'] = ['provider' => $provider, 'state' => $state, 'verifier' => $verifier, 'at' => time()];
        $q = [
            'client_id' => $clientId, 'redirect_uri' => self::redirectUri(), 'response_type' => 'code', 'state' => $state,
            'code_challenge' => Push::b64u(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256',
        ];
        if ($provider === 'google') {
            $q += ['scope' => 'openid email https://www.googleapis.com/auth/calendar.events', 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'];
        } else {
            $q += ['scope' => 'openid email offline_access https://graph.microsoft.com/Calendars.ReadWrite', 'response_mode' => 'query', 'prompt' => 'select_account'];
        }
        return self::endpoint($provider . '_auth') . '?' . http_build_query($q);
    }

    /** Finish the OAuth flow and store the account. Returns [ok, message]. */
    public static function callback(int $userId, array $query): array
    {
        $flow = $_SESSION['cal_oauth'] ?? null;
        unset($_SESSION['cal_oauth']);
        if (!$flow || $flow['at'] < time() - 900 || !hash_equals($flow['state'], (string)($query['state'] ?? ''))) {
            return [false, __('The calendar connection expired or did not match. Please try again.')];
        }
        if (!empty($query['error'])) {
            return [false, __('The calendar connection was cancelled.')];
        }
        $provider = $flow['provider'];
        [$id, $secret] = self::client($provider);
        $tok = self::tokenRequest($provider, ['grant_type' => 'authorization_code', 'code' => (string)($query['code'] ?? ''), 'redirect_uri' => self::redirectUri(), 'client_id' => $id, 'client_secret' => $secret, 'code_verifier' => $flow['verifier']]);
        if (empty($tok['access_token'])) {
            return [false, __('The calendar service did not accept the connection: %s', (string)($tok['error_description'] ?? $tok['error'] ?? __('unknown error')))];
        }
        $email = null;
        if (!empty($tok['id_token']) && count($parts = explode('.', (string)$tok['id_token'])) === 3) {
            $claims = json_decode((string)Push::b64uDecode($parts[1]), true) ?: [];
            $email = $claims['email'] ?? $claims['preferred_username'] ?? null;
        }
        $row = [
            'user_id' => $userId, 'provider' => $provider, 'account_email' => $email ? mb_substr((string)$email, 0, 190) : null,
            'access_token' => Crypto::encrypt((string)$tok['access_token']),
            'refresh_token' => !empty($tok['refresh_token']) ? Crypto::encrypt((string)$tok['refresh_token']) : null,
            'expires_at' => date('Y-m-d H:i:s', time() + (int)($tok['expires_in'] ?? 3600) - 60), 'last_error' => null,
        ];
        $existing = DB::row('SELECT * FROM {p}calendar_accounts WHERE user_id = ? AND provider = ?', [$userId, $provider]);
        if ($existing) {
            if ($row['refresh_token'] === null) {
                unset($row['refresh_token']);
            }
            DB::update('calendar_accounts', $row, 'id = ?', [$existing['id']]);
            $aid = (int)$existing['id'];
        } else {
            $aid = DB::insert('calendar_accounts', $row);
        }
        audit('calendar.connected', 'user', $userId, ['provider' => $provider]);
        return [true, __('Connected to %s.', self::PROVIDERS[$provider]), $aid];
    }

    private static function tokenRequest(string $provider, array $form): array
    {
        [$code, $body] = http_request('POST', self::endpoint($provider . '_token'), http_build_query($form), ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);
        $j = json_decode((string)$body, true);
        return is_array($j) ? $j + ['_status' => $code] : ['error' => 'HTTP ' . $code];
    }

    /** A valid access token, refreshing it when needed. */
    private static function accessToken(array &$acct): string
    {
        if ($acct['expires_at'] && strtotime($acct['expires_at']) > time() + 30) {
            return (string)Crypto::decrypt((string)$acct['access_token']);
        }
        $refresh = (string)Crypto::decrypt((string)$acct['refresh_token']);
        if ($refresh === '') {
            throw new RuntimeException(__('Calendar access has expired. Please reconnect.'));
        }
        [$id, $secret] = self::client($acct['provider']);
        $tok = self::tokenRequest($acct['provider'], ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $id, 'client_secret' => $secret]);
        if (empty($tok['access_token'])) {
            if (in_array($tok['error'] ?? '', ['invalid_grant', 'unauthorized_client'], true)) {
                throw new RuntimeException(__('Calendar access was withdrawn. Please reconnect.'));
            }
            throw new RuntimeException('Token refresh failed: ' . ($tok['error'] ?? 'HTTP ' . ($tok['_status'] ?? '?')));
        }
        $upd = ['access_token' => Crypto::encrypt((string)$tok['access_token']), 'expires_at' => date('Y-m-d H:i:s', time() + (int)($tok['expires_in'] ?? 3600) - 60)];
        if (!empty($tok['refresh_token'])) {
            $upd['refresh_token'] = Crypto::encrypt((string)$tok['refresh_token']);
        }
        DB::update('calendar_accounts', $upd, 'id = ?', [$acct['id']]);
        $acct = array_merge($acct, $upd);
        return (string)$tok['access_token'];
    }

    private static function api(array &$acct, string $method, string $path, ?array $json = null, array $extraHeaders = []): array
    {
        $token = self::accessToken($acct);
        $url = str_starts_with($path, 'http') ? $path : self::endpoint($acct['provider'] . '_api') . $path;
        $headers = array_merge(['Authorization: Bearer ' . $token, 'Accept: application/json'], $extraHeaders);
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        [$code, $body] = http_request($method, $url, $json !== null ? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $headers);
        return [$code, json_decode((string)$body, true) ?: []];
    }

    // ------------------------------------------------------------------ what to push

    /** Everything that should be in this person's calendar, keyed (e.g. "roster:12"). */
    public static function desiredItems(int $personId): array
    {
        $items = [];
        $from = date('Y-m-d', strtotime('-1 day'));
        $to = date('Y-m-d', strtotime('+' . self::WINDOW_DAYS . ' days'));
        $rows = DB::all("SELECT a.id, a.status, r.title, r.service_date, r.start_time, r.end_time, r.notes, po.name AS position, g.name AS team FROM {p}roster_assignments a JOIN {p}rosters r ON r.id = a.roster_id JOIN {p}positions po ON po.id = a.position_id JOIN {p}groups g ON g.id = r.group_id WHERE a.person_id = ? AND r.status = 'published' AND a.status IN ('pending','accepted','swap_requested') AND r.service_date BETWEEN ? AND ?", [$personId, $from, $to]);
        foreach ($rows as $r) {
            $items['roster:' . $r['id']] = [
                'title' => __('Serving: %s', $r['position']) . ' — ' . $r['title'],
                'details' => __('Team: %s', $r['team']) . ($r['status'] === 'pending' ? "\n" . __('Please accept or decline in Affreso.') : '') . "\n" . url('/me/rosters', [], true),
                'location' => null, 'date' => $r['service_date'],
                'start' => $r['start_time'] ? $r['service_date'] . ' ' . $r['start_time'] : null,
                'end' => $r['start_time'] ? $r['service_date'] . ' ' . ($r['end_time'] ?: date('H:i:s', strtotime($r['start_time']) + 5400)) : null,
            ];
        }
        $rows = DB::all("SELECT e.id, e.title, e.location, e.start_at, e.end_at, e.joining_instructions FROM {p}event_registrations x JOIN {p}events e ON e.id = x.event_id WHERE x.person_id = ? AND x.status = 'registered' AND e.status = 'scheduled' AND e.end_at BETWEEN ? AND ?", [$personId, $from, $to . ' 23:59:59']);
        foreach ($rows as $r) {
            $items['event:' . $r['id']] = ['title' => $r['title'], 'details' => trim((string)$r['joining_instructions']), 'location' => $r['location'], 'date' => substr($r['start_at'], 0, 10), 'start' => $r['start_at'], 'end' => $r['end_at']];
        }
        return $items;
    }

    private static function iso(string $local): string
    {
        return (new DateTimeImmutable($local, new DateTimeZone(date_default_timezone_get())))->format(DATE_RFC3339);
    }

    private static function utc(string $local): string
    {
        return (new DateTimeImmutable($local, new DateTimeZone(date_default_timezone_get())))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }

    /** Provider-specific event body. */
    public static function body(string $provider, array $it, string $key): array
    {
        $church = (string)setting('church_name', 'Affreso');
        if ($provider === 'google') {
            $b = ['summary' => $it['title'], 'description' => trim($it['details'] . "\n\n" . __('Added by %s (Affreso).', $church)), 'extendedProperties' => ['private' => ['affreso' => $key]]];
            if ($it['location']) {
                $b['location'] = $it['location'];
            }
            if ($it['start']) {
                $b['start'] = ['dateTime' => self::iso($it['start']), 'timeZone' => date_default_timezone_get()];
                $b['end'] = ['dateTime' => self::iso($it['end']), 'timeZone' => date_default_timezone_get()];
            } else {
                $b['start'] = ['date' => $it['date']];
                $b['end'] = ['date' => date('Y-m-d', strtotime($it['date'] . ' +1 day'))];
                $b['transparency'] = 'transparent';
            }
            return $b;
        }
        $b = ['subject' => $it['title'], 'body' => ['contentType' => 'text', 'content' => trim($it['details'] . "\n\n" . __('Added by %s (Affreso).', $church))]];
        if ($it['location']) {
            $b['location'] = ['displayName' => $it['location']];
        }
        if ($it['start']) {
            $b['start'] = ['dateTime' => self::utc($it['start']), 'timeZone' => 'UTC'];
            $b['end'] = ['dateTime' => self::utc($it['end']), 'timeZone' => 'UTC'];
        } else {
            $b['isAllDay'] = true;
            $b['showAs'] = 'free';
            $b['start'] = ['dateTime' => $it['date'] . 'T00:00:00', 'timeZone' => 'UTC'];
            $b['end'] = ['dateTime' => date('Y-m-d', strtotime($it['date'] . ' +1 day')) . 'T00:00:00', 'timeZone' => 'UTC'];
        }
        return $b;
    }

    private static function eventPath(array $acct, ?string $remoteId = null): string
    {
        if ($acct['provider'] === 'google') {
            return '/calendars/' . rawurlencode($acct['calendar_id'] ?: 'primary') . '/events' . ($remoteId !== null ? '/' . rawurlencode($remoteId) : '');
        }
        return '/me/events' . ($remoteId !== null ? '/' . rawurlencode($remoteId) : '');
    }

    // ------------------------------------------------------------------ sync

    /** Sync one account both ways. Returns counts. */
    public static function sync(int $accountId): array
    {
        $acct = DB::row('SELECT a.*, u.person_id FROM {p}calendar_accounts a JOIN {p}users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$acct) {
            return [];
        }
        $out = ['created' => 0, 'updated' => 0, 'removed' => 0, 'busy' => 0];
        try {
            if ($acct['push_enabled'] && $acct['person_id']) {
                $out = array_merge($out, self::push($acct, self::desiredItems((int)$acct['person_id'])));
            }
            if ($acct['busy_enabled'] && $acct['person_id']) {
                $out['busy'] = self::pullBusy($acct);
            } else {
                DB::run('DELETE FROM {p}calendar_busy WHERE account_id = ?', [$acct['id']]);
            }
            DB::update('calendar_accounts', ['last_sync_at' => date('Y-m-d H:i:s'), 'last_error' => null], 'id = ?', [$acct['id']]);
        } catch (Throwable $e) {
            DB::update('calendar_accounts', ['last_sync_at' => date('Y-m-d H:i:s'), 'last_error' => mb_substr($e->getMessage(), 0, 255)], 'id = ?', [$acct['id']]);
            $out['error'] = $e->getMessage();
        }
        return $out;
    }

    private static function push(array &$acct, array $desired): array
    {
        $n = ['created' => 0, 'updated' => 0, 'removed' => 0];
        $links = [];
        foreach (DB::all('SELECT * FROM {p}calendar_links WHERE account_id = ?', [$acct['id']]) as $l) {
            $links[$l['item_key']] = $l;
        }
        foreach ($desired as $key => $it) {
            $body = self::body($acct['provider'], $it, $key);
            $hash = sha1(json_encode($body));
            $link = $links[$key] ?? null;
            if ($link && $link['content_hash'] === $hash) {
                continue;
            }
            if ($link) {
                [$code] = self::api($acct, $acct['provider'] === 'google' ? 'PUT' : 'PATCH', self::eventPath($acct, $link['remote_id']), $body);
                if ($code >= 200 && $code < 300) {
                    DB::update('calendar_links', ['content_hash' => $hash, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$link['id']]);
                    $n['updated']++;
                    continue;
                }
                if (!in_array($code, [404, 410], true)) {
                    throw new RuntimeException('Calendar update failed (HTTP ' . $code . ')');
                }
                // The person deleted it from their calendar: create it again below.
                DB::run('DELETE FROM {p}calendar_links WHERE id = ?', [$link['id']]);
            }
            [$code, $res] = self::api($acct, 'POST', self::eventPath($acct), $body);
            if ($code < 200 || $code >= 300 || empty($res['id'])) {
                throw new RuntimeException('Calendar create failed (HTTP ' . $code . ')');
            }
            DB::insert('calendar_links', ['account_id' => $acct['id'], 'item_key' => $key, 'remote_id' => (string)$res['id'], 'content_hash' => $hash]);
            $n['created']++;
        }
        foreach ($links as $key => $l) {
            if (isset($desired[$key])) {
                continue;
            }
            [$code] = self::api($acct, 'DELETE', self::eventPath($acct, $l['remote_id']));
            if (($code >= 200 && $code < 300) || in_array($code, [404, 410], true)) {
                DB::run('DELETE FROM {p}calendar_links WHERE id = ?', [$l['id']]);
                $n['removed']++;
            }
        }
        return $n;
    }

    /** Read busy blocks (times only) for the next 90 days, ignoring events Affreso itself added. */
    private static function pullBusy(array &$acct): int
    {
        $ours = array_flip(DB::col('SELECT remote_id FROM {p}calendar_links WHERE account_id = ?', [$acct['id']]));
        $from = new DateTimeImmutable('today');
        $to = $from->modify('+' . self::WINDOW_DAYS . ' days');
        $blocks = [];
        $tz = new DateTimeZone(date_default_timezone_get());
        $local = fn(string $s) => (new DateTimeImmutable($s))->setTimezone($tz)->format('Y-m-d H:i:s');
        if ($acct['provider'] === 'google') {
            $page = null;
            for ($i = 0; $i < 10; $i++) {
                $q = ['timeMin' => $from->format(DATE_RFC3339), 'timeMax' => $to->format(DATE_RFC3339), 'singleEvents' => 'true', 'maxResults' => 250, 'fields' => 'items(id,start,end,transparency,status),nextPageToken'];
                if ($page) {
                    $q['pageToken'] = $page;
                }
                [$code, $res] = self::api($acct, 'GET', self::eventPath($acct) . '?' . http_build_query($q));
                if ($code !== 200) {
                    throw new RuntimeException('Could not read calendar (HTTP ' . $code . ')');
                }
                foreach ($res['items'] ?? [] as $ev) {
                    if (isset($ours[$ev['id'] ?? '']) || ($ev['status'] ?? '') === 'cancelled' || ($ev['transparency'] ?? 'opaque') === 'transparent') {
                        continue;
                    }
                    if (isset($ev['start']['dateTime'])) {
                        $blocks[] = [$local($ev['start']['dateTime']), $local($ev['end']['dateTime'])];
                    } elseif (isset($ev['start']['date'])) {
                        $blocks[] = [$ev['start']['date'] . ' 00:00:00', $ev['end']['date'] . ' 00:00:00'];
                    }
                }
                if (!($page = $res['nextPageToken'] ?? null)) {
                    break;
                }
            }
        } else {
            $url = self::endpoint('microsoft_api') . '/me/calendarView?' . http_build_query(['startDateTime' => $from->format(DATE_RFC3339), 'endDateTime' => $to->format(DATE_RFC3339), '$select' => 'id,start,end,showAs,isCancelled,isAllDay', '$top' => 200]);
            for ($i = 0; $i < 10 && $url; $i++) {
                [$code, $res] = self::api($acct, 'GET', $url, null, ['Prefer: outlook.timezone="UTC"']);
                if ($code !== 200) {
                    throw new RuntimeException('Could not read calendar (HTTP ' . $code . ')');
                }
                foreach ($res['value'] ?? [] as $ev) {
                    if (isset($ours[$ev['id'] ?? '']) || !empty($ev['isCancelled']) || in_array($ev['showAs'] ?? 'busy', ['free', 'workingElsewhere'], true)) {
                        continue;
                    }
                    if (!empty($ev['isAllDay'])) {
                        $blocks[] = [substr($ev['start']['dateTime'], 0, 10) . ' 00:00:00', substr($ev['end']['dateTime'], 0, 10) . ' 00:00:00'];
                    } else {
                        $blocks[] = [$local($ev['start']['dateTime'] . 'Z'), $local($ev['end']['dateTime'] . 'Z')];
                    }
                }
                $url = $res['@odata.nextLink'] ?? null;
            }
        }
        DB::run('DELETE FROM {p}calendar_busy WHERE account_id = ?', [$acct['id']]);
        foreach ($blocks as [$s, $e]) {
            DB::insert('calendar_busy', ['account_id' => $acct['id'], 'person_id' => $acct['person_id'], 'start_at' => $s, 'end_at' => $e]);
        }
        return count($blocks);
    }

    /** Remove Affreso's events from the calendar (optional) and forget the account. */
    public static function disconnect(int $accountId, bool $removeEvents): void
    {
        $acct = DB::row('SELECT * FROM {p}calendar_accounts WHERE id = ?', [$accountId]);
        if (!$acct) {
            return;
        }
        try {
            if ($removeEvents) {
                foreach (DB::all('SELECT * FROM {p}calendar_links WHERE account_id = ?', [$acct['id']]) as $l) {
                    self::api($acct, 'DELETE', self::eventPath($acct, $l['remote_id']));
                }
            }
            if ($acct['provider'] === 'google' && ($rt = (string)Crypto::decrypt((string)$acct['refresh_token'])) !== '') {
                http_request('POST', self::endpoint('google_revoke'), http_build_query(['token' => $rt]), ['Content-Type: application/x-www-form-urlencoded']);
            }
        } catch (Throwable $e) {
            // Disconnect locally even if the provider can't be reached.
        }
        DB::run('DELETE FROM {p}calendar_links WHERE account_id = ?', [$acct['id']]);
        DB::run('DELETE FROM {p}calendar_busy WHERE account_id = ?', [$acct['id']]);
        DB::run('DELETE FROM {p}calendar_accounts WHERE id = ?', [$acct['id']]);
        audit('calendar.disconnected', 'user', (int)$acct['user_id'], ['provider' => $acct['provider']]);
    }

    /** Cron: sync accounts not synced for 30 minutes (a few per run). */
    public static function syncDue(int $limit = 15): int
    {
        if (Demo::on()) {
            return 0;
        }
        $n = 0;
        foreach (DB::col('SELECT id FROM {p}calendar_accounts WHERE last_sync_at IS NULL OR last_sync_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY last_sync_at LIMIT ' . max(1, $limit)) as $id) {
            self::sync((int)$id);
            $n++;
        }
        return $n;
    }

    /**
     * Is the person busy in their own calendar at this time? Returns a short reason or null.
     * With no start time, only all-day (or very long) commitments count.
     */
    public static function busyReason(int $personId, string $date, ?string $start, ?string $end): ?string
    {
        if ($start) {
            $s = $date . ' ' . $start;
            $e = $date . ' ' . ($end ?: date('H:i:s', strtotime($start) + 5400));
            $hit = DB::row('SELECT start_at, end_at FROM {p}calendar_busy WHERE person_id = ? AND start_at < ? AND end_at > ? LIMIT 1', [$personId, $e, $s]);
        } else {
            $hit = DB::row('SELECT start_at, end_at FROM {p}calendar_busy WHERE person_id = ? AND start_at <= ? AND end_at >= ? AND TIMESTAMPDIFF(HOUR, start_at, end_at) >= 20 LIMIT 1', [$personId, $date . ' 12:00:00', $date . ' 12:00:00']);
        }
        if (!$hit) {
            return null;
        }
        $allDay = substr($hit['start_at'], 11) === '00:00:00' && substr($hit['end_at'], 11) === '00:00:00';
        return $allDay ? __('Busy in their calendar all day') : __('Busy in their calendar %s–%s', fmt_time(substr($hit['start_at'], 11)), fmt_time(substr($hit['end_at'], 11)));
    }
}
