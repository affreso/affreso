<?php
declare(strict_types=1);

final class Installer
{
    public static function requirements(): array
    {
        return [
            'PHP 8.1 or newer' => PHP_VERSION_ID >= 80100,
            'PDO MySQL extension' => extension_loaded('pdo_mysql'),
            'OpenSSL extension (encryption)' => extension_loaded('openssl') && Crypto::available(),
            'cURL extension (SMS, Stripe)' => extension_loaded('curl'),
            'mbstring extension' => extension_loaded('mbstring'),
            'fileinfo extension (uploads)' => extension_loaded('fileinfo'),
            'config/ folder is writable' => is_writable(BASE_DIR . '/config'),
            'storage/ folder is writable' => is_writable(STORAGE_DIR) && is_writable(STORAGE_DIR . '/uploads'),
        ];
    }

    public static function createSchema(string $prefix): void
    {
        $sql = str_replace('{p}', $prefix, (string)file_get_contents(BASE_DIR . '/database/schema.sql'));
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            DB::pdo()->exec($stmt);
        }
        // Fresh installs get every upgrade applied straight away.
        DB::run("INSERT INTO {p}settings (k, v) VALUES ('db_version', '1')");
        setting('__flush');
        Migrator::run();
    }

    public static function seed(array $o): int
    {
        foreach (default_roles() as $slug => [$name, $desc, $perms]) {
            $rid = (int)DB::val('SELECT id FROM {p}roles WHERE slug = ?', [$slug]) ?: DB::insert('roles', ['slug' => $slug, 'name' => $name, 'description' => $desc, 'is_system' => 1]);
            foreach ($perms as $p) {
                DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$rid, $p]);
            }
        }
        $defaults = [
            'church_name' => $o['church_name'], 'church_state' => $o['state'] ?? 'NSW', 'church_email' => $o['admin_email'],
            'language' => 'en', 'currency' => 'AUD', 'mail_from_email' => $o['admin_email'], 'mail_from_name' => $o['church_name'],
            'smtp_port' => '587', 'smtp_security' => 'tls', 'sms_provider' => 'none', 'sms_default_country_code' => '61',
            'session_timeout_minutes' => '60', 'mfa_required_for_staff' => '1', 'pastoral_access_logging' => '1',
            'retention_visitor_months' => '24', 'retention_archived_years' => '7', 'retention_audit_years' => '7', 'retention_messages_months' => '24',
            'wwcc_reminder_days' => '60', 'roster_reminder_days' => '2', 'cron_key' => Crypto::token(16),
            'giving_dgr_statement' => 'Gifts to this church are not tax deductible unless made to a fund with Deductible Gift Recipient (DGR) status.',
            'privacy_officer' => $o['admin_name'], 'privacy_contact' => $o['admin_email'], 'connect_followup_user' => '',
        ];
        foreach ($defaults as $k => $v) {
            DB::run('INSERT INTO {p}settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
        }
        setting('__flush');
        DB::run("UPDATE {p}campuses SET name = ? WHERE name = 'Main campus'", [$o['church_name']]);
        foreach (['General Offering', 'Missions', 'Building Fund', 'Benevolence'] as $f) {
            DB::insert('funds', ['name' => $f]);
        }
        [$first, $last] = array_pad(explode(' ', trim($o['admin_name']), 2), 2, '');
        $pid = DB::insert('people', ['first_name' => $first ?: 'Admin', 'last_name' => $last ?: 'User', 'email' => mb_strtolower($o['admin_email']), 'membership_status' => 'member', 'consent_contact' => 1, 'consent_updated_at' => date('Y-m-d H:i:s')]);
        $uid = DB::insert('users', ['person_id' => $pid, 'email' => mb_strtolower($o['admin_email']), 'password_hash' => password_hash($o['admin_password'], PASSWORD_DEFAULT), 'ical_token' => Crypto::token(20), 'password_changed_at' => date('Y-m-d H:i:s')]);
        DB::insert('user_roles', ['user_id' => $uid, 'role_id' => (int)DB::val("SELECT id FROM {p}roles WHERE slug = 'super_admin'")]);
        DB::update('settings', ['v' => (string)$uid], 'k = ?', ['connect_followup_user']);
        if (!empty($o['sample_data']) || !empty($o['demo'])) {
            self::sampleData($uid);
        }
        if (!empty($o['demo'])) {
            Demo::createAccounts();
        }
        return $uid;
    }

    /** Demonstration data so a church can explore Affreso before entering real records. */
    public static function sampleData(int $adminUid): void
    {
        $rooms = [];
        foreach ([['Main Auditorium', 300, 0], ['Church Hall', 120, 0], ['Kids Room 1', 25, 0], ['Projector & Screen', null, 1]] as [$n, $c, $eq]) {
            $rooms[$n] = DB::insert('rooms', ['name' => $n, 'capacity' => $c, 'is_equipment' => $eq]);
        }
        $households = [];
        $people = [];
        $data = [
            ['Nguyen', [['Michael', 'adult', 'member', '1982-04-11'], ['Grace', 'adult', 'member', '1985-09-02'], ['Lily', 'child', 'member', '2015-06-20']]],
            ['Thompson', [['Sarah', 'adult', 'member', '1976-01-30'], ['David', 'adult', 'member', '1974-12-14']]],
            ['Okafor', [['Daniel', 'adult', 'regular', '1993-03-08'], ['Esther', 'adult', 'regular', '1995-07-19']]],
            ['Chen', [['Wei', 'adult', 'member', '1988-10-05'], ['Hannah', 'adult', 'member', '1990-02-27'], ['Joshua', 'child', 'member', '2017-11-03']]],
            ['Patel', [['Ravi', 'adult', 'visitor', '1999-05-23']]],
            ['Murray', [['Ruth', 'adult', 'member', '1961-08-16']]],
            ['Kim', [['Joel', 'adult', 'member', '2001-09-12']]],
            ['Wilson', [['Amy', 'adult', 'member', '1987-04-04'], ['Ben', 'adult', 'member', '1986-06-06']]],
        ];
        foreach ($data as [$family, $members]) {
            $hid = DB::insert('households', ['name' => $family . ' household', 'suburb' => 'Parramatta', 'state' => 'NSW', 'postcode' => '2150']);
            foreach ($members as [$fn, $hr, $status, $bd]) {
                $people[$fn] = DB::insert('people', ['household_id' => $hid, 'household_role' => $hr, 'first_name' => $fn, 'last_name' => $family, 'email' => $hr === 'adult' ? strtolower($fn . '.' . $family) . '@example.org' : null, 'mobile' => $hr === 'adult' ? '04' . random_int(10000000, 99999999) : null, 'birthdate' => $bd, 'membership_status' => $status, 'directory_visible' => $hr === 'adult' ? 1 : 0, 'consent_contact' => 1, 'consent_updated_at' => date('Y-m-d H:i:s'), 'first_visit' => date('Y-m-d', strtotime('-' . random_int(30, 900) . ' days'))]);
            }
        }
        $g = [];
        foreach ([['Worship Team', 'team', 0], ['Welcome Team', 'team', 0], ["Children's Ministry", 'team', 1], ['AV & Livestream', 'team', 0], ['Tuesday Night Bible Study', 'small_group', 0], ['Young Adults', 'small_group', 0]] as [$n, $t, $child]) {
            $g[$n] = DB::insert('groups', ['name' => $n, 'group_type' => $t, 'child_related' => $child, 'is_open' => $t === 'small_group' ? 1 : 0, 'meeting_schedule' => $t === 'small_group' ? 'Tuesdays 7:30pm' : 'Sundays', 'location' => $t === 'small_group' ? 'Church Hall' : 'Main Auditorium']);
        }
        $c1 = DB::insert('courses', ['title' => 'Volunteer Induction', 'summary' => 'Welcome to serving at our church: our vision, expectations and code of conduct.', 'category' => 'Induction', 'status' => 'published', 'pass_mark' => 80, 'created_by' => $adminUid, 'approved_by' => $adminUid, 'approved_at' => date('Y-m-d H:i:s'), 'self_enrol' => 1]);
        $c2 = DB::insert('courses', ['title' => 'Child Safety Essentials', 'summary' => 'Child Safe Standards, recognising and responding to concerns, and our reporting procedures.', 'category' => 'Safeguarding', 'status' => 'published', 'pass_mark' => 100, 'validity_months' => 24, 'prerequisite_id' => $c1, 'created_by' => $adminUid, 'approved_by' => $adminUid, 'approved_at' => date('Y-m-d H:i:s')]);
        DB::insert('lessons', ['course_id' => $c1, 'title' => 'Why we serve', 'lesson_type' => 'scripture', 'body' => "1 Peter 4:10 — Each of you should use whatever gift you have received to serve others, as faithful stewards of God's grace in its various forms.\n\nServing is an act of worship and love for our church family.", 'sort' => 1]);
        DB::insert('lessons', ['course_id' => $c1, 'title' => 'Our code of conduct', 'lesson_type' => 'text', 'body' => "Volunteers represent Christ and this church. Please be punctual, respectful, and follow the directions of your ministry leader.\n\nIf you cannot attend a rostered shift, decline or request a swap in Affreso as early as possible.", 'sort' => 2]);
        DB::insert('quiz_questions', ['course_id' => $c1, 'question' => 'What should you do if you cannot make a rostered shift?', 'options' => json_encode(['Just not turn up', 'Decline or request a swap in Affreso as early as possible', 'Send a friend without telling anyone']), 'correct_index' => 1, 'sort' => 1]);
        DB::insert('lessons', ['course_id' => $c2, 'title' => 'Child Safe Standards', 'lesson_type' => 'text', 'body' => "Every child has the right to be safe. Our church follows the National Principles for Child Safe Organisations.\n\nNever be alone with a child out of sight of other adults. Follow the two-adult rule in every children's ministry room.", 'sort' => 1]);
        DB::insert('lessons', ['course_id' => $c2, 'title' => 'Responding to a concern', 'lesson_type' => 'text', 'body' => "If a child discloses harm or you have a concern: listen, stay calm, do not investigate, write down what was said, and report immediately to the Safeguarding Officer. If a child is in immediate danger call 000.", 'sort' => 2]);
        DB::insert('quiz_questions', ['course_id' => $c2, 'question' => 'A child is in immediate danger. What do you do first?', 'options' => json_encode(['Call 000', 'Email the pastor', 'Wait until after the service']), 'correct_index' => 0, 'sort' => 1]);
        DB::insert('quiz_questions', ['course_id' => $c2, 'question' => 'Which practice should always be followed in children\'s ministry rooms?', 'options' => json_encode(['One adult is fine', 'The two-adult rule', 'Close the door for quiet']), 'correct_index' => 1, 'sort' => 2]);

        $pos = [];
        foreach ([['Worship Team', 'Vocals', 0], ['Worship Team', 'Keys', 0], ['Welcome Team', 'Greeter', 0], ['Welcome Team', 'Usher', 0], ["Children's Ministry", 'Kids Leader', 1], ["Children's Ministry", 'Kids Helper', 1], ['AV & Livestream', 'Sound Desk', 0], ['AV & Livestream', 'Slides', 0]] as [$gn, $pn, $wwcc]) {
            $pos[$pn] = DB::insert('positions', ['group_id' => $g[$gn], 'name' => $pn, 'requires_wwcc' => $wwcc, 'min_age' => $wwcc ? 18 : null, 'max_per_month' => 3]);
            DB::insert('position_courses', ['position_id' => $pos[$pn], 'course_id' => $c1]);
            if ($wwcc) {
                DB::insert('position_courses', ['position_id' => $pos[$pn], 'course_id' => $c2]);
            }
        }
        $serve = ['Michael' => ['Vocals', 'Greeter'], 'Grace' => ['Kids Leader'], 'Sarah' => ['Keys', 'Vocals'], 'David' => ['Usher', 'Greeter'], 'Daniel' => ['Sound Desk', 'Slides'], 'Esther' => ['Greeter', 'Kids Helper'], 'Wei' => ['Sound Desk'], 'Hannah' => ['Kids Leader', 'Kids Helper'], 'Joel' => ['Slides', 'Vocals'], 'Amy' => ['Usher'], 'Ben' => ['Keys']];
        foreach ($serve as $fn => $plist) {
            DB::insert('enrolments', ['course_id' => $c1, 'person_id' => $people[$fn], 'status' => 'completed', 'score' => 100, 'completed_at' => date('Y-m-d H:i:s', strtotime('-60 days')), 'certificate_code' => strtoupper(bin2hex(random_bytes(5)))]);
            foreach ($plist as $i => $pn) {
                DB::insert('volunteer_positions', ['person_id' => $people[$fn], 'position_id' => $pos[$pn], 'approved_by' => $adminUid, 'approved_at' => date('Y-m-d H:i:s'), 'preference' => $i === 0 ? 'preferred' : 'willing']);
                $gid = (int)DB::val('SELECT group_id FROM {p}positions WHERE id = ?', [$pos[$pn]]);
                DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id) VALUES (?, ?)', [$gid, $people[$fn]]);
            }
        }
        foreach (['Grace', 'Hannah'] as $fn) {
            DB::insert('enrolments', ['course_id' => $c2, 'person_id' => $people[$fn], 'status' => 'completed', 'score' => 100, 'completed_at' => date('Y-m-d H:i:s', strtotime('-50 days')), 'expires_at' => date('Y-m-d', strtotime('+22 months')), 'certificate_code' => strtoupper(bin2hex(random_bytes(5)))]);
            DB::insert('safeguarding_checks', ['person_id' => $people[$fn], 'check_type' => 'WWCC', 'jurisdiction' => 'NSW', 'reference_enc' => Crypto::encrypt('WWC' . random_int(1000000, 9999999) . 'E'), 'status' => 'verified', 'verified_on' => date('Y-m-d', strtotime('-50 days')), 'expires_on' => date('Y-m-d', strtotime($fn === 'Grace' ? '+40 days' : '+3 years')), 'verified_by' => $adminUid]);
        }
        DB::insert('enrolments', ['course_id' => $c2, 'person_id' => $people['Esther'], 'status' => 'assigned', 'due_date' => date('Y-m-d', strtotime('+14 days'))]);
        DB::run("UPDATE {p}group_members SET member_role = 'leader' WHERE (group_id = ? AND person_id = ?) OR (group_id = ? AND person_id = ?) OR (group_id = ? AND person_id = ?)", [$g['Worship Team'], $people['Sarah'], $g["Children's Ministry"], $people['Hannah'], $g['AV & Livestream'], $people['Wei']]);
        foreach (['Michael', 'Grace', 'Ruth', 'Amy', 'Ben'] as $fn) {
            DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id, member_role) VALUES (?, ?, ?)', [$g['Tuesday Night Bible Study'], $people[$fn], $fn === 'Ruth' ? 'leader' : 'member']);
        }
        foreach (['Joel', 'Daniel', 'Esther'] as $fn) {
            DB::run('INSERT IGNORE INTO {p}group_members (group_id, person_id) VALUES (?, ?)', [$g['Young Adults'], $people[$fn]]);
        }
        $sunday = date('Y-m-d', strtotime('next sunday'));
        foreach (['Worship Team' => ['Vocals', 'Vocals', 'Keys'], 'Welcome Team' => ['Greeter', 'Greeter', 'Usher'], "Children's Ministry" => ['Kids Leader', 'Kids Helper'], 'AV & Livestream' => ['Sound Desk', 'Slides']] as $gn => $slots) {
            $rid = DB::insert('rosters', ['group_id' => $g[$gn], 'title' => $gn . ' — Sunday Service', 'service_date' => $sunday, 'start_time' => '09:00:00', 'end_time' => '11:30:00', 'status' => 'draft', 'created_by' => $adminUid]);
            foreach ($slots as $pn) {
                DB::insert('roster_assignments', ['roster_id' => $rid, 'position_id' => $pos[$pn]]);
            }
        }
        $e1 = DB::insert('events', ['title' => 'Sunday Service', 'category' => 'Service', 'location' => 'Main Auditorium', 'start_at' => $sunday . ' 09:30:00', 'end_at' => $sunday . ' 11:00:00', 'is_public' => 1, 'created_by' => $adminUid]);
        $e2 = DB::insert('events', ['title' => 'Newcomers Lunch', 'description' => 'Meet the pastors and find out more about our church over lunch.', 'category' => 'Community', 'location' => 'Church Hall', 'start_at' => date('Y-m-d', strtotime($sunday . ' +7 days')) . ' 12:00:00', 'end_at' => date('Y-m-d', strtotime($sunday . ' +7 days')) . ' 13:30:00', 'is_public' => 1, 'registration_enabled' => 1, 'capacity' => 30, 'joining_instructions' => 'Head to the Church Hall straight after the service. Children are welcome — please let us know of any dietary needs.', 'created_by' => $adminUid]);
        DB::insert('room_bookings', ['room_id' => $rooms['Main Auditorium'], 'event_id' => $e1, 'title' => 'Sunday Service', 'start_at' => $sunday . ' 08:00:00', 'end_at' => $sunday . ' 12:00:00', 'status' => 'approved', 'requested_by' => $adminUid, 'approved_by' => $adminUid]);
        DB::insert('room_bookings', ['room_id' => $rooms['Church Hall'], 'event_id' => $e2, 'title' => 'Newcomers Lunch', 'start_at' => date('Y-m-d', strtotime($sunday . ' +7 days')) . ' 11:30:00', 'end_at' => date('Y-m-d', strtotime($sunday . ' +7 days')) . ' 14:00:00', 'status' => 'approved', 'requested_by' => $adminUid, 'approved_by' => $adminUid]);
        foreach (['Ravi', 'Daniel', 'Esther'] as $fn) {
            $p = DB::row('SELECT * FROM {p}people WHERE id = ?', [$people[$fn]]);
            DB::insert('event_registrations', ['event_id' => $e2, 'person_id' => $p['id'], 'name' => person_name($p), 'email' => $p['email']]);
        }
        DB::insert('care_tasks', ['person_id' => $people['Ravi'], 'task_type' => 'newcomer', 'title' => 'Welcome call to Ravi Patel', 'details' => 'First visited recently; interested in Young Adults.', 'assigned_to' => $adminUid, 'due_date' => date('Y-m-d', strtotime('+3 days')), 'created_by' => $adminUid]);
        DB::insert('volunteer_applications', ['person_id' => $people['Ruth'], 'position_ids' => (string)$pos['Greeter'], 'skills' => 'Hospitality, cooking', 'availability' => 'Most Sundays', 'status' => 'submitted']);
        $fund = (int)DB::val('SELECT id FROM {p}funds ORDER BY id LIMIT 1');
        foreach (['Michael' => 15000, 'Sarah' => 25000, 'Wei' => 10000, 'Ruth' => 5000] as $fn => $amt) {
            // Wei and Ruth gave online, so the demo can show a refund back to the card.
            $online = in_array($fn, ['Wei', 'Ruth'], true);
            DB::insert('donations', ['person_id' => $people[$fn], 'fund_id' => $fund, 'amount_cents' => $amt, 'method' => $online ? 'stripe' : 'eft', 'stripe_pi' => $online ? 'pi_demo_' . strtolower($fn) : null, 'status' => 'paid', 'received_on' => date('Y-m-d', strtotime('-' . random_int(1, 40) . ' days')), 'receipt_no' => 'R' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 5, '0', STR_PAD_LEFT)]);
        }
        self::samplePhase2($adminUid, $people, $g, $pos, $rooms);
        self::samplePhase3($adminUid);
        self::samplePhase4($adminUid);
        DB::insert('announcements', ['title' => 'Welcome to Affreso', 'body' => 'This is our new church management system. Update your profile, set your availability and check your rosters here.', 'audience' => 'all', 'publish_on' => date('Y-m-d'), 'created_by' => $adminUid]);
    }

    /** Sample data for the version 4 modules. */
    private static function samplePhase4(int $adminUid): void
    {
        $pid = fn(string $first, string $last) => (int)DB::val('SELECT id FROM {p}people WHERE first_name = ? AND last_name = ?', [$first, $last]);
        // Ruth Murray (the demo member) gets two young children so pre-check-in can be tried.
        $ruth = $pid('Ruth', 'Murray');
        $hid = (int)DB::val('SELECT household_id FROM {p}people WHERE id = ?', [$ruth]);
        if ($ruth && $hid) {
            DB::update('people', ['birthdate' => '1987-08-16', 'mobile' => '0412 555 019'], 'id = ?', [$ruth]);
            foreach ([['Noah', '2019-05-02'], ['Isla', '2022-02-10']] as [$fn, $dob]) {
                $kid = DB::insert('people', ['first_name' => $fn, 'last_name' => 'Murray', 'household_id' => $hid, 'household_role' => 'child', 'birthdate' => $dob, 'membership_status' => 'member', 'campus_id' => DB::val('SELECT MIN(id) FROM {p}campuses')]);
                DB::insert('child_profiles', ['person_id' => $kid, 'allergies_enc' => $fn === 'Isla' ? Crypto::encrypt('Dairy (mild)') : null, 'photo_permission' => 1]);
            }
        }

        // Online forms.
        $k = fn(string $type, string $label, bool $req = false, array $opts = [], string $help = '') => ['key' => substr(md5($label), 0, 8), 'type' => $type, 'label' => $label, 'required' => $req, 'options' => $opts, 'help' => $help];
        $camp = [$k('heading', 'About the camper'), $k('text', "Camper's full name", true), ['profile' => 'birthdate'] + $k('date', 'Date of birth', true), $k('select', 'School year in 2027', true, ['Year 7', 'Year 8', 'Year 9', 'Year 10', 'Year 11', 'Year 12']),
            $k('phone', 'Parent or guardian mobile', true), $k('heading', 'Health and safety'), $k('textarea', 'Allergies, medical conditions and medication', false, [], 'Leave blank if none.'),
            $k('checkboxes', 'Dietary requirements', false, ['Vegetarian', 'Gluten free', 'Dairy free', 'Halal']), $k('yesno', 'May we take photos for church use?', true),
            $k('consent', 'I give permission for my child to attend and for leaders to seek medical treatment in an emergency.', true)];
        $campEvent = DB::insert('events', ['title' => 'Youth Camp 2027', 'description' => 'Three days of games, bush walks and Bible teaching for Years 7–12.', 'category' => 'Youth', 'location' => 'Galston Gorge Conference Centre',
            'start_at' => '2027-01-15 16:00:00', 'end_at' => '2027-01-17 14:00:00', 'is_public' => 1, 'registration_enabled' => 1, 'joining_instructions' => 'Drop-off is at the church car park at 4pm on Friday. Bring a sleeping bag, torch, Bible and water bottle.', 'created_by' => $adminUid]);
        $campId = DB::insert('forms', ['title' => 'Youth Camp 2027', 'event_id' => $campEvent, 'slug' => 'youth-camp-2027', 'intro' => "Three days at Galston Gorge, 15–17 January 2027. Cost covers food, accommodation and activities.\nPlaces are limited.", 'fields' => json_encode($camp),
            'audience' => 'public', 'status' => 'open', 'closes_at' => date('Y-m-d 23:59:00', strtotime('+6 weeks')), 'capacity' => 40, 'price_cents' => 18000, 'instalments' => 3, 'confirmation' => "Thanks for registering! We'll email the packing list in early January.", 'notify_emails' => 'youth@example.org', 'created_by' => $adminUid]);
        $answers = [
            ['Ella Chen', 'hannah.chen@example.org', ['Ella Chen', '2012-09-14', 'Year 9', '0423 551 207', '', 'Asthma — has a puffer', ['Gluten free'], 'yes', 'yes'], 'paid'],
            ['Jack Thompson', 'sarah.thompson@example.org', ['Jack Thompson', '2013-03-02', 'Year 8', '0411 220 845', '', '', [], 'yes', 'yes'], 'part_paid'],
            ['Mia Okafor', 'esther.okafor@example.org', ['Mia Okafor', '2011-11-21', 'Year 10', '0430 118 402', '', 'Peanut allergy (EpiPen)', ['Vegetarian'], 'no', 'yes'], 'awaiting_payment'],
            ['Liam Walker', 'kate.walker@example.org', ['Liam Walker', '2012-06-30', 'Year 9', '0422 914 336', '', '', [], 'yes', 'yes'], 'cancelled'],
        ];
        // Jack's family is in the database (Jack himself has no date of birth yet), so staff can update his profile from the form.
        $sarah = $pid('Sarah', 'Thompson');
        if ($sarah) {
            DB::insert('people', ['first_name' => 'Jack', 'last_name' => 'Thompson', 'household_id' => DB::val('SELECT household_id FROM {p}people WHERE id = ?', [$sarah]), 'household_role' => 'child', 'membership_status' => 'regular', 'campus_id' => DB::val('SELECT MIN(id) FROM {p}campuses')]);
        }
        foreach ($answers as [$name, $email, $vals, $status]) {
            $a = [];
            foreach (array_values(array_filter($camp, fn($f) => $f['type'] !== 'heading')) as $i => $f) {
                $a[$f['key']] = $vals[$i + ($i >= 4 ? 1 : 0)] ?? '';
            }
            $when = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 9) . ' days'));
            $parts = $status === 'part_paid' ? 3 : 1;
            $sid = DB::insert('form_submissions', ['form_id' => $campId, 'person_id' => $status === 'part_paid' ? ($sarah ?: null) : null, 'name' => $name, 'email' => $email, 'answers' => Forms::encryptAnswers($a), 'status' => $status,
                'amount_cents' => 18000, 'instalments' => $parts, 'paid_cents' => $status === 'paid' ? 18000 : ($status === 'part_paid' ? 6000 : 0), 'refunded_cents' => $status === 'cancelled' ? 18000 : 0,
                'staff_notes' => $status === 'cancelled' ? 'Family moving interstate — refunded in full.' : null, 'created_at' => $when]);
            if ($status !== 'awaiting_payment') {
                DB::insert('form_payments', ['submission_id' => $sid, 'amount_cents' => $status === 'part_paid' ? 6000 : 18000, 'refunded_cents' => $status === 'cancelled' ? 18000 : 0, 'method' => 'card', 'paid_at' => $when]);
                // Confirmed places appear on the event; the refunded one shows as cancelled.
                DB::insert('event_registrations', ['event_id' => $campEvent, 'person_id' => null, 'name' => $name, 'email' => $email, 'guests' => 0,
                    'status' => $status === 'cancelled' ? 'cancelled' : 'registered', 'form_submission_id' => $sid, 'created_at' => $when]);
            }
        }
        // A ticketed event: family tickets count as five places.
        $picnic = DB::insert('events', ['title' => 'Church Family Picnic & Carols', 'description' => 'A picnic dinner on the lawn followed by carols by candlelight. Bring a rug; we provide the food.', 'category' => 'Community', 'location' => 'Church lawn',
            'start_at' => date('Y-m-d', strtotime('second saturday of december')) . ' 17:30:00', 'end_at' => date('Y-m-d', strtotime('second saturday of december')) . ' 20:30:00', 'is_public' => 1, 'registration_enabled' => 1, 'created_by' => $adminUid]);
        $picnicForm = DB::insert('forms', ['title' => 'Family Picnic & Carols tickets', 'slug' => 'picnic-carols', 'intro' => 'Tickets cover dinner and a candle for carols. Children under 3 come free — no ticket needed.', 'event_id' => $picnic,
            'audience' => 'public', 'status' => 'open', 'capacity' => 120, 'price_cents' => 500, 'created_by' => $adminUid, 'notify_emails' => 'office@hillside.example.org',
            'tickets' => json_encode([['name' => 'Adult', 'price_cents' => 1500, 'places' => 1], ['name' => 'Child (3–12)', 'price_cents' => 500, 'places' => 1], ['name' => 'Family (2 adults, up to 3 children)', 'price_cents' => 4000, 'places' => 5]]),
            'fields' => json_encode([$k('textarea', 'Dietary needs for your group')])]);
        foreach ([['Murray family', 'ruth.murray@example.org', [['name' => 'Family (2 adults, up to 3 children)', 'qty' => 1, 'price_cents' => 4000, 'places' => 5]], 4000, 5],
                  ['Esther Okafor', 'esther.okafor@example.org', [['name' => 'Adult', 'qty' => 2, 'price_cents' => 1500, 'places' => 1], ['name' => 'Child (3–12)', 'qty' => 1, 'price_cents' => 500, 'places' => 1]], 3500, 3]] as [$who, $em, $lines, $cents, $places]) {
            $when = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 4) . ' days'));
            $psid = DB::insert('form_submissions', ['form_id' => $picnicForm, 'name' => $who, 'email' => $em, 'answers' => Forms::encryptAnswers([]), 'tickets' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'places' => $places,
                'status' => 'paid', 'amount_cents' => $cents, 'paid_cents' => $cents, 'created_at' => $when]);
            DB::insert('form_payments', ['submission_id' => $psid, 'amount_cents' => $cents, 'method' => 'card', 'paid_at' => $when]);
            DB::insert('event_registrations', ['event_id' => $picnic, 'name' => $who, 'email' => $em, 'guests' => $places - 1, 'status' => 'registered', 'form_submission_id' => $psid, 'created_at' => $when]);
        }
        // A members' form that updates their own profile.
        DB::insert('forms', ['title' => 'Update your contact details', 'slug' => 'my-details', 'intro' => 'Moved house or changed number? Let us know and your profile will be updated straight away.', 'audience' => 'members', 'status' => 'open', 'profile_auto' => 1, 'created_by' => $adminUid,
            'fields' => json_encode([['profile' => 'mobile'] + $k('phone', 'Mobile'), ['profile' => 'address_line'] + $k('text', 'Street address'), ['profile' => 'suburb'] + $k('text', 'Suburb'), ['profile' => 'postcode'] + $k('text', 'Postcode'), $k('textarea', 'Anything else we should know?')])]);
        DB::insert('forms', ['title' => 'Baptism enquiry', 'slug' => 'baptism', 'intro' => 'Thinking about baptism? Let us know and one of the pastors will get in touch.', 'audience' => 'members', 'status' => 'open', 'created_by' => $adminUid,
            'fields' => json_encode([$k('radio', 'Is this for', true, ['Me', 'My child', 'Someone else']), $k('textarea', 'Tell us a little about your journey of faith'), $k('checkboxes', 'Best times to meet', false, ['Sunday after church', 'Weekday evening', 'Weekday daytime'])])]);
        $enq = DB::insert('forms', ['title' => 'Hall hire enquiry', 'slug' => 'hall-hire', 'intro' => 'Our hall seats 120 and has a kitchen. Tell us what you have in mind.', 'audience' => 'public', 'status' => 'open', 'notify_emails' => 'office@example.org', 'created_by' => $adminUid,
            'fields' => json_encode([$k('phone', 'Phone'), $k('date', 'Preferred date', true), $k('select', 'Type of event', true, ['Birthday', 'Community group', 'Wedding reception', 'Other']), $k('number', 'Expected guests'), $k('textarea', 'Anything else?')])]);
        DB::insert('form_submissions', ['form_id' => $enq, 'name' => 'Priya Sharma', 'email' => 'priya@example.org', 'status' => 'submitted',
            'answers' => Forms::encryptAnswers([substr(md5('Phone'), 0, 8) => '0400 222 333', substr(md5('Preferred date'), 0, 8) => date('Y-m-d', strtotime('+5 weeks saturday')), substr(md5('Type of event'), 0, 8) => 'Birthday', substr(md5('Expected guests'), 0, 8) => '45', substr(md5('Anything else?'), 0, 8) => "Daughter's 10th birthday — we'd like the kitchen."])]);

        // A building fund campaign with pledges and gifts.
        $fund = (int)DB::val("SELECT id FROM {p}funds WHERE name = 'Building Fund'") ?: DB::insert('funds', ['name' => 'Building Fund']);
        $start = date('Y-m-d', strtotime('first day of -8 months'));
        $cid = DB::insert('campaigns', ['name' => 'New Hall Building Fund', 'description' => "Together we're raising \$450,000 over three years to rebuild the hall for kids' ministry, youth and community groups.", 'goal_cents' => 45000000, 'fund_id' => $fund,
            'starts_on' => $start, 'ends_on' => date('Y-m-d', strtotime($start . ' +3 years -1 day')), 'is_public' => 1, 'created_by' => $adminUid]);
        // [first, last, total pledged, frequency, how faithfully they've given so far]
        $pledgers = [['Michael', 'Nguyen', 1800000, 'monthly', 1.0], ['David', 'Thompson', 1200000, 'monthly', 0.6], ['Ruth', 'Murray', 300000, 'fortnightly', 1.1], ['Hannah', 'Chen', 500000, 'quarterly', 1.0], ['Amy', 'Wilson', 100000, 'once', 0]];
        $periods = ['monthly' => 36, 'fortnightly' => 78, 'quarterly' => 12];
        foreach ($pledgers as [$fn, $ln, $total, $freq, $rate]) {
            $gift = $rate ? (int)(round($total / $periods[$freq] * $rate / 100) * 100) : 0;
            $p = DB::row('SELECT * FROM {p}people WHERE first_name = ? AND last_name = ?', [$fn, $ln]);
            if (!$p) {
                continue;
            }
            DB::insert('pledges', ['campaign_id' => $cid, 'person_id' => $p['id'], 'name' => person_name($p), 'email' => $p['email'], 'total_cents' => $total, 'frequency' => $freq, 'created_at' => $start . ' 10:00:00']);
            if ($gift) {
                $step = ['monthly' => '+1 month', 'fortnightly' => '+2 weeks', 'quarterly' => '+3 months'][$freq];
                for ($d = strtotime($start . ' +5 days'); $d < time(); $d = strtotime($step, $d)) {
                    DB::insert('donations', ['person_id' => $p['id'], 'fund_id' => $fund, 'donor_name' => person_name($p), 'amount_cents' => $gift, 'method' => 'eft', 'status' => 'paid', 'received_on' => date('Y-m-d', $d)]);
                }
            }
        }
        DB::insert('pledges', ['campaign_id' => $cid, 'name' => 'A. Supporter', 'email' => 'supporter@example.org', 'total_cents' => 250000, 'frequency' => 'yearly', 'anonymous' => 1]);
        DB::insert('donations', ['fund_id' => $fund, 'donor_name' => 'Hillside Op Shop', 'amount_cents' => 1250000, 'method' => 'eft', 'status' => 'paid', 'received_on' => date('Y-m-d', strtotime($start . ' +2 months')), 'notes' => 'Op shop proceeds']);
    }

    /** Sample data for the version 3 modules. */
    private static function samplePhase3(int $adminUid): void
    {
        $main = (int)DB::val('SELECT MIN(id) FROM {p}campuses');
        $north = (int)DB::val('SELECT MAX(id) FROM {p}campuses');
        // Repeating events: the Sunday service every week, prayer night on the first Wednesday each month.
        $sun = (int)DB::val("SELECT id FROM {p}events WHERE title = 'Sunday Service' ORDER BY id LIMIT 1");
        if ($sun) {
            EventSeries::create($sun, ['freq' => 'weekly', 'interval_n' => 1, 'weekdays' => '7', 'until_date' => null, 'max_count' => null]);
        }
        $firstWed = date('Y-m-d', strtotime('first wednesday of next month'));
        $pn = DB::insert('events', ['title' => 'Leaders Prayer Night', 'category' => 'Prayer', 'location' => 'Church Hall', 'campus_id' => $main, 'start_at' => $firstWed . ' 19:30:00', 'end_at' => $firstWed . ' 21:00:00', 'is_public' => 0, 'created_by' => $adminUid]);
        EventSeries::create($pn, ['freq' => 'monthly_weekday', 'interval_n' => 1, 'weekdays' => null, 'until_date' => null, 'max_count' => null]);

        // Twenty weeks of attendance headcounts for both campuses.
        $lastSun = date('Y-m-d', strtotime(date('N') == 7 ? 'today -7 days' : 'last sunday'));
        mt_srand(42);
        for ($w = 19; $w >= 0; $w--) {
            $d = date('Y-m-d', strtotime("$lastSun -$w weeks"));
            $holiday = in_array((int)date('n', strtotime($d)), [1, 7], true) ? 0.82 : 1.0;
            $growth = 1 + (19 - $w) * 0.006;
            foreach ([[$main, 'Sunday service', 138, 18, 34, 26], [$north, 'Sunday service', 52, 6, 15, 8]] as [$cid, $label, $a, $y, $c, $o]) {
                $f = $holiday * $growth;
                DB::insert('attendance_counts', ['campus_id' => $cid, 'count_date' => $d, 'service_label' => $label,
                    'adults' => (int)round($a * $f + mt_rand(-8, 8)), 'youth' => (int)round($y * $f + mt_rand(-3, 3)), 'children' => (int)round($c * $f + mt_rand(-4, 4)),
                    'online' => max(0, (int)round($o + mt_rand(-5, 5))), 'visitors' => mt_rand(0, $cid === $main ? 5 : 2), 'recorded_by' => $adminUid,
                    'notes' => $holiday < 1 ? 'School holidays' : null]);
            }
        }
        mt_srand();

        // A self check-in kiosk, and a session open today so the kiosk can be tried.
        DB::run("UPDATE {p}people SET mobile = '0428 044 740' WHERE first_name = 'Michael' AND last_name = 'Nguyen'");
        DB::insert('kiosks', ['name' => 'Foyer iPad', 'campus_id' => $main, 'exit_pin_hash' => password_hash('1234', PASSWORD_DEFAULT), 'print_labels' => 1, 'active' => 1, 'created_by' => $adminUid]);
        DB::insert('checkin_sessions', ['title' => 'Kids church', 'session_date' => date('Y-m-d'), 'start_time' => '09:30:00', 'campus_id' => $main, 'status' => 'open', 'created_by' => $adminUid]);
    }

    /** Sample data for the version 2 modules. */
    private static function samplePhase2(int $adminUid, array $people, array $g, array $pos, array $rooms): void
    {
        $main = (int)DB::val('SELECT MIN(id) FROM {p}campuses');
        $north = DB::insert('campuses', ['name' => 'Northside campus', 'address' => '12 Church St, Hornsby NSW']);
        DB::update('groups', ['campus_id' => $north], 'id = ?', [$g['Young Adults']]);
        foreach (['people', 'groups', 'events', 'rosters', 'rooms'] as $t) {
            DB::run("UPDATE {p}$t SET campus_id = ? WHERE campus_id IS NULL", [$main]);
        }
        DB::insert('events', ['title' => 'Northside Family Picnic', 'category' => 'Community', 'location' => 'Hornsby Park', 'campus_id' => $north, 'start_at' => date('Y-m-d', strtotime('next saturday')) . ' 11:00:00', 'end_at' => date('Y-m-d', strtotime('next saturday')) . ' 14:00:00', 'is_public' => 1, 'created_by' => $adminUid]);

        // Children's check-in
        $little = DB::insert('checkin_classes', ['name' => 'Little Lambs', 'min_age' => 0, 'max_age' => 4, 'ratio' => 5, 'room_id' => $rooms['Kids Room 1'], 'campus_id' => $main]);
        $kids = DB::insert('checkin_classes', ['name' => 'Kids Church', 'min_age' => 5, 'max_age' => 11, 'ratio' => 8, 'room_id' => $rooms['Church Hall'], 'campus_id' => $main]);
        DB::insert('child_profiles', ['person_id' => $people['Lily'], 'allergies_enc' => Crypto::encrypt('Peanuts and tree nuts — EpiPen in her backpack'), 'medical_enc' => Crypto::encrypt(''), 'needs_enc' => Crypto::encrypt(''), 'emergency_enc' => Crypto::encrypt('Grandma Mai Nguyen 0412 000 111'), 'photo_permission' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        DB::insert('child_profiles', ['person_id' => $people['Joshua'], 'allergies_enc' => Crypto::encrypt(''), 'medical_enc' => Crypto::encrypt('Mild asthma — puffer in bag'), 'needs_enc' => Crypto::encrypt('Can be anxious at drop-off; a quiet welcome helps'), 'emergency_enc' => Crypto::encrypt(''), 'photo_permission' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        DB::insert('authorised_collectors', ['child_id' => $people['Lily'], 'name' => 'Mai Nguyen', 'relationship' => 'Grandmother', 'phone' => '0412 000 111']);
        $last = date('Y-m-d', strtotime('last sunday'));
        $sid = DB::insert('checkin_sessions', ['title' => 'Sunday kids church', 'session_date' => $last, 'start_time' => '09:30:00', 'campus_id' => $main, 'status' => 'closed', 'created_by' => $adminUid]);
        foreach ([[$people['Lily'], $kids, 'K7MQ', 'Grace Nguyen', 'Michael Nguyen (household)'], [$people['Joshua'], $kids, 'R3TX', 'Hannah Chen', 'Wei Chen (household)']] as [$child, $class, $code, $in, $out]) {
            DB::insert('checkins', ['session_id' => $sid, 'child_id' => $child, 'class_id' => $class, 'security_code' => $code, 'checked_in_at' => $last . ' 09:20:00', 'checked_in_by' => $adminUid, 'dropped_off_by' => $in, 'checked_out_at' => $last . ' 11:05:00', 'checked_out_by' => $adminUid, 'collected_by' => $out]);
        }
        foreach (['Grace', 'Hannah'] as $fn) {
            DB::insert('checkin_leaders', ['session_id' => $sid, 'class_id' => $kids, 'person_id' => $people[$fn], 'checked_in_at' => $last . ' 09:10:00', 'checked_out_at' => $last . ' 11:10:00']);
        }

        // Resource library & policies
        $doc = function (string $title, string $cat, string $vis, int $ack, string $body, string $desc) use ($adminUid): int {
            $id = DB::insert('documents', ['title' => $title, 'category' => $cat, 'visibility' => $vis, 'requires_ack' => $ack, 'ack_renew_months' => $ack ? 24 : null, 'description' => $desc, 'created_by' => $adminUid]);
            DB::insert('document_versions', ['document_id' => $id, 'version' => 1, 'body' => $body, 'content_hash' => hash('sha256', $body), 'notes' => 'First version', 'created_by' => $adminUid]);
            return $id;
        };
        $coc = $doc('Volunteer Code of Conduct', 'Policy', 'volunteers', 1, "1. Serve with integrity, humility and respect.
2. Never be alone with a child out of sight of other adults.
3. Do not contact children privately online or by phone.
4. Report any concern about a child's safety to the Safeguarding Officer the same day.
5. Respect privacy: do not share personal information you learn while serving.
6. Arrive on time and tell your leader early if you cannot serve.

(Sample policy for demonstration — replace with your church's own.)", 'Expectations for everyone who serves.');
        $csp = $doc('Child Safe Policy', 'Policy', 'volunteers', 1, "Our church is committed to the safety and wellbeing of every child.

We follow the National Principles for Child Safe Organisations. All children's ministry workers hold a current Working With Children Check, complete child-safety training every two years, follow the two-adult rule, and report concerns immediately.

(Sample policy for demonstration — replace with your church's own.)", 'How we keep children safe.');
        $doc('Volunteer Handbook', 'Handbook', 'volunteers', 0, "Welcome to the team! This handbook explains how rosters, swaps, training and communication work at our church.

Rosters: you will receive an invitation by email and in Affreso. Please accept or decline within three days.
Swaps: use 'Request a swap' on My rosters; other trained volunteers will be asked to cover.
Training: complete any assigned courses in My training.", 'Practical guide for volunteers.');
        $doc('Tuesday Bible Study — Romans notes', 'Bible study', 'groups', 0, "Week 1: Romans 1:1–17 — the gospel of God.
Week 2: Romans 3:21–26 — righteousness from God.
Week 3: Romans 5:1–11 — peace with God.
Week 4: Romans 8:28–39 — nothing can separate us.", 'Study notes for the Tuesday group.');
        DB::update('documents', ['group_ids' => (string)$g['Tuesday Night Bible Study']], 'title = ?', ['Tuesday Bible Study — Romans notes']);
        foreach (['Kids Leader', 'Kids Helper'] as $pn) {
            DB::insert('position_documents', ['position_id' => $pos[$pn], 'document_id' => $coc]);
            DB::insert('position_documents', ['position_id' => $pos[$pn], 'document_id' => $csp]);
        }
        foreach (['Grace', 'Hannah'] as $fn) {
            foreach ([$coc, $csp] as $d) {
                $p = DB::row('SELECT first_name, last_name FROM {p}people WHERE id = ?', [$people[$fn]]);
                DB::insert('acknowledgements', ['document_id' => $d, 'version' => 1, 'person_id' => $people[$fn], 'signed_name' => $p['first_name'] . ' ' . $p['last_name'], 'content_hash' => (string)DB::val('SELECT content_hash FROM {p}document_versions WHERE document_id = ? AND version = 1', [$d]), 'ip' => '203.0.113.10', 'signed_at' => date('Y-m-d H:i:s', strtotime('-40 days'))]);
            }
        }

        // Service planning
        $songs = [];
        foreach ([['Amazing Grace', 'John Newton', '22025', 'G'], ['Be Thou My Vision', 'Traditional Irish', '30639', 'D'], ['Holy, Holy, Holy', 'Reginald Heber; John B. Dykes', '1156', 'D'], ['It Is Well with My Soul', 'Horatio Spafford; Philip Bliss', '25376', 'C'], ['Great Is Thy Faithfulness', 'Thomas Chisholm; William Runyan', '18723', 'D']] as [$t, $a, $c, $k]) {
            $songs[$t] = DB::insert('songs', ['title' => $t, 'author' => $a, 'ccli_number' => $c, 'default_key' => $k]);
        }
        $sunday = date('Y-m-d', strtotime('next sunday'));
        foreach ([[$last, ['Holy, Holy, Holy', 'Amazing Grace', 'It Is Well with My Soul'], 'final'], [$sunday, ['Be Thou My Vision', 'Great Is Thy Faithfulness', 'Amazing Grace'], 'draft']] as [$date, $set, $status]) {
            $sp = DB::insert('service_plans', ['title' => 'Sunday Service', 'service_date' => $date, 'start_time' => '09:30:00', 'campus_id' => $main, 'theme' => 'Romans: Peace with God', 'status' => $status, 'notes' => 'Communion this week. Band call 8:15am.', 'created_by' => $adminUid]);
            $items = [['other', 'Welcome & call to worship', 3, null, 'Sarah Thompson'], ['song', $set[0], 4, $set[0], 'Worship Team'], ['song', $set[1], 5, $set[1], 'Worship Team'], ['prayer', 'Prayers of the people', 4, null, 'Ruth Murray'], ['announcement', 'Announcements', 3, null, 'Amy Wilson'], ['reading', 'Romans 5:1–11', 3, null, 'Joel Kim'], ['sermon', 'Peace with God', 30, null, 'David Thompson'], ['communion', 'Lord\'s Supper', 8, null, 'David Thompson'], ['song', $set[2], 4, $set[2], 'Worship Team'], ['other', 'Benediction', 1, null, 'David Thompson']];
            foreach ($items as $i => [$type, $title, $min, $song, $who]) {
                DB::insert('plan_items', ['plan_id' => $sp, 'sort' => $i + 1, 'item_type' => $type, 'title' => str_replace("\\'", "'", $title), 'song_id' => $song ? $songs[$song] : null, 'song_key' => $song ? DB::val('SELECT default_key FROM {p}songs WHERE id = ?', [$songs[$song]]) : null, 'duration_minutes' => $min, 'person_text' => $who]);
            }
        }

        // Prayer, incidents, reimbursements, regular giving
        foreach ([[$people['Ruth'], 'Please pray for my sister, who starts chemotherapy next week.', 'team', 4], [$people['Joel'], 'Pray for wisdom as I finish my university exams.', 'church', 7], [null, 'Thank you for praying for my job search — I start my new role on Monday!', 'church', 12]] as [$pid, $text, $vis, $count]) {
            DB::insert('prayer_requests', ['person_id' => $pid, 'name' => $pid ? (string)DB::val("SELECT CONCAT(first_name, ' ', last_name) FROM {p}people WHERE id = ?", [$pid]) : 'Anonymous', 'request_enc' => Crypto::encrypt($text), 'visibility' => $vis, 'prayed_count' => $count, 'status' => $pid ? 'open' : 'answered', 'answer_enc' => $pid ? null : Crypto::encrypt('Praise God for His provision!')]);
        }
        DB::insert('incidents', ['incident_type' => 'whs', 'occurred_at' => $last . ' 10:15:00', 'location' => 'Church Hall', 'campus_id' => $main, 'summary' => 'Child grazed knee during games', 'details_enc' => Crypto::encrypt('During the relay game a child tripped on the edge of the mat and grazed her knee. First aid applied (cleaned, band-aid). Parent informed at pick-up.'), 'people_enc' => Crypto::encrypt('Kids Church leaders: Grace Nguyen, Hannah Chen'), 'injury_enc' => Crypto::encrypt('Minor graze, left knee'), 'action_enc' => Crypto::encrypt('First aid; mat edge taped down after the session.'), 'severity' => 'low', 'child_involved' => 1, 'status' => 'actioned', 'reported_by' => $adminUid]);
        foreach ([['Esther', 'Morning tea for newcomers lunch', 6420, 'Food & hospitality', 'submitted'], ['Sarah', 'Guitar strings and capo', 4895, 'Equipment', 'approved']] as [$fn, $title, $cents, $cat, $status]) {
            DB::insert('reimbursements', ['person_id' => $people[$fn], 'title' => $title, 'amount_cents' => $cents, 'expense_date' => date('Y-m-d', strtotime('-5 days')), 'category' => $cat, 'group_id' => $fn === 'Sarah' ? $g['Worship Team'] : $g['Welcome Team'], 'payee_name' => $fn . ' (sample)', 'bsb_enc' => Crypto::encrypt('062000'), 'account_enc' => Crypto::encrypt('12345678'), 'status' => $status, 'approver_id' => $status === 'approved' ? $adminUid : null, 'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null]);
        }
        DB::insert('recurring_gifts', ['person_id' => $people['Michael'], 'donor_email' => 'michael.nguyen@example.org', 'amount_cents' => 5000, 'frequency' => 'week', 'fund_id' => (int)DB::val('SELECT MIN(id) FROM {p}funds'), 'status' => 'active', 'stripe_subscription_id' => 'sub_sample']);
    }

    public static function writeConfig(array $db, string $tz, string $appKey, bool $demo = false): void
    {
        $cfg = [
            'db' => $db,
            'app_key' => $appKey,
            'timezone' => $tz,
            'pretty_urls' => false,
            'base_url' => '',
            'debug' => false,
            'demo_mode' => $demo,
            'installed_at' => date('c'),
        ];
        $php = "<?php\n// Affreso configuration — generated by the installer on " . date('Y-m-d H:i') . ".\n// KEEP THIS FILE PRIVATE AND BACKED UP. The app_key decrypts pastoral notes and other protected data.\nreturn " . var_export($cfg, true) . ";\n";
        if (file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
            throw new RuntimeException('Could not write config/config.php');
        }
        @chmod(CONFIG_FILE, 0640);
    }
}
