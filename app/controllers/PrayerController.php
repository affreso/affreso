<?php
declare(strict_types=1);

/**
 * Prayer requests with three privacy levels:
 *  church  — shown on the members' prayer wall
 *  team    — prayer team (prayer.team) and pastors
 *  pastors — pastoral staff only (pastoral.view)
 * Requests are encrypted at rest.
 */
final class PrayerController
{
    public static function levels(): array
    {
        return ['church' => __('Church family (members\' prayer wall)'), 'team' => __('Prayer team only'), 'pastors' => __('Pastors only (confidential)')];
    }

    /** SQL condition for requests the current user may see. */
    private function visibleWhere(): array
    {
        $levels = ["'church'"];
        if (can('prayer.team') || can('pastoral.view')) {
            $levels[] = "'team'";
        }
        if (can('pastoral.view')) {
            $levels[] = "'pastors'";
        }
        return ['(r.visibility IN (' . implode(',', $levels) . ') OR r.created_by = ? OR (r.person_id IS NOT NULL AND r.person_id = ?))', [Auth::id(), person_id() ?? 0]];
    }

    public function index(): void
    {
        [$w, $p] = $this->visibleWhere();
        $status = input('status') === 'answered' ? 'answered' : 'open';
        $rows = DB::all("SELECT r.*, pe.first_name, pe.last_name, pe.preferred_name, (SELECT 1 FROM {p}prayer_log l WHERE l.request_id = r.id AND l.user_id = ? AND l.prayed_on = CURDATE()) AS prayed_today FROM {p}prayer_requests r LEFT JOIN {p}people pe ON pe.id = r.person_id WHERE $w AND r.status = ? ORDER BY r.created_at DESC LIMIT 200", array_merge([Auth::id()], $p, [$status]));
        foreach ($rows as &$r) {
            $r['request'] = (string)Crypto::decrypt($r['request_enc']);
            $r['answer'] = (string)Crypto::decrypt($r['answer_enc']);
            $r['mine'] = (int)$r['created_by'] === Auth::id() || ($r['person_id'] && (int)$r['person_id'] === person_id());
        }
        unset($r);
        view('prayer/index', ['title' => __('Prayer'), 'rows' => $rows, 'status' => $status]);
    }

    public function store(): void
    {
        $text = trim((string)input('request', ''));
        if ($text === '') {
            back('/prayer');
        }
        $vis = isset(self::levels()[input('visibility')]) ? input('visibility') : 'team';
        $me = person_id() ? DB::row('SELECT * FROM {p}people WHERE id = ?', [person_id()]) : null;
        $id = DB::insert('prayer_requests', ['person_id' => input_bool('anonymous') ? null : person_id(), 'name' => input_bool('anonymous') ? __('Anonymous') : person_name($me), 'request_enc' => Crypto::encrypt(mb_substr($text, 0, 4000)), 'visibility' => $vis, 'created_by' => Auth::id()]);
        self::alertTeam($id, $vis);
        audit('prayer.submitted', 'prayer_request', $id);
        flash('success', __('Thank you. Your request has been shared with %s.', mb_strtolower(self::levels()[$vis])));
        redirect('/prayer');
    }

    public static function alertTeam(int $id, string $vis): void
    {
        $uids = $vis === 'pastors' ? users_with_permission('pastoral.view') : array_merge(users_with_permission('prayer.team'), users_with_permission('pastoral.view'));
        foreach (array_unique($uids) as $uid) {
            notify_user($uid, __('New prayer request'), url('/prayer'));
        }
    }

    private function find(int|string $id): array
    {
        [$w, $p] = $this->visibleWhere();
        return DB::row("SELECT r.* FROM {p}prayer_requests r WHERE r.id = ? AND $w", array_merge([(int)$id], $p)) ?? abort(404);
    }

    public function prayed(string $id): void
    {
        $r = $this->find($id);
        $new = DB::run('INSERT IGNORE INTO {p}prayer_log (request_id, user_id, prayed_on) VALUES (?, ?, CURDATE())', [$r['id'], Auth::id()])->rowCount();
        if ($new) {
            DB::run('UPDATE {p}prayer_requests SET prayed_count = prayed_count + 1 WHERE id = ?', [$r['id']]);
            $owner = $r['created_by'] ? (int)$r['created_by'] : user_id_for_person($r['person_id'] ? (int)$r['person_id'] : null);
            if ($owner && $owner !== Auth::id() && !DB::val('SELECT 1 FROM {p}notifications WHERE user_id = ? AND title = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)', [$owner, __('Someone prayed for your request today 🙏')])) {
                notify_user($owner, __('Someone prayed for your request today 🙏'), url('/prayer'));
            }
        }
        back('/prayer');
    }

    public function update(string $id): void
    {
        $r = $this->find($id);
        $mine = (int)$r['created_by'] === Auth::id() || ($r['person_id'] && (int)$r['person_id'] === person_id());
        if (!$mine && !can('pastoral.view') && !can('prayer.team')) {
            abort(403);
        }
        $status = in_array(input('status'), ['open', 'answered', 'archived'], true) ? input('status') : $r['status'];
        DB::update('prayer_requests', ['status' => $status, 'answer_enc' => Crypto::encrypt(trim((string)input('answer', '')) ?: (string)Crypto::decrypt($r['answer_enc'])), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
        audit('prayer.updated', 'prayer_request', (int)$r['id'], ['status' => $status]);
        flash('success', $status === 'answered' ? __('Praise God! Marked as answered.') : __('Updated.'));
        redirect('/prayer');
    }

    public function publicForm(): void
    {
        view('prayer/public', ['title' => __('Prayer request')], 'layout_public');
    }

    public function publicStore(): void
    {
        if (!empty($_POST['website'])) {
            redirect('/public/events');
        }
        if ((int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email = 'public:prayer' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [client_ip()]) >= 10) {
            abort(429);
        }
        DB::insert('login_attempts', ['email' => 'public:prayer', 'ip' => client_ip(), 'success' => 1]);
        $text = trim((string)input('request', ''));
        if ($text === '' || !input_bool('consent')) {
            flash('error', __('Please write your request and tick the consent box.'));
            redirect('/pray');
        }
        $vis = input('visibility') === 'pastors' ? 'pastors' : 'team';
        $email = filter_var((string)input('email', ''), FILTER_VALIDATE_EMAIL) ?: null;
        $id = DB::insert('prayer_requests', ['name' => mb_substr((string)input('name', ''), 0, 160) ?: __('Anonymous'), 'email' => $email, 'request_enc' => Crypto::encrypt(mb_substr($text, 0, 4000)), 'visibility' => $vis]);
        self::alertTeam($id, $vis);
        audit('prayer.public_submitted', 'prayer_request', $id);
        flash('success', __('Thank you. We will be praying for you.'));
        redirect('/pray');
    }
}
