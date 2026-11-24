<?php
declare(strict_types=1);

/**
 * Giving campaigns (building funds, missions appeals) and pledges.
 * Progress is worked out from gifts already recorded to the campaign's fund between its dates.
 * Gifts from anyone in a pledger's household count towards that pledge, because couples
 * usually pledge together but may give from different accounts.
 */
final class CampaignsController
{
    public const FREQS = ['once', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'yearly'];

    public static function freqLabel(string $f): string
    {
        return ['once' => __('One-off'), 'weekly' => __('Weekly'), 'fortnightly' => __('Fortnightly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly'), 'yearly' => __('Yearly')][$f] ?? $f;
    }

    private static function giftWhere(): string
    {
        return "d.status = 'paid' AND COALESCE(d.received_on, DATE(d.created_at)) BETWEEN ? AND ?";
    }

    public static function raised(array $c): int
    {
        return (int)DB::val('SELECT COALESCE(SUM(d.amount_cents), 0) FROM {p}donations d WHERE d.fund_id = ? AND ' . self::giftWhere(), [$c['fund_id'], $c['starts_on'], $c['ends_on']]);
    }

    public static function pledged(int $campaignId): int
    {
        return (int)DB::val("SELECT COALESCE(SUM(total_cents), 0) FROM {p}pledges WHERE campaign_id = ? AND status = 'active'", [$campaignId]);
    }

    /** Given so far towards one pledge (the person's household, or the person alone). */
    public static function givenFor(array $c, array $pledge): int
    {
        if (!$pledge['person_id']) {
            return $pledge['email'] ? (int)DB::val('SELECT COALESCE(SUM(d.amount_cents), 0) FROM {p}donations d WHERE d.fund_id = ? AND d.person_id IS NULL AND d.donor_email = ? AND ' . self::giftWhere(), [$c['fund_id'], $pledge['email'], $c['starts_on'], $c['ends_on']]) : 0;
        }
        $hid = DB::val('SELECT household_id FROM {p}people WHERE id = ?', [$pledge['person_id']]);
        $ids = $hid ? DB::col("SELECT id FROM {p}people WHERE household_id = ? AND household_role <> 'child'", [$hid]) : [$pledge['person_id']];
        return (int)DB::val('SELECT COALESCE(SUM(d.amount_cents), 0) FROM {p}donations d WHERE d.fund_id = ? AND d.person_id IN (' . DB::in($ids) . ') AND ' . self::giftWhere(), array_merge([$c['fund_id']], $ids, [$c['starts_on'], $c['ends_on']]));
    }

    /** How much of a pledge would be given by now if spread evenly over the campaign. */
    public static function expectedByNow(array $c, array $pledge): int
    {
        if ($pledge['frequency'] === 'once') {
            return date('Y-m-d') >= $c['ends_on'] ? (int)$pledge['total_cents'] : 0;
        }
        $start = strtotime(max($c['starts_on'], substr((string)$pledge['created_at'], 0, 10)));
        $end = strtotime($c['ends_on']);
        $frac = $end > $start ? max(0, min(1, (time() - $start) / ($end - $start))) : 1;
        return (int)round($pledge['total_cents'] * $frac);
    }

    private function campaign(string $id): array
    {
        return DB::row('SELECT c.*, f.name AS fund FROM {p}campaigns c LEFT JOIN {p}funds f ON f.id = c.fund_id WHERE c.id = ?', [(int)$id]) ?? abort(404);
    }

    private function funds(): array
    {
        return array_column(DB::all('SELECT id, name FROM {p}funds WHERE active = 1 ORDER BY name'), 'name', 'id');
    }

    public function index(): void
    {
        $rows = DB::all('SELECT c.*, f.name AS fund FROM {p}campaigns c LEFT JOIN {p}funds f ON f.id = c.fund_id ORDER BY c.status, c.ends_on DESC');
        foreach ($rows as &$r) {
            $r['raised'] = self::raised($r);
            $r['pledged'] = self::pledged((int)$r['id']);
        }
        unset($r);
        view('campaigns/index', ['title' => __('Campaigns & pledges'), 'rows' => $rows, 'funds' => $this->funds()]);
    }

    private function data(): array
    {
        $goal = (int)round((float)str_replace([',', '$'], '', (string)input('goal', '0')) * 100);
        $d = ['name' => trim(mb_substr((string)input('name', ''), 0, 160)), 'description' => mb_substr((string)input('description', ''), 0, 5000) ?: null, 'goal_cents' => max(0, $goal),
            'fund_id' => input_int('fund_id'), 'starts_on' => input_date('starts_on'), 'ends_on' => input_date('ends_on'), 'is_public' => input_bool('is_public'),
            'status' => input('status') === 'closed' ? 'closed' : 'active'];
        if ($d['name'] === '' || !$d['fund_id'] || !$d['starts_on'] || !$d['ends_on'] || $d['ends_on'] < $d['starts_on']) {
            flash('error', __('Enter a name, a fund, and a start date before the end date.'));
            back('/campaigns');
        }
        return $d;
    }

    public function store(): void
    {
        $id = DB::insert('campaigns', $this->data() + ['created_by' => Auth::id()]);
        audit('campaign.created', 'campaign', $id);
        flash('success', __('Campaign created.'));
        redirect('/campaigns/' . $id);
    }

    public function update(string $id): void
    {
        $c = $this->campaign($id);
        DB::update('campaigns', $this->data(), 'id = ?', [$c['id']]);
        audit('campaign.updated', 'campaign', (int)$c['id']);
        flash('success', __('Campaign saved.'));
        redirect('/campaigns/' . $c['id']);
    }

    public function show(string $id): void
    {
        $c = $this->campaign($id);
        $pledges = DB::all('SELECT pl.*, p.first_name, p.last_name, p.preferred_name FROM {p}pledges pl LEFT JOIN {p}people p ON p.id = pl.person_id WHERE pl.campaign_id = ? ORDER BY pl.status, pl.created_at DESC', [$c['id']]);
        foreach ($pledges as &$pl) {
            $pl['given'] = self::givenFor($c, $pl);
            $pl['expected'] = self::expectedByNow($c, $pl);
        }
        unset($pl);
        $raised = self::raised($c);
        $fromPledgers = array_sum(array_map(fn($p) => $p['status'] === 'active' ? min($p['given'], (int)$p['total_cents']) : 0, $pledges));
        if (input('export')) {
            audit('report.exported', 'campaign', (int)$c['id']);
            csv_download('pledges-' . Forms::slugify($c['name']) . '.csv', ['name', 'email', 'pledged', 'frequency', 'given', 'expected_by_now', 'status', 'pledged_on'],
                array_map(fn($p) => [$p['person_id'] ? person_name($p) : $p['name'], $p['email'], number_format($p['total_cents'] / 100, 2, '.', ''), $p['frequency'], number_format($p['given'] / 100, 2, '.', ''), number_format($p['expected'] / 100, 2, '.', ''), $p['status'], substr($p['created_at'], 0, 10)], $pledges));
        }
        $weeks = [];
        $from = max(strtotime($c['starts_on']), strtotime('-25 weeks monday this week'));
        foreach (DB::all('SELECT DATE_SUB(COALESCE(d.received_on, DATE(d.created_at)), INTERVAL WEEKDAY(COALESCE(d.received_on, DATE(d.created_at))) DAY) wk, SUM(d.amount_cents) s FROM {p}donations d WHERE d.fund_id = ? AND ' . self::giftWhere() . ' GROUP BY wk ORDER BY wk', [$c['fund_id'], date('Y-m-d', $from), $c['ends_on']]) as $w) {
            $weeks[$w['wk']] = (int)$w['s'];
        }
        view('campaigns/show', ['title' => $c['name'], 'c' => $c, 'pledges' => $pledges, 'raised' => $raised, 'pledged' => self::pledged((int)$c['id']), 'fromPledgers' => $fromPledgers,
            'weeks' => $weeks, 'funds' => $this->funds(), 'people' => people_options_scoped()]);
    }

    private function pledgeData(bool $staff): array
    {
        $total = (int)round((float)str_replace([',', '$'], '', (string)input('total', '0')) * 100);
        $freq = in_array(input('frequency'), self::FREQS, true) ? input('frequency') : 'once';
        if ($total < 100 || $total > 100000000) {
            return [null, __('Enter a pledge amount.')];
        }
        return [['total_cents' => $total, 'frequency' => $freq, 'anonymous' => input_bool('anonymous'), 'notes' => $staff ? (mb_substr((string)input('notes', ''), 0, 255) ?: null) : null], null];
    }

    /** Staff recording a pledge card. */
    public function addPledge(string $id): void
    {
        $c = $this->campaign($id);
        [$d, $err] = $this->pledgeData(true);
        $pid = input_int('person_id');
        $p = $pid ? DB::row('SELECT * FROM {p}people WHERE id = ?', [$pid]) : null;
        $name = $p ? person_name($p) : trim(mb_substr((string)input('name', ''), 0, 160));
        if ($err || $name === '') {
            flash('error', $err ?? __('Choose a person or enter a name.'));
            redirect('/campaigns/' . $c['id']);
        }
        $email = $p['email'] ?? (filter_var((string)input('email', ''), FILTER_VALIDATE_EMAIL) ?: null);
        $plid = DB::insert('pledges', $d + ['campaign_id' => $c['id'], 'person_id' => $p['id'] ?? null, 'name' => $name, 'email' => $email]);
        audit('pledge.recorded', 'pledge', $plid);
        flash('success', __('Pledge recorded for %s.', $name));
        redirect('/campaigns/' . $c['id']);
    }

    public function updatePledge(string $id, string $pid): void
    {
        $c = $this->campaign($id);
        $pl = DB::row('SELECT * FROM {p}pledges WHERE id = ? AND campaign_id = ?', [(int)$pid, $c['id']]) ?? abort(404);
        if (input('action') === 'cancel') {
            DB::update('pledges', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$pl['id']]);
            audit('pledge.cancelled', 'pledge', (int)$pl['id']);
            flash('success', __('Pledge cancelled.'));
        } else {
            [$d, $err] = $this->pledgeData(true);
            if ($err) {
                flash('error', $err);
                redirect('/campaigns/' . $c['id']);
            }
            DB::update('pledges', $d + ['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$pl['id']]);
            audit('pledge.updated', 'pledge', (int)$pl['id']);
            flash('success', __('Pledge updated.'));
        }
        redirect('/campaigns/' . $c['id']);
    }

    /** Email each active pledger their progress. */
    public function remind(string $id): void
    {
        $c = $this->campaign($id);
        $n = 0;
        foreach (DB::all("SELECT * FROM {p}pledges WHERE campaign_id = ? AND status = 'active' AND email IS NOT NULL", [$c['id']]) as $pl) {
            $given = self::givenFor($c, $pl);
            $html = '<p>' . e(__('Thank you for your pledge to %s.', $c['name'])) . '</p>'
                . '<p>' . e(__('You pledged %s (%s). So far you have given %s, and %s remains.', fmt_money((int)$pl['total_cents']), self::freqLabel($pl['frequency']), fmt_money($given), fmt_money(max(0, (int)$pl['total_cents'] - $given)))) . '</p>'
                . '<p>' . e(__('The campaign runs until %s. Gifts from anyone in your household count towards your pledge.', fmt_date($c['ends_on']))) . '</p>'
                . '<p><a href="' . e(url('/give', ['fund' => $c['fund_id']], true)) . '">' . e(__('Give online')) . '</a></p>';
            $pl['person_id'] ? Notify::emailPerson((int)$pl['person_id'], __('Your pledge to %s', $c['name']), $html, true) : Notify::emailAddress((string)$pl['email'], __('Your pledge to %s', $c['name']), $html);
            $n++;
        }
        audit('campaign.reminders_sent', 'campaign', (int)$c['id'], ['count' => $n]);
        flash('success', __('Progress emails queued for %d pledgers.', $n));
        redirect('/campaigns/' . $c['id']);
    }

    // ------------------------------------------------------------------ public & members

    public function publicPage(string $id): void
    {
        $c = $this->campaign($id);
        if (!$c['is_public'] && !(Auth::id() !== null)) {
            abort(404);
        }
        $me = person_id() ? DB::row('SELECT * FROM {p}people WHERE id = ?', [person_id()]) : null;
        $mine = $me ? DB::all("SELECT * FROM {p}pledges WHERE campaign_id = ? AND person_id = ? AND status = 'active'", [$c['id'], $me['id']]) : [];
        foreach ($mine as &$m) {
            $m['given'] = self::givenFor($c, $m);
        }
        unset($m);
        view('campaigns/public', ['title' => $c['name'], 'c' => $c, 'raised' => self::raised($c), 'pledged' => self::pledged((int)$c['id']),
            'pledgers' => (int)DB::val("SELECT COUNT(*) FROM {p}pledges WHERE campaign_id = ? AND status = 'active'", [$c['id']]), 'me' => $me, 'mine' => $mine,
            'open' => $c['status'] === 'active' && $c['ends_on'] >= date('Y-m-d')], (Auth::id() !== null) ? 'layout' : 'layout_public');
    }

    public function publicPledge(string $id): void
    {
        $c = $this->campaign($id);
        if ((!$c['is_public'] && Auth::id() === null) || $c['status'] !== 'active' || $c['ends_on'] < date('Y-m-d')) {
            abort(404);
        }
        if (!empty($_POST['website'])) {
            redirect('/campaign/' . $c['id']);
        }
        if (Auth::id() === null) {
            if ((int)DB::val("SELECT COUNT(*) FROM {p}login_attempts WHERE ip = ? AND email = 'public:pledge' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [client_ip()]) >= 10) {
                abort(429);
            }
            DB::insert('login_attempts', ['email' => 'public:pledge', 'ip' => client_ip(), 'success' => 1]);
        }
        [$d, $err] = $this->pledgeData(false);
        $me = person_id() ? DB::row('SELECT * FROM {p}people WHERE id = ?', [person_id()]) : null;
        $name = $me ? person_name($me) : trim(mb_substr((string)input('name', ''), 0, 160));
        $email = $me['email'] ?? (filter_var(trim((string)input('email', '')), FILTER_VALIDATE_EMAIL) ?: null);
        if ($err || $name === '' || !$email || !input_bool('consent')) {
            flash('error', $err ?? __('Please enter your name and email and tick the box.'));
            redirect('/campaign/' . $c['id'] . '#pledge');
        }
        // A public (signed-out) pledge is never attached to a person just because the email matches.
        $plid = DB::insert('pledges', $d + ['campaign_id' => $c['id'], 'person_id' => $me['id'] ?? null, 'name' => $name, 'email' => mb_strtolower($email)]);
        audit('pledge.made', 'pledge', $plid);
        $html = '<p>' . e(__('Thank you for pledging %s (%s) to %s.', fmt_money((int)$d['total_cents']), self::freqLabel($d['frequency']), $c['name'])) . '</p><p>' . e(__('A pledge is a statement of intent, not a contract — you can change it at any time by contacting the church office.')) . '</p>'
            . '<p><a href="' . e(url('/give', ['fund' => $c['fund_id']], true)) . '">' . e(__('Give online')) . '</a></p>';
        Notify::emailAddress($email, __('Your pledge to %s', $c['name']), $html);
        flash('success', __('Thank you! Your pledge has been recorded.'));
        redirect('/campaign/' . $c['id']);
    }
}
