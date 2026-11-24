<?php
declare(strict_types=1);

final class CommsController
{
    public function index(): void
    {
        $rows = DB::all('SELECT m.*, p.first_name, p.last_name, p.preferred_name, (SELECT COUNT(*) FROM {p}message_recipients r WHERE r.message_id = m.id AND r.status = \'sent\') AS sent, (SELECT COUNT(*) FROM {p}message_recipients r WHERE r.message_id = m.id AND r.status = \'failed\') AS failed FROM {p}messages m LEFT JOIN {p}users u ON u.id = m.sent_by LEFT JOIN {p}people p ON p.id = u.person_id ORDER BY m.id DESC LIMIT 100');
        $queued = (int)DB::val("SELECT COUNT(*) FROM {p}message_recipients WHERE status = 'queued'");
        view('comms/index', ['title' => __('Communications'), 'rows' => $rows, 'queued' => $queued]);
    }

    public function create(): void
    {
        $groups = array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name'), 'name', 'id');
        view('comms/create', ['title' => __('New message'), 'groups' => $groups, 'smsEnabled' => Sms::enabled(), 'preselect' => input_int('group')]);
    }

    /** Resolve an audience to person ids. Only people who consented to contact are included. */
    private function audience(): array
    {
        $type = (string)input('audience', 'all');
        $base = 'SELECT DISTINCT p.id FROM {p}people p';
        $where = ' WHERE p.archived_at IS NULL AND p.consent_contact = 1';
        $params = [];
        $label = '';
        switch ($type) {
            case 'members':
                $where .= " AND p.membership_status = 'member'";
                $label = __('Members');
                break;
            case 'regulars':
                $where .= " AND p.membership_status IN ('member','regular')";
                $label = __('Members & regular attenders');
                break;
            case 'visitors':
                $where .= " AND p.membership_status = 'visitor'";
                $label = __('Visitors');
                break;
            case 'volunteers':
                $base .= ' JOIN {p}volunteer_positions vp ON vp.person_id = p.id AND vp.approved_at IS NOT NULL';
                $label = __('All volunteers');
                break;
            case 'groups':
                $gids = input_ids('group_ids');
                $base .= ' JOIN {p}group_members gm ON gm.person_id = p.id';
                $where .= ' AND gm.group_id IN (' . DB::in($gids) . ')';
                $params = $gids;
                $label = __('Groups: %s', implode(', ', DB::col('SELECT name FROM {p}groups WHERE id IN (' . DB::in($gids) . ')', $gids)));
                break;
            case 'people':
                $ids = input_ids('person_ids');
                $where .= ' AND p.id IN (' . DB::in($ids) . ')';
                $params = $ids;
                $label = __('%d selected people', count($ids));
                break;
            default:
                $label = __('Everyone');
        }
        if (input('adults_only')) {
            $where .= " AND p.household_role <> 'child'";
        }
        return [array_map('intval', DB::col($base . $where, $params)), $label];
    }

    public function send(): void
    {
        $channel = input('channel') === 'sms' ? 'sms' : 'email';
        $subject = trim((string)input('subject', ''));
        $body = trim((string)input('body', ''));
        if ($body === '' || ($channel === 'email' && $subject === '')) {
            flash('error', __('Enter a subject and message.'));
            back('/comms/new');
        }
        if ($channel === 'sms' && !Sms::enabled()) {
            flash('error', __('SMS is not set up. Add your ClickSend or Twilio details in Administration → Settings.'));
            back('/comms/new');
        }
        [$ids, $label] = $this->audience();
        if (!$ids) {
            flash('error', __('No one matches that audience (only people who have consented to contact are included).'));
            back('/comms/new');
        }
        $mid = DB::insert('messages', ['channel' => $channel, 'subject' => $channel === 'email' ? mb_substr($subject, 0, 200) : null, 'body' => $body, 'audience' => mb_substr($label, 0, 255), 'sent_by' => Auth::id(), 'status' => 'sending']);
        $n = 0;
        foreach ($ids as $pid) {
            $p = DB::row('SELECT * FROM {p}people WHERE id = ?', [$pid]);
            $personal = strtr($body, ['{first_name}' => $p['preferred_name'] ?: $p['first_name'], '{last_name}' => $p['last_name']]);
            $ok = $channel === 'email'
                ? Notify::emailPerson($pid, $subject, nl2br(e($personal)), false, $mid)
                : Notify::smsPerson($pid, $personal, false, $mid);
            $n += $ok ? 1 : 0;
        }
        DB::update('messages', ['recipient_count' => $n, 'status' => $n ? 'sending' : 'sent'], 'id = ?', [$mid]);
        Notify::processQueue(20);
        audit('message.sent', 'message', $mid, ['channel' => $channel, 'recipients' => $n]);
        flash('success', __('Message queued for %d people (others have opted out of %s). The first batch has been sent; the rest will go out with the scheduled task.', $n, $channel === 'email' ? __('email') : __('SMS')));
        redirect('/comms/' . $mid);
    }

    public function show(string $id): void
    {
        $m = DB::row('SELECT * FROM {p}messages WHERE id = ?', [(int)$id]) ?? abort(404);
        $rec = DB::all('SELECT r.*, p.first_name, p.last_name, p.preferred_name FROM {p}message_recipients r LEFT JOIN {p}people p ON p.id = r.person_id WHERE r.message_id = ? ORDER BY r.status, p.last_name', [$m['id']]);
        view('comms/show', ['title' => $m['subject'] ?: __('SMS message'), 'm' => $m, 'rec' => $rec]);
    }

    public function announcements(): void
    {
        $rows = DB::all('SELECT * FROM {p}announcements ORDER BY id DESC LIMIT 50');
        view('comms/announcements', ['title' => __('Announcements'), 'rows' => $rows]);
    }

    public function storeAnnouncement(): void
    {
        $t = trim((string)input('title', ''));
        $b = trim((string)input('body', ''));
        if ($t !== '' && $b !== '') {
            $id = DB::insert('announcements', ['title' => mb_substr($t, 0, 200), 'body' => $b, 'publish_on' => input_date('publish_on') ?? date('Y-m-d'), 'expires_on' => input_date('expires_on'), 'created_by' => Auth::id()]);
            audit('announcement.created', 'announcement', $id);
            flash('success', __('Announcement published on everyone\'s dashboard.'));
        }
        redirect('/announcements');
    }

    public function deleteAnnouncement(string $id): void
    {
        DB::run('DELETE FROM {p}announcements WHERE id = ?', [(int)$id]);
        redirect('/announcements');
    }
}
