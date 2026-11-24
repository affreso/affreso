<?php
declare(strict_types=1);

final class AdminController
{
    /** Settings stored encrypted at rest. A blank form value keeps the existing secret. */
    private const SECRETS = ['smtp_pass', 'sms_api_key', 'stripe_secret_key', 'stripe_webhook_secret', 'mailchimp_api_key', 'google_client_secret', 'ms_client_secret', 'printnode_api_key'];

    private const SETTINGS = [
        'general' => ['church_name', 'church_abn', 'church_state', 'church_email', 'church_phone', 'church_address', 'language', 'currency', 'connect_followup_user'],
        'email' => ['mail_from_name', 'mail_from_email', 'mail_reply_to', 'smtp_host', 'smtp_port', 'smtp_security', 'smtp_user', 'smtp_pass'],
        'sms' => ['sms_provider', 'sms_username', 'sms_api_key', 'sms_from', 'sms_default_country_code'],
        'payments' => ['stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'giving_dgr_statement'],
        'security' => ['session_timeout_minutes', 'mfa_required_for_staff', 'pastoral_access_logging', 'swap_requires_approval', 'roster_reminder_days', 'wwcc_reminder_days'],
        'integrations' => ['aba_bank', 'aba_bsb', 'aba_account', 'aba_account_name', 'aba_user_name', 'aba_apca_id', 'aba_balance', 'xero_expense_account', 'xero_income_account', 'mailchimp_api_key', 'mailchimp_list_id', 'google_client_id', 'google_client_secret', 'ms_client_id', 'ms_client_secret', 'ms_tenant', 'push_content', 'event_series_horizon', 'printnode_api_key', 'label_printer_id'],
        'privacy' => ['privacy_officer', 'privacy_contact', 'retention_visitor_months', 'retention_archived_years', 'retention_audit_years', 'retention_messages_months', 'privacy_policy_text'],
    ];

    private const CHECKBOXES = ['mfa_required_for_staff', 'pastoral_access_logging', 'swap_requires_approval', 'aba_balance'];

    public function index(): void
    {
        if (!can('admin.users') && !can('admin.roles') && !can('admin.settings') && !can('audit.view') && !can('privacy.manage')) {
            abort(403);
        }
        $health = [
            __('HTTPS is in use') => is_https(),
            __('Email (SMTP) configured') => setting('smtp_host', '') !== '',
            __('Scheduled task ran in the last hour') => ($ran = DB::val("SELECT ran_at FROM {p}jobs_lock WHERE name = 'cron'")) && strtotime((string)$ran) > time() - 3600,
            __('Debug mode is off') => !config('debug'),
            __('config/config.php is not writable by the web server') => !is_writable(CONFIG_FILE),
            __('Two-step verification required for staff') => (bool)setting('mfa_required_for_staff', '1'),
        ];
        $stats = ['users' => DB::val("SELECT COUNT(*) FROM {p}users WHERE status = 'active'"), 'mfa' => DB::val("SELECT COUNT(*) FROM {p}users WHERE status = 'active' AND mfa_enabled = 1"), 'queued' => DB::val("SELECT COUNT(*) FROM {p}message_recipients WHERE status = 'queued'"), 'failed' => DB::val("SELECT COUNT(*) FROM {p}message_recipients WHERE status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")];
        view('admin/index', ['title' => __('Administration'), 'health' => $health, 'stats' => $stats, 'cronRan' => $ran ?? null]);
    }

    // Users ----------------------------------------------------------------------
    public function users(): void
    {
        $q = (string)input('q', '');
        $role = input_int('role');
        $params = ['%' . $q . '%', '%' . $q . '%'];
        $where = "(u.email LIKE ? OR CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) LIKE ?)";
        if ($role) {
            $where .= ' AND u.id IN (SELECT user_id FROM {p}user_roles WHERE role_id = ?)';
            $params[] = $role;
        }
        $rows = DB::all("SELECT u.*, p.first_name, p.last_name, p.preferred_name, (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM {p}user_roles ur JOIN {p}roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE $where ORDER BY u.status, p.last_name, u.email LIMIT 500", $params);
        $roles = array_column(DB::all('SELECT id, name FROM {p}roles ORDER BY id'), 'name', 'id');
        view('admin/users', ['title' => __('Users'), 'rows' => $rows, 'roles' => $roles, 'q' => $q, 'role' => $role]);
    }

    public function storeUser(): void
    {
        $email = mb_strtolower(trim((string)input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || DB::val('SELECT 1 FROM {p}users WHERE email = ?', [$email])) {
            flash('error', __('Enter a valid email that is not already used by another account.'));
            redirect('/admin/users');
        }
        $pid = input_int('person_id');
        if (!$pid) {
            $first = trim((string)input('first_name', ''));
            $last = trim((string)input('last_name', ''));
            if ($first === '' || $last === '') {
                flash('error', __('Choose an existing person or enter a first and last name.'));
                redirect('/admin/users');
            }
            $pid = DB::insert('people', ['first_name' => mb_substr($first, 0, 80), 'last_name' => mb_substr($last, 0, 80), 'email' => $email, 'membership_status' => 'member']);
        } elseif (DB::val('SELECT 1 FROM {p}users WHERE person_id = ?', [$pid])) {
            flash('error', __('That person already has a login.'));
            redirect('/admin/users');
        }
        $uid = DB::insert('users', ['person_id' => $pid, 'email' => $email, 'password_hash' => password_hash(Crypto::token(24), PASSWORD_DEFAULT), 'ical_token' => Crypto::token(20)]);
        $this->syncRoles($uid, input_ids('role_ids') ?: [(int)DB::val("SELECT id FROM {p}roles WHERE slug = 'member'")]);
        $link = Auth::resetLink($uid, 72);
        Notify::emailAddress($email, __('Your %s account', setting('church_name')), '<p>' . e(__('An account has been created for you on our church management system.')) . '</p><p><a href="' . e($link) . '">' . e(__('Set your password')) . '</a> ' . e(__('(this link works for 3 days)')) . '</p>');
        Notify::processQueue(5);
        audit('user.created', 'user', $uid, ['roles' => input_ids('role_ids')]);
        flash('success', __('Account created and invitation emailed to %s.', $email));
        redirect('/admin/users/' . $uid);
    }

    private function superAdminRoleId(): int
    {
        return (int)DB::val("SELECT id FROM {p}roles WHERE slug = 'super_admin'");
    }

    private function syncRoles(int $uid, array $roleIds): void
    {
        $super = $this->superAdminRoleId();
        $hadSuper = (bool)DB::val('SELECT 1 FROM {p}user_roles WHERE user_id = ? AND role_id = ?', [$uid, $super]);
        $iAmSuper = in_array('super_admin', Auth::roleSlugs((int)Auth::id()), true);
        if (in_array($super, $roleIds, true) && !$hadSuper && !$iAmSuper) {
            $roleIds = array_values(array_diff($roleIds, [$super]));
            flash('warning', __('Only a Super Administrator can grant the Super Administrator role.'));
        }
        if ($hadSuper && !in_array($super, $roleIds, true) && (int)DB::val("SELECT COUNT(*) FROM {p}user_roles ur JOIN {p}users u ON u.id = ur.user_id WHERE ur.role_id = ? AND u.status = 'active'", [$super]) <= 1) {
            $roleIds[] = $super;
            flash('warning', __('The last Super Administrator cannot be removed.'));
        }
        DB::run('DELETE FROM {p}user_roles WHERE user_id = ?', [$uid]);
        foreach (array_unique($roleIds) as $rid) {
            DB::insert('user_roles', ['user_id' => $uid, 'role_id' => $rid]);
        }
    }

    /** Only a Super Administrator may change another Super Administrator's account. */
    private function guardTarget(int $uid): void
    {
        // On the demo site, visitors (demo accounts) cannot change the shared demo logins or the site owner.
        if (Demo::isDemoUser(Auth::id()) && (Demo::isDemoUser($uid) || in_array('super_admin', Auth::roleSlugs($uid), true))) {
            Demo::block('/admin/users/' . $uid, __('Demo sign-in accounts and the site owner\'s account cannot be changed on the demo site.'));
        }
        if (in_array('super_admin', Auth::roleSlugs($uid), true) && !in_array('super_admin', Auth::roleSlugs((int)Auth::id()), true)) {
            abort(403, __('Only a Super Administrator can change this account.'));
        }
    }

    public function user(string $id): void
    {
        $u = DB::row('SELECT u.*, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id WHERE u.id = ?', [(int)$id]) ?? abort(404);
        $roles = DB::all('SELECT * FROM {p}roles ORDER BY id');
        $mine = array_map('intval', DB::col('SELECT role_id FROM {p}user_roles WHERE user_id = ?', [$u['id']]));
        $perms = DB::col('SELECT DISTINCT rp.permission FROM {p}role_permissions rp JOIN {p}user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ? ORDER BY rp.permission', [$u['id']]);
        $activity = DB::all('SELECT * FROM {p}audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$u['id']]);
        view('admin/user', ['title' => person_name($u) ?: $u['email'], 'u' => $u, 'roles' => $roles, 'mine' => $mine, 'perms' => $perms, 'activity' => $activity]);
    }

    public function updateUser(string $id): void
    {
        $u = DB::row('SELECT * FROM {p}users WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->guardTarget((int)$u['id']);
        $status = in_array(input('status'), ['active', 'disabled'], true) ? input('status') : $u['status'];
        if ((int)$u['id'] === Auth::id() && $status !== 'active') {
            flash('error', __('You cannot disable your own account.'));
            redirect('/admin/users/' . $u['id']);
        }
        $campus = input_int('campus_id');
        DB::update('users', ['status' => $status, 'locked_until' => null, 'campus_id' => $campus && isset(Campus::all()[$campus]) ? $campus : null], 'id = ?', [$u['id']]);
        $this->syncRoles((int)$u['id'], input_ids('role_ids'));
        audit('user.updated', 'user', (int)$u['id'], ['status' => $status, 'roles' => input_ids('role_ids')]);
        flash('success', __('User saved.'));
        redirect('/admin/users/' . $u['id']);
    }

    public function resetMfa(string $id): void
    {
        $this->guardTarget((int)$id);
        DB::update('users', ['mfa_secret' => null, 'mfa_enabled' => 0, 'mfa_recovery' => null], 'id = ?', [(int)$id]);
        audit('mfa.reset_by_admin', 'user', (int)$id);
        flash('success', __('Two-step verification reset. The user will set it up again at next sign in.'));
        redirect('/admin/users/' . (int)$id);
    }

    public function sendReset(string $id): void
    {
        $u = DB::row('SELECT * FROM {p}users WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->guardTarget((int)$u['id']);
        $link = Auth::resetLink((int)$u['id'], 24);
        Notify::emailAddress($u['email'], __('Set your %s password', setting('church_name')), '<p>' . e(__('Use this link within 24 hours to set a new password:')) . '</p><p><a href="' . e($link) . '">' . e(__('Set password')) . '</a></p>');
        Notify::processQueue(3);
        audit('password.reset_sent_by_admin', 'user', (int)$u['id']);
        flash('success', __('Password link sent to %s.', $u['email']));
        redirect('/admin/users/' . $u['id']);
    }

    // Roles ----------------------------------------------------------------------
    public function roles(): void
    {
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM {p}user_roles ur WHERE ur.role_id = r.id) AS users FROM {p}roles r ORDER BY r.id');
        $grants = [];
        foreach (DB::all('SELECT * FROM {p}role_permissions') as $g) {
            $grants[$g['role_id']][$g['permission']] = true;
        }
        view('admin/roles', ['title' => __('Roles & permissions'), 'roles' => $roles, 'grants' => $grants, 'edit' => input_int('edit')]);
    }

    public function storeRole(): void
    {
        $name = trim((string)input('name', ''));
        if ($name === '') {
            redirect('/admin/roles');
        }
        $slug = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), 0, 40) . '_' . substr(Crypto::token(2), 0, 4);
        $id = DB::insert('roles', ['slug' => $slug, 'name' => mb_substr($name, 0, 100), 'description' => mb_substr((string)input('description', ''), 0, 255) ?: null]);
        audit('role.created', 'role', $id);
        redirect('/admin/roles', ['edit' => $id]);
    }

    public function updateRole(string $id): void
    {
        $r = DB::row('SELECT * FROM {p}roles WHERE id = ?', [(int)$id]) ?? abort(404);
        if (Demo::isDemoUser(Auth::id())) Demo::block('/admin/roles', __('Role permissions are read-only on the demo site, so every visitor sees the same roles.'));
        if ($r['slug'] === 'super_admin') {
            flash('error', __('The Super Administrator role always has every permission.'));
            redirect('/admin/roles');
        }
        if (input('delete') && !(int)$r['is_system']) {
            DB::run('DELETE FROM {p}role_permissions WHERE role_id = ?', [$r['id']]);
            DB::run('DELETE FROM {p}user_roles WHERE role_id = ?', [$r['id']]);
            DB::run('DELETE FROM {p}roles WHERE id = ?', [$r['id']]);
            audit('role.deleted', 'role', (int)$r['id']);
            redirect('/admin/roles');
        }
        $valid = all_permissions();
        $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), $valid));
        DB::run('DELETE FROM {p}role_permissions WHERE role_id = ?', [$r['id']]);
        foreach ($perms as $p) {
            DB::insert('role_permissions', ['role_id' => $r['id'], 'permission' => $p]);
        }
        DB::update('roles', ['name' => mb_substr((string)input('name', $r['name']), 0, 100), 'description' => mb_substr((string)input('description', ''), 0, 255) ?: null], 'id = ?', [$r['id']]);
        audit('role.permissions_changed', 'role', (int)$r['id'], ['permissions' => $perms]);
        flash('success', __('Permissions for "%s" saved.', $r['name']));
        redirect('/admin/roles');
    }

    // Settings -------------------------------------------------------------------
    public function settings(): void
    {
        $tab = (string)input('tab', 'general');
        if (!isset(self::SETTINGS[$tab]) && !in_array($tab, ['fields', 'cron', 'campuses'], true)) {
            $tab = 'general';
        }
        $values = [];
        foreach (self::SETTINGS[$tab] ?? [] as $k) {
            $values[$k] = in_array($k, self::SECRETS, true) ? (setting($k, '') !== '' ? '__set__' : '') : setting($k, '');
        }
        $staff = [];
        foreach (DB::all("SELECT DISTINCT u.id, u.email, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id JOIN {p}user_roles ur ON ur.user_id = u.id JOIN {p}role_permissions rp ON rp.role_id = ur.role_id WHERE rp.permission = 'care.manage' AND u.status = 'active'") as $s) {
            $staff[$s['id']] = person_name($s) ?: $s['email'];
        }
        $fields = DB::all('SELECT * FROM {p}custom_fields ORDER BY sort, id');
        $campuses = DB::all('SELECT c.*, (SELECT COUNT(*) FROM {p}people p WHERE p.campus_id = c.id AND p.archived_at IS NULL) AS people FROM {p}campuses c ORDER BY c.id');
        $funds = DB::all('SELECT * FROM {p}funds ORDER BY id');
        $langs = available_languages();
        $printers = [];
        $printerError = null;
        if ($tab === 'integrations' && Labels::printNodeKey() !== '' && !Demo::on()) {
            try {
                $printers = Labels::printers();
            } catch (Throwable $e) {
                $printerError = $e->getMessage();
            }
        }
        view('admin/settings', ['printers' => $printers, 'printerError' => $printerError, 'labelFont' => basename(Labels::font()), 'customFont' => !str_starts_with(Labels::font(), APP_DIR),'title' => __('Settings'), 'tab' => $tab, 'v' => $values, 'staff' => $staff, 'fields' => $fields, 'campuses' => $campuses, 'funds' => $funds, 'langs' => $langs, 'cronRan' => DB::val("SELECT ran_at FROM {p}jobs_lock WHERE name = 'cron'")]);
    }

    public function saveSettings(): void
    {
        $tab = (string)input('tab', 'general');
        if (Demo::isDemoUser(Auth::id())) Demo::block('/admin/settings?tab=' . rawurlencode($tab), __('Settings cannot be changed on the demo site.'));
        $keys = self::SETTINGS[$tab] ?? [];
        foreach ($keys as $k) {
            if (in_array($k, self::CHECKBOXES, true)) {
                set_setting($k, input_bool($k) ? '1' : '0');
                continue;
            }
            $v = (string)($_POST[$k] ?? '');
            $v = $k === 'privacy_policy_text' || $k === 'giving_dgr_statement' || $k === 'church_address' ? trim($v) : trim(str_replace(["\r", "\n"], '', $v));
            if (in_array($k, self::SECRETS, true)) {
                if ($v === '' || $v === '__set__') {
                    if (input_bool('clear_' . $k)) {
                        set_setting($k, '');
                    }
                    continue;
                }
                $v = (string)Crypto::encrypt($v);
            }
            if (in_array($k, ['session_timeout_minutes', 'roster_reminder_days', 'wwcc_reminder_days', 'retention_visitor_months', 'retention_archived_years', 'retention_audit_years', 'retention_messages_months', 'smtp_port', 'event_series_horizon'], true)) {
                $v = (string)max(0, (int)$v);
            }
            if ($k === 'push_content' && !in_array($v, ['title', 'none'], true)) {
                $v = 'title';
            }
            if ($k === 'event_series_horizon') {
                $v = (string)max(1, min(24, (int)$v ?: 12));
            }
            set_setting($k, mb_substr($v, 0, 20000));
        }
        if ($tab === 'cron' && input_bool('regenerate_cron_key')) {
            set_setting('cron_key', Crypto::token(16));
        }
        audit('settings.updated', null, null, ['tab' => $tab]);
        flash('success', __('Settings saved.'));
        redirect('/admin/settings', ['tab' => $tab]);
    }

    public function testEmail(): void
    {
        Demo::block('/admin/settings?tab=email', __('Email is switched off on the demo site.'));
        $to = (string)(user()['email'] ?? '');
        [$ok, $err] = Mailer::send($to, __('Affreso test email'), '<p>' . e(__('Your email settings are working.')) . '</p>');
        flash($ok ? 'success' : 'error', $ok ? __('Test email sent to %s.', $to) : __('Email failed: %s', (string)$err));
        redirect('/admin/settings', ['tab' => 'email']);
    }

    public function testSms(): void
    {
        Demo::block('/admin/settings?tab=sms', __('SMS is switched off on the demo site.'));
        $to = (string)input('to', '');
        [$ok, $err] = Sms::send($to, __('Affreso test message from %s', setting('church_name')));
        flash($ok ? 'success' : 'error', $ok ? __('Test SMS sent.') : __('SMS failed: %s', (string)$err));
        redirect('/admin/settings', ['tab' => 'sms']);
    }

    public function storeField(): void
    {
        $label = trim((string)input('label', ''));
        $type = in_array(input('field_type'), ['text', 'date', 'select', 'checkbox'], true) ? input('field_type') : 'text';
        if ($label !== '') {
            DB::insert('custom_fields', ['label' => mb_substr($label, 0, 100), 'field_type' => $type, 'options' => $type === 'select' ? mb_substr((string)input('options', ''), 0, 2000) : null, 'sort' => (int)input_int('sort', 0)]);
            audit('custom_field.created');
        }
        redirect('/admin/settings', ['tab' => 'fields']);
    }

    public function deleteField(string $id): void
    {
        DB::run('DELETE FROM {p}custom_field_values WHERE field_id = ?', [(int)$id]);
        DB::run('DELETE FROM {p}custom_fields WHERE id = ?', [(int)$id]);
        audit('custom_field.deleted', 'custom_field', (int)$id);
        redirect('/admin/settings', ['tab' => 'fields']);
    }

    public function storeCampus(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/admin/settings?tab=campuses');
        }
        if ($cid = input_int('campus_id')) {
            DB::update('campuses', ['name' => mb_substr((string)input('name', ''), 0, 120), 'address' => mb_substr((string)input('address', ''), 0, 255) ?: null, 'active' => input_bool('active')], 'id = ?', [$cid]);
        } elseif (($n = trim((string)input('name', ''))) !== '') {
            DB::insert('campuses', ['name' => mb_substr($n, 0, 120), 'address' => mb_substr((string)input('address', ''), 0, 255) ?: null]);
        }
        audit('campus.saved');
        redirect('/admin/settings', ['tab' => 'campuses']);
    }

    public function storeFund(): void
    {
        if ($fid = input_int('fund_id')) {
            DB::update('funds', ['name' => mb_substr((string)input('name', ''), 0, 120), 'active' => input_bool('active')], 'id = ?', [$fid]);
        } elseif (($n = trim((string)input('name', ''))) !== '') {
            DB::insert('funds', ['name' => mb_substr($n, 0, 120)]);
        }
        redirect('/admin/settings', ['tab' => 'fields']);
    }

    // Audit ----------------------------------------------------------------------
    public function audit(): void
    {
        $where = ['1=1'];
        $params = [];
        if ($a = (string)input('action', '')) {
            $where[] = 'l.action LIKE ?';
            $params[] = $a . '%';
        }
        if ($uid = input_int('user')) {
            $where[] = 'l.user_id = ?';
            $params[] = $uid;
        }
        if ($ent = (string)input('entity', '')) {
            $where[] = 'l.entity = ?';
            $params[] = $ent;
            if ($eid = input_int('entity_id')) {
                $where[] = 'l.entity_id = ?';
                $params[] = $eid;
            }
        }
        if ($from = input_date('from')) {
            $where[] = 'l.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $w = implode(' AND ', $where);
        if (input('export')) {
            csv_download('audit-log.csv', ['time', 'user', 'action', 'entity', 'entity_id', 'details', 'ip'], DB::all("SELECT l.created_at, u.email, l.action, l.entity, l.entity_id, l.details, l.ip FROM {p}audit_log l LEFT JOIN {p}users u ON u.id = l.user_id WHERE $w ORDER BY l.id DESC LIMIT 50000", $params));
        }
        $pg = paginate("SELECT COUNT(*) FROM {p}audit_log l WHERE $w", $params, 100);
        $rows = DB::all("SELECT l.*, u.email FROM {p}audit_log l LEFT JOIN {p}users u ON u.id = l.user_id WHERE $w ORDER BY l.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
        $users = array_column(DB::all('SELECT id, email FROM {p}users ORDER BY email'), 'email', 'id');
        view('admin/audit', ['title' => __('Audit log'), 'rows' => $rows, 'pg' => $pg, 'users' => $users]);
    }

    public function resetDemo(): void
    {
        if (!Demo::on() || Demo::isDemoUser(Auth::id())) {
            abort(403, __('Only the site owner can reset the demo.'));
        }
        Demo::reset();
        Auth::logout();
        flash('success', __('The demo has been reset to fresh sample data.'));
        redirect('/login');
    }

    public function mailchimpSync(): void
    {
        if (!Mailchimp::configured()) {
            flash('error', __('Add your Mailchimp API key and audience ID first.'));
            redirect('/admin/settings', ['tab' => 'integrations']);
        }
        try {
            $r = Mailchimp::sync();
            flash('success', __('Mailchimp updated: %d synced, %d unsubscribed, %d errors.', $r['updated'] ?? 0, $r['unsubscribed'] ?? 0, $r['errors'] ?? 0));
        } catch (Throwable $e) {
            flash('error', __('Mailchimp sync failed: %s', $e->getMessage()));
        }
        redirect('/admin/settings', ['tab' => 'integrations']);
    }

    public function runJobs(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/admin');
        }
        $log = Jobs::run((bool)input_bool('daily'));
        audit('jobs.run_manually', null, null, $log);
        flash('success', __('Scheduled tasks ran: %s', json_encode($log)));
        redirect('/admin');
    }

    /** Upload or remove the label font (for names in scripts DejaVu Sans doesn't cover, e.g. Chinese or Korean). */
    public function labelFont(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/admin/settings?tab=integrations', __('Settings cannot be changed on the demo site.'));
        }
        $dir = STORAGE_DIR . '/fonts';
        foreach (glob($dir . '/label-font.*') ?: [] as $old) {
            @unlink($old);
        }
        if (input('action') === 'remove') {
            audit('settings.label_font_removed');
            flash('success', __('Labels will use the built-in font.'));
            redirect('/admin/settings?tab=integrations#labels');
        }
        $f = $_FILES['font'] ?? null;
        $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !in_array($ext, ['ttf', 'otf', 'ttc'], true) || $f['size'] > 40 * 1024 * 1024) {
            flash('error', __('Choose a TrueType or OpenType font file (.ttf, .otf or .ttc) under 40 MB.'));
            redirect('/admin/settings?tab=integrations#labels');
        }
        $magic = (string)file_get_contents($f['tmp_name'], false, null, 0, 4);
        if (!in_array($magic, ["\x00\x01\x00\x00", 'OTTO', 'ttcf', 'true'], true)) {
            flash('error', __('That file does not look like a font.'));
            redirect('/admin/settings?tab=integrations#labels');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        move_uploaded_file($f['tmp_name'], $dir . '/label-font.' . $ext);
        audit('settings.label_font_uploaded');
        flash('success', __('Label font uploaded.'));
        redirect('/admin/settings?tab=integrations#labels');
    }

    /** Print a sample label to check the printer and font. */
    public function testLabel(): void
    {
        $printer = (string)setting('label_printer_id', '');
        $pdf = Labels::forCheckins([['security_code' => 'TEST', 'first_name' => (string)(user()['email'] ?? 'Test'), 'last_name' => '', 'preferred_name' => __('Test label'), 'class' => __('Test class'), 'session_date' => date('Y-m-d'), 'alert' => true, 'allergies' => __('This is how a care alert prints')]], true);
        if (input('download')) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="test-label.pdf"');
            echo $pdf;
            exit;
        }
        $err = Labels::send($printer, $pdf, __('Affreso test label'));
        flash($err ? 'error' : 'success', $err ?? __('Test label sent to the printer.'));
        redirect('/admin/settings?tab=integrations#labels');
    }
}
