<?php
declare(strict_types=1);

final class AccountController
{
    public function index(): void
    {
        $u = user();
        $roles = DB::col('SELECT r.name FROM {p}roles r JOIN {p}user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$u['id']]);
        if (!$u['ical_token']) {
            DB::update('users', ['ical_token' => Crypto::token(20)], 'id = ?', [$u['id']]);
            $u['ical_token'] = DB::val('SELECT ical_token FROM {p}users WHERE id = ?', [$u['id']]);
        }
        $logins = DB::all("SELECT action, ip, created_at FROM {p}audit_log WHERE user_id = ? AND action LIKE 'login%' ORDER BY id DESC LIMIT 8", [$u['id']]);
        $calAccounts = DB::all('SELECT * FROM {p}calendar_accounts WHERE user_id = ? ORDER BY provider', [$u['id']]);
        view('account/index', ['title' => __('Account & security'), 'u' => $u, 'roles' => $roles, 'logins' => $logins, 'calAccounts' => $calAccounts]);
    }

    public function password(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/account', __('Demo accounts are shared, so their password and two-step settings cannot be changed.'));
        }
        $u = user();
        if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
            flash('error', __('Your current password was not correct.'));
            redirect('/account');
        }
        $pw = (string)($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            flash('error', $err);
            redirect('/account');
        }
        if ($pw !== ($_POST['password2'] ?? '')) {
            flash('error', __('The passwords do not match.'));
            redirect('/account');
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        DB::update('users', ['password_hash' => $hash, 'password_changed_at' => date('Y-m-d H:i:s')], 'id = ?', [$u['id']]);
        $_SESSION['pwv'] = crc32($hash);
        audit('password.changed', 'user', (int)$u['id']);
        flash('success', __('Your password has been changed.'));
        redirect('/account');
    }

    public function mfa(): void
    {
        $u = user();
        if (!(int)$u['mfa_enabled']) {
            if (empty($_SESSION['mfa_setup_secret'])) {
                $_SESSION['mfa_setup_secret'] = Totp::newSecret();
            }
            $secret = $_SESSION['mfa_setup_secret'];
            $uri = Totp::uri($secret, $u['email'], (string)setting('church_name', 'Affreso'));
        }
        view('account/mfa', ['title' => __('Two-step verification'), 'u' => $u, 'secret' => $secret ?? null, 'uri' => $uri ?? null, 'required' => Auth::needsMfa(), 'codes' => $_SESSION['mfa_new_codes'] ?? null]);
        unset($_SESSION['mfa_new_codes']);
    }

    public function mfaEnable(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/account', __('Demo accounts are shared, so their password and two-step settings cannot be changed.'));
        }
        $u = user();
        $secret = $_SESSION['mfa_setup_secret'] ?? '';
        if (!$secret || !Totp::verify($secret, (string)input('code', ''))) {
            flash('error', __('That code did not match. Make sure you scanned the QR code, then enter the current 6-digit code.'));
            redirect('/account/mfa');
        }
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($c, 0, 4) . '-' . substr($c, 4);
            $hashes[] = password_hash($c, PASSWORD_DEFAULT);
        }
        DB::update('users', ['mfa_secret' => Crypto::encrypt($secret), 'mfa_enabled' => 1, 'mfa_recovery' => json_encode($hashes)], 'id = ?', [$u['id']]);
        unset($_SESSION['mfa_setup_secret']);
        $_SESSION['mfa_new_codes'] = $codes;
        audit('mfa.enabled', 'user', (int)$u['id']);
        flash('success', __('Two-step verification is on. Save your recovery codes now — they are shown only once.'));
        redirect('/account/mfa');
    }

    public function mfaDisable(): void
    {
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/account', __('Demo accounts are shared, so their password and two-step settings cannot be changed.'));
        }
        $u = user();
        if (Auth::needsMfaIfDisabled()) {
            flash('error', __('Your role requires two-step verification, so it cannot be turned off.'));
            redirect('/account/mfa');
        }
        if (!password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            flash('error', __('Your password was not correct.'));
            redirect('/account/mfa');
        }
        DB::update('users', ['mfa_secret' => null, 'mfa_enabled' => 0, 'mfa_recovery' => null], 'id = ?', [$u['id']]);
        audit('mfa.disabled', 'user', (int)$u['id']);
        flash('success', __('Two-step verification has been turned off.'));
        redirect('/account/mfa');
    }

    public function icalReset(): void
    {
        DB::update('users', ['ical_token' => Crypto::token(20)], 'id = ?', [Auth::id()]);
        flash('success', __('Your calendar link has been replaced. Re-subscribe using the new link.'));
        redirect('/account');
    }

    public function language(): void
    {
        $lang = (string)input('language', '');
        DB::update('users', ['language' => isset(available_languages()[$lang]) ? $lang : null], 'id = ?', [Auth::id()]);
        redirect('/account');
    }

    public function switchCampus(): void
    {
        $c = input_int('campus', 0);
        $_SESSION['campus'] = isset(Campus::all()[$c]) ? $c : 0;
        back('/');
    }

    public function notifications(): void
    {
        $rows = DB::all('SELECT * FROM {p}notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [Auth::id()]);
        view('account/notifications', ['title' => __('Notifications'), 'rows' => $rows]);
    }

    public function markRead(): void
    {
        DB::run('UPDATE {p}notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [Auth::id()]);
        redirect('/notifications');
    }

    // ------------------------------------------------------------------ calendar sync

    public function calendarConnect(string $provider): void
    {
        if (!isset(CalendarSync::PROVIDERS[$provider]) || !CalendarSync::configured($provider)) {
            abort(404);
        }
        if (Demo::isDemoUser(Auth::id())) {
            Demo::block('/account', __('Calendar connections are switched off on the demo site.'));
        }
        if (!user()['person_id']) {
            flash('error', __('Your account is not linked to a person record, so there is nothing to sync yet.'));
            redirect('/account');
        }
        redirect(CalendarSync::authUrl($provider));
    }

    public function calendarCallback(): void
    {
        $res = CalendarSync::callback((int)Auth::id(), $_GET);
        flash($res[0] ? 'success' : 'error', $res[1]);
        if ($res[0]) {
            $r = CalendarSync::sync((int)$res[2]);
            if (!empty($r['error'])) {
                flash('warning', __('Connected, but the first sync had a problem: %s', $r['error']));
            } else {
                flash('success', __('Synced: %d added to your calendar, %d busy times read.', $r['created'], $r['busy']));
            }
        }
        redirect('/account#calendar-sync');
    }

    private function myAccount(string $id): array
    {
        return DB::row('SELECT * FROM {p}calendar_accounts WHERE id = ? AND user_id = ?', [(int)$id, Auth::id()]) ?? abort(404);
    }

    public function calendarSave(string $id): void
    {
        $a = $this->myAccount($id);
        DB::update('calendar_accounts', ['push_enabled' => input_bool('push_enabled'), 'busy_enabled' => input_bool('busy_enabled'), 'last_sync_at' => null], 'id = ?', [$a['id']]);
        $r = CalendarSync::sync((int)$a['id']);
        flash(empty($r['error']) ? 'success' : 'warning', empty($r['error']) ? __('Calendar settings saved.') : __('Saved, but sync had a problem: %s', $r['error']));
        redirect('/account#calendar-sync');
    }

    public function calendarSyncNow(string $id): void
    {
        $a = $this->myAccount($id);
        $r = CalendarSync::sync((int)$a['id']);
        if (!empty($r['error'])) {
            flash('error', __('Sync failed: %s', $r['error']));
        } else {
            flash('success', __('Synced: %d added, %d updated, %d removed, %d busy times read.', $r['created'], $r['updated'], $r['removed'], $r['busy']));
        }
        redirect('/account#calendar-sync');
    }

    public function calendarDisconnect(string $id): void
    {
        $a = $this->myAccount($id);
        CalendarSync::disconnect((int)$a['id'], input_bool('remove_events'));
        flash('success', __('Calendar disconnected.'));
        redirect('/account#calendar-sync');
    }
}
