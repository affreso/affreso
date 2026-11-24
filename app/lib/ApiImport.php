<?php
declare(strict_types=1);

/**
 * Direct import from Elvanto and Planning Center using the church's own API key.
 * Keys are used for this import only and are never stored. Re-running an import is safe:
 * records are matched on the other system's ID, so nobody is duplicated.
 */
final class ApiImport
{
    public const SOURCES = ['elvanto' => 'Elvanto', 'pco' => 'Planning Center'];
    private const MAX_PAGES = 200;

    /** API base addresses. A test harness may override them through config 'import_endpoints'. */
    private static function base(string $source): string
    {
        $default = ['elvanto' => 'https://api.elvanto.com/v1', 'pco' => 'https://api.planningcenteronline.com'];
        return (string)(((array)(config('import_endpoints') ?? []))[$source] ?? '') ?: $default[$source];
    }

    private static function get(string $url, string $auth): array
    {
        [$code, $body] = http_request('GET', $url, null, ['Accept: application/json'], $auth);
        return self::decode($code, (string)$body);
    }

    private static function post(string $url, array $json, string $auth): array
    {
        [$code, $body] = http_request('POST', $url, json_encode($json), ['Content-Type: application/json', 'Accept: application/json'], $auth);
        return self::decode($code, (string)$body);
    }

    private static function decode(int $code, string $body): array
    {
        $j = json_decode($body, true);
        if ($code === 401 || $code === 403) {
            throw new RuntimeException(__('The API key was not accepted. Check it and try again.'));
        }
        if ($code === 429) {
            throw new RuntimeException(__('The other system asked us to slow down. Wait a minute and run the import again — nothing will be duplicated.'));
        }
        if ($code < 200 || $code >= 300 || !is_array($j)) {
            throw new RuntimeException(__('The other system returned an error (HTTP %d).', $code));
        }
        if (isset($j['status']) && $j['status'] !== 'ok') {
            throw new RuntimeException((string)($j['error']['message'] ?? __('The other system returned an error.')));
        }
        return $j;
    }

    // ------------------------------------------------------------------ Elvanto

    /** @return array{people: array, groups: array} */
    public static function fetchElvanto(string $apiKey, bool $withGroups): array
    {
        $auth = $apiKey . ':x';
        $base = self::base('elvanto');
        $people = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $j = self::post($base . '/people/getAll.json', ['page' => $page, 'page_size' => 1000, 'fields' => ['gender', 'birthday', 'home_address', 'home_city', 'home_state', 'home_postcode', 'family', 'mobile', 'preferred_name']], $auth);
            $list = $j['people']['person'] ?? [];
            foreach ($list as $p) {
                if (!empty($p['deceased'])) {
                    continue;
                }
                $fam = $p['family'] ?? [];
                $rel = strtolower((string)($fam['family_relationship'] ?? $p['family_relationship'] ?? ''));
                $people[] = [
                    'ref' => (string)$p['id'], 'first_name' => (string)($p['firstname'] ?? ''), 'last_name' => (string)($p['lastname'] ?? ''),
                    'preferred_name' => (string)($p['preferred_name'] ?? ''), 'email' => (string)($p['email'] ?? ''), 'mobile' => (string)(($p['mobile'] ?? '') ?: ($p['phone'] ?? '')),
                    'birthdate' => (string)($p['birthday'] ?? ''), 'gender' => (string)($p['gender'] ?? ''),
                    'address_line' => (string)($p['home_address'] ?? ''), 'suburb' => (string)($p['home_city'] ?? ''), 'state' => (string)($p['home_state'] ?? ''), 'postcode' => (string)($p['home_postcode'] ?? ''),
                    'status' => !empty($p['archived']) ? 'inactive' : 'member',
                    'household_ref' => (string)($fam['family_id'] ?? $p['family_id'] ?? ''), 'household_name' => '',
                    'child' => str_contains($rel, 'child') || str_contains($rel, 'son') || str_contains($rel, 'daughter'),
                ];
            }
            $total = (int)($j['people']['total'] ?? 0);
            if (!$list || $page * 1000 >= $total) {
                break;
            }
        }
        $groups = [];
        if ($withGroups) {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $j = self::post($base . '/groups/getAll.json', ['page' => $page, 'page_size' => 1000, 'fields' => ['people']], $auth);
                $list = $j['groups']['group'] ?? [];
                foreach ($list as $g) {
                    $members = [];
                    foreach ($g['people']['person'] ?? [] as $m) {
                        $members[(string)$m['id']] = stripos((string)($m['position'] ?? ''), 'leader') !== false ? 'leader' : 'member';
                    }
                    $groups[] = ['ref' => (string)$g['id'], 'name' => (string)$g['name'], 'description' => (string)($g['description'] ?? ''), 'members' => $members];
                }
                if (!$list || $page * 1000 >= (int)($j['groups']['total'] ?? 0)) {
                    break;
                }
            }
        }
        return ['people' => $people, 'groups' => $groups];
    }

    // ------------------------------------------------------------------ Planning Center

    /** Follow JSON:API "next" links, merging data and included records. */
    private static function pcoAll(string $url, string $auth): array
    {
        $data = [];
        $included = [];
        for ($i = 0; $i < self::MAX_PAGES && $url; $i++) {
            $j = self::get($url, $auth);
            $data = array_merge($data, $j['data'] ?? []);
            foreach ($j['included'] ?? [] as $inc) {
                $included[$inc['type'] . ':' . $inc['id']] = $inc;
            }
            $url = $j['links']['next'] ?? null;
            if ($url && str_starts_with($url, 'https://api.planningcenteronline.com') && self::base('pco') !== 'https://api.planningcenteronline.com') {
                $url = self::base('pco') . substr($url, strlen('https://api.planningcenteronline.com'));
            }
        }
        return [$data, $included];
    }

    public static function fetchPco(string $appId, string $secret, bool $withGroups): array
    {
        $auth = $appId . ':' . $secret;
        $base = self::base('pco');
        [$data, $inc] = self::pcoAll($base . '/people/v2/people?per_page=100&include=emails,phone_numbers,addresses,households', $auth);
        $pick = function (array $p, string $rel, string $type) use ($inc): array {
            $rows = [];
            foreach ($p['relationships'][$rel]['data'] ?? [] as $ref) {
                if (isset($inc[$type . ':' . $ref['id']])) {
                    $rows[] = $inc[$type . ':' . $ref['id']]['attributes'] ?? [];
                }
            }
            usort($rows, fn($a, $b) => (int)!empty($b['primary']) <=> (int)!empty($a['primary']));
            return $rows;
        };
        $people = [];
        foreach ($data as $p) {
            $a = $p['attributes'] ?? [];
            $emails = $pick($p, 'emails', 'Email');
            $phones = $pick($p, 'phone_numbers', 'PhoneNumber');
            $mobile = '';
            foreach ($phones as $ph) {
                if (stripos((string)($ph['location'] ?? ''), 'mobile') !== false || stripos((string)($ph['location'] ?? ''), 'cell') !== false) {
                    $mobile = (string)$ph['number'];
                    break;
                }
            }
            $mobile = $mobile ?: (string)($phones[0]['number'] ?? '');
            $addr = $pick($p, 'addresses', 'Address')[0] ?? [];
            $house = $p['relationships']['households']['data'][0]['id'] ?? '';
            $membership = (string)($a['membership'] ?? '');
            $people[] = [
                'ref' => (string)$p['id'], 'first_name' => (string)($a['first_name'] ?? $a['given_name'] ?? ''), 'last_name' => (string)($a['last_name'] ?? ''),
                'preferred_name' => (string)($a['nickname'] ?? ''), 'email' => (string)($emails[0]['address'] ?? ''), 'mobile' => $mobile,
                'birthdate' => (string)($a['birthdate'] ?? ''), 'gender' => match (strtoupper(substr((string)($a['gender'] ?? ''), 0, 1))) { 'M' => 'Male', 'F' => 'Female', default => '' },
                'address_line' => trim(($addr['street_line_1'] ?? $addr['street'] ?? '') . ' ' . ($addr['street_line_2'] ?? '')), 'suburb' => (string)($addr['city'] ?? ''), 'state' => (string)($addr['state'] ?? ''), 'postcode' => (string)($addr['zip'] ?? ''),
                'status' => ($a['status'] ?? 'active') === 'inactive' ? 'inactive' : ($membership !== '' ? PeopleController::mapStatus($membership) : 'regular'),
                'household_ref' => (string)$house, 'household_name' => (string)($inc['Household:' . $house]['attributes']['name'] ?? ''),
                'child' => !empty($a['child']),
            ];
        }
        $groups = [];
        if ($withGroups) {
            [$gdata] = self::pcoAll($base . '/groups/v2/groups?per_page=100', $auth);
            foreach ($gdata as $g) {
                [$mem] = self::pcoAll($base . '/groups/v2/groups/' . rawurlencode((string)$g['id']) . '/memberships?per_page=100', $auth);
                $members = [];
                foreach ($mem as $m) {
                    $pid = $m['relationships']['person']['data']['id'] ?? null;
                    if ($pid) {
                        $members[(string)$pid] = ($m['attributes']['role'] ?? '') === 'leader' ? 'leader' : 'member';
                    }
                }
                $groups[] = ['ref' => (string)$g['id'], 'name' => (string)($g['attributes']['name'] ?? ''), 'description' => strip_tags((string)($g['attributes']['description'] ?? '')), 'members' => $members];
            }
        }
        return ['people' => $people, 'groups' => $groups];
    }

    // ------------------------------------------------------------------ saving

    /** Save fetched records. Returns counts. */
    public static function save(string $source, array $set, bool $updateExisting, bool $consent): array
    {
        $n = ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'households' => 0, 'groups' => 0, 'memberships' => 0];
        $date = function (string $v): ?string {
            $v = trim($v);
            if ($v === '' || str_starts_with($v, '0000')) {
                return null;
            }
            $t = strtotime(str_replace('/', '-', $v));
            return $t ? date('Y-m-d', $t) : null;
        };
        $campus = Campus::fromInput();
        DB::tx(function () use ($source, $set, $updateExisting, $consent, $date, $campus, &$n) {
            $houses = [];
            $surnames = [];
            foreach ($set['people'] as $p) {
                if ($p['household_ref'] !== '' && $p['last_name'] !== '') {
                    $surnames[$p['household_ref']] ??= $p['last_name'];
                }
            }
            $personIds = [];
            foreach ($set['people'] as $p) {
                $first = trim(mb_substr($p['first_name'], 0, 80));
                $last = trim(mb_substr($p['last_name'], 0, 80));
                if ($first === '' || $last === '') {
                    $n['skipped']++;
                    continue;
                }
                $hid = null;
                if ($p['household_ref'] !== '') {
                    $hid = $houses[$p['household_ref']] ??= (function () use ($source, $p, $surnames, &$n) {
                        $id = (int)DB::val('SELECT id FROM {p}households WHERE import_source = ? AND import_ref = ?', [$source, $p['household_ref']]);
                        if ($id) {
                            return $id;
                        }
                        $n['households']++;
                        return DB::insert('households', ['name' => mb_substr($p['household_name'] ?: __('%s family', $surnames[$p['household_ref']] ?? $p['last_name']), 0, 150), 'address_line' => $p['address_line'] ?: null, 'suburb' => $p['suburb'] ?: null, 'state' => $p['state'] ?: null, 'postcode' => $p['postcode'] ?: null, 'import_source' => $source, 'import_ref' => mb_substr($p['household_ref'], 0, 64)]);
                    })();
                }
                $email = mb_strtolower(trim($p['email']));
                $row = [
                    'first_name' => $first, 'last_name' => $last, 'preferred_name' => trim($p['preferred_name']) !== '' && trim($p['preferred_name']) !== $first ? mb_substr(trim($p['preferred_name']), 0, 80) : null,
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, 'mobile' => trim($p['mobile']) ? mb_substr(trim($p['mobile']), 0, 40) : null,
                    'birthdate' => $date($p['birthdate']), 'gender' => $p['gender'] ? mb_substr($p['gender'], 0, 20) : null,
                    'address_line' => $p['address_line'] ? mb_substr($p['address_line'], 0, 200) : null, 'suburb' => $p['suburb'] ?: null, 'state' => $p['state'] ? mb_substr($p['state'], 0, 50) : null, 'postcode' => $p['postcode'] ? mb_substr($p['postcode'], 0, 12) : null,
                    'membership_status' => $p['status'], 'household_id' => $hid, 'household_role' => $p['child'] ? 'child' : 'adult',
                ];
                $existing = DB::row('SELECT id FROM {p}people WHERE import_source = ? AND import_ref = ?', [$source, $p['ref']]);
                if ($existing) {
                    if ($updateExisting) {
                        DB::update('people', $row + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$existing['id']]);
                        $n['updated']++;
                    } else {
                        $n['skipped']++;
                    }
                    $personIds[$p['ref']] = (int)$existing['id'];
                    continue;
                }
                $match = $row['email'] ? DB::row('SELECT id FROM {p}people WHERE email = ? AND first_name = ? AND import_ref IS NULL AND archived_at IS NULL', [$row['email'], $first]) : null;
                if ($match) {
                    DB::update('people', ['import_source' => $source, 'import_ref' => $p['ref']], 'id = ?', [$match['id']]);
                    $personIds[$p['ref']] = (int)$match['id'];
                    $n['linked']++;
                    continue;
                }
                $personIds[$p['ref']] = DB::insert('people', $row + ['import_source' => $source, 'import_ref' => mb_substr($p['ref'], 0, 64), 'campus_id' => $campus,
                    'consent_contact' => $consent ? 1 : 0, 'consent_updated_at' => $consent ? date('Y-m-d H:i:s') : null]);
                $n['created']++;
            }
            foreach ($set['groups'] as $g) {
                if (trim($g['name']) === '') {
                    continue;
                }
                $gid = (int)DB::val('SELECT id FROM {p}groups WHERE import_source = ? AND import_ref = ?', [$source, $g['ref']]);
                if (!$gid) {
                    $gid = DB::insert('groups', ['name' => mb_substr($g['name'], 0, 150), 'group_type' => 'small_group', 'description' => $g['description'] ?: null, 'campus_id' => $campus, 'import_source' => $source, 'import_ref' => mb_substr($g['ref'], 0, 64)]);
                    $n['groups']++;
                }
                foreach ($g['members'] as $ref => $role) {
                    if (isset($personIds[$ref]) || ($personIds[$ref] = (int)DB::val('SELECT id FROM {p}people WHERE import_source = ? AND import_ref = ?', [$source, (string)$ref]))) {
                        $n['memberships'] += DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id, member_role) VALUES (?, ?, ?)', [$gid, $personIds[$ref], $role])->rowCount();
                    }
                }
            }
        });
        return $n;
    }
}
