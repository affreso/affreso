<?php
declare(strict_types=1);

/**
 * Demo mode: a public showcase site with one-click role sign-ins, no outgoing email/SMS/payments,
 * protections so visitors cannot lock each other out, and a nightly reset to fresh sample data.
 * Enable with 'demo_mode' => true in config/config.php (the installer sets this when you tick "demo site").
 */
final class Demo
{
    public static function on(): bool
    {
        return (bool)config('demo_mode', false);
    }

    /** key => [first name, last name, role slug, label, description] */
    public static function accounts(): array
    {
        return [
            'super_admin' => ['Jordan', 'Lee', 'super_admin', __('Super Administrator'), __('Everything: users, roles, settings, privacy tools and the audit log.')],
            'pastor' => ['David', 'Thompson', 'pastor', __('Pastor / Elder'), __('Members, confidential pastoral notes, care follow-ups, course approval and reports.')],
            'church_admin' => ['Amy', 'Wilson', 'church_admin', __('Church Administrator'), __('Members and households, events, rooms, rosters, communications.')],
            'ministry_leader' => ['Sarah', 'Thompson', 'ministry_leader', __('Ministry Leader'), __('Leads the Worship Team: builds and publishes its rosters, approves its volunteers.')],
            'safeguarding' => ['Hannah', 'Chen', 'safeguarding_officer', __('Safeguarding Officer'), __('Working With Children Checks, child-safety compliance; leads Children\'s Ministry.')],
            'volunteer' => ['Esther', 'Okafor', 'volunteer', __('Volunteer'), __('Has a roster invitation to answer, policies to sign, training to finish and a reimbursement claim.')],
            'member' => ['Ruth', 'Murray', 'member', __('Member'), __('Own profile, events, groups, giving history and privacy choices.')],
            'finance' => ['Wei', 'Chen', 'finance', __('Finance Officer'), __('Giving, statements, bank imports and paying reimbursements.')],
            'checkin' => ['Joel', 'Kim', 'checkin_volunteer', __('Check-in Volunteer'), __('Checks children in and out with security codes and labels.')],
        ];
    }

    public static function userIds(): array
    {
        $ids = json_decode((string)setting('demo_user_ids', '[]'), true);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    public static function isDemoUser(?int $uid): bool
    {
        return self::on() && $uid !== null && in_array($uid, self::userIds(), true);
    }

    /** Create the demo logins on top of the sample data and set up a few things to try. */
    public static function createAccounts(): void
    {
        $ids = [];
        $map = [];
        foreach (self::accounts() as $key => [$first, $last, $slug]) {
            $pid = (int)DB::val('SELECT id FROM {p}people WHERE first_name = ? AND last_name = ? ORDER BY id LIMIT 1', [$first, $last]);
            if (!$pid) {
                $pid = DB::insert('people', ['first_name' => $first, 'last_name' => $last, 'email' => strtolower($first . '.' . $last) . '@example.org', 'membership_status' => 'member', 'consent_contact' => 1, 'consent_updated_at' => date('Y-m-d H:i:s')]);
            }
            $email = (string)DB::val('SELECT email FROM {p}people WHERE id = ?', [$pid]) ?: strtolower($first . '.' . $last) . '@example.org';
            $uid = DB::insert('users', ['person_id' => $pid, 'email' => $email, 'password_hash' => password_hash(Crypto::token(24), PASSWORD_DEFAULT), 'ical_token' => Crypto::token(20)]);
            DB::insert('user_roles', ['user_id' => $uid, 'role_id' => (int)DB::val('SELECT id FROM {p}roles WHERE slug = ?', [$slug])]);
            $ids[] = $uid;
            $map[$key] = $pid;
        }
        DB::run('INSERT INTO {p}settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', ['demo_user_ids', json_encode($ids)]);
        // Data is fresh now, so the next automatic reset is tomorrow.
        DB::run('INSERT INTO {p}jobs_lock (name, ran_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE ran_at = NOW()', ['demo_reset']);
        setting('__flush');

        // Give the volunteer a published roster invitation to accept, decline or swap.
        $welcome = DB::row("SELECT r.id FROM {p}rosters r JOIN {p}groups g ON g.id = r.group_id WHERE g.name = 'Welcome Team' ORDER BY r.id LIMIT 1");
        if ($welcome) {
            $slot = DB::val("SELECT a.id FROM {p}roster_assignments a JOIN {p}positions p ON p.id = a.position_id WHERE a.roster_id = ? AND p.name = 'Greeter' AND a.person_id IS NULL LIMIT 1", [$welcome['id']]);
            if ($slot) {
                DB::update('roster_assignments', ['person_id' => $map['volunteer'], 'status' => 'pending'], 'id = ?', [$slot]);
            }
            $other = DB::val("SELECT a.id FROM {p}roster_assignments a JOIN {p}positions p ON p.id = a.position_id WHERE a.roster_id = ? AND p.name = 'Usher' AND a.person_id IS NULL LIMIT 1", [$welcome['id']]);
            if ($other) {
                DB::update('roster_assignments', ['person_id' => (int)DB::val("SELECT id FROM {p}people WHERE first_name = 'Amy' AND last_name = 'Wilson'"), 'status' => 'accepted', 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [$other]);
            }
            DB::update('rosters', ['status' => 'published'], 'id = ?', [$welcome['id']]);
        }
        // A confidential pastoral note for the pastor to see.
        DB::insert('pastoral_notes', ['person_id' => $map['member'], 'author_id' => $ids[1], 'category' => 'visit', 'body_enc' => Crypto::encrypt('Home visit. Ruth is settling in well after her move and would value a call from the women\'s ministry. (Sample note — demo data.)')]);
        // A few notifications so the bell has something in it.
        foreach ([[$ids[5], 'Serving invitation: Greeter, ' . date('D j M', strtotime('next sunday'))], [$ids[5], 'Training assigned: Child Safety Essentials'], [$ids[3], 'New volunteer application waiting for review'], [$ids[1], 'Course ready for review: Welcoming Newcomers']] as [$uid, $title]) {
            DB::insert('notifications', ['user_id' => $uid, 'title' => $title]);
        }
        $draft = DB::insert('courses', ['title' => 'Welcoming Newcomers', 'summary' => 'How our Welcome Team greets, connects and follows up with first-time guests.', 'category' => 'Welcoming newcomers', 'status' => 'review', 'pass_mark' => 80, 'created_by' => $ids[2]]);
        DB::insert('lessons', ['course_id' => $draft, 'title' => 'First impressions', 'lesson_type' => 'text', 'body' => "Smile, introduce yourself by name, and offer to show newcomers around.\n\nInvite them to fill in a connect card so someone can follow up during the week.", 'sort' => 1]);
        DB::insert('course_reviews', ['course_id' => $draft, 'reviewer_id' => $ids[2], 'decision' => 'submitted', 'comment' => 'Ready for review.']);
    }

    /** Sign in as one of the demo roles (no password, no two-step). */
    public static function login(string $key): bool
    {
        if (!self::on() || !isset(self::accounts()[$key])) {
            return false;
        }
        $idx = array_search($key, array_keys(self::accounts()), true);
        $uid = self::userIds()[$idx] ?? null;
        $u = $uid ? DB::row('SELECT * FROM {p}users WHERE id = ?', [$uid]) : null;
        if (!$u) {
            return false;
        }
        // Self-heal anything a previous visitor changed.
        DB::update('users', ['status' => 'active', 'locked_until' => null, 'mfa_enabled' => 0, 'mfa_secret' => null], 'id = ?', [$uid]);
        if ($u['person_id']) {
            DB::update('people', ['archived_at' => null], 'id = ?', [$u['person_id']]);
        }
        Auth::login(DB::row('SELECT * FROM {p}users WHERE id = ?', [$uid]));
        return true;
    }

    /** Wipe everything and rebuild fresh sample data. The site owner's own login is kept. */
    public static function reset(): void
    {
        $owner = DB::row('SELECT u.*, p.first_name, p.last_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE u.id NOT IN (' . DB::in(self::userIds()) . ') ORDER BY u.id LIMIT 1', self::userIds());
        $keep = [];
        foreach (['church_name', 'church_state', 'cron_key', 'language', 'currency', 'privacy_policy_text'] as $k) {
            $keep[$k] = setting($k);
        }
        $prefix = DB::$prefix;
        foreach (DB::pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (str_starts_with((string)$t, $prefix)) {
                DB::pdo()->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$t) . '`');
            }
        }
        Installer::createSchema($prefix);
        setting('__flush');
        Installer::seed([
            'church_name' => (string)($keep['church_name'] ?: 'Demo Church'), 'state' => (string)($keep['church_state'] ?: 'NSW'),
            'admin_name' => $owner ? trim($owner['first_name'] . ' ' . $owner['last_name']) : 'Site Owner',
            'admin_email' => $owner['email'] ?? 'owner@example.org', 'admin_password' => Crypto::token(16),
            'sample_data' => true, 'demo' => true,
        ]);
        if ($owner) {
            DB::update('users', ['password_hash' => $owner['password_hash'], 'mfa_secret' => $owner['mfa_secret'], 'mfa_enabled' => $owner['mfa_enabled'], 'mfa_recovery' => $owner['mfa_recovery'], 'ical_token' => $owner['ical_token']], 'email = ?', [$owner['email']]);
        }
        foreach ($keep as $k => $v) {
            if ($v !== null) {
                set_setting($k, (string)$v);
            }
        }
        foreach (glob(STORAGE_DIR . '/uploads/*') as $f) {
            if (is_file($f) && !in_array(basename($f), ['index.php', '.gitkeep', '.htaccess'], true)) {
                @unlink($f);
            }
        }
        foreach (['cron', 'daily', 'demo_reset'] as $lock) {
            DB::run('INSERT INTO {p}jobs_lock (name, ran_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE ran_at = NOW()', [$lock]);
        }
        audit('demo.reset');
    }

    /** Stop the request with a friendly message if an action is not allowed on the demo site. */
    public static function block(string $back, ?string $message = null): void
    {
        if (self::on()) {
            flash('warning', $message ?? __('This is switched off on the demo site.'));
            redirect($back);
        }
    }
}
