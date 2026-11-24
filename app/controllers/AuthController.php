<?php
declare(strict_types=1);

final class AuthController
{
    public function loginForm(): void
    {
        if (user()) {
            redirect('/');
        }
        view('auth/login', ['title' => __('Sign in')], 'layout_auth');
    }

    public function login(): void
    {
        $email = (string)input('email', '');
        [$status, $u] = Auth::attempt($email, (string)($_POST['password'] ?? ''));
        if ($status === 'throttled') {
            flash('error', __('Too many sign-in attempts. Please wait 15 minutes and try again.'));
            redirect('/login');
        }
        if ($status !== 'ok') {
            flash('error', __('That email and password combination was not recognised.'));
            redirect('/login');
        }
        $intended = $_SESSION['_intended'] ?? '/';
        if ((int)$u['mfa_enabled'] === 1) {
            session_regenerate_id(true);
            $_SESSION['mfa_uid'] = (int)$u['id'];
            $_SESSION['mfa_at'] = time();
            $_SESSION['_intended'] = $intended;
            redirect('/login/mfa');
        }
        Auth::login($u);
        redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/');
    }

    public function mfaForm(): void
    {
        if (empty($_SESSION['mfa_uid']) || time() - (int)$_SESSION['mfa_at'] > 300) {
            redirect('/login');
        }
        view('auth/mfa', ['title' => __('Two-step verification')], 'layout_auth');
    }

    public function mfaVerify(): void
    {
        $uid = (int)($_SESSION['mfa_uid'] ?? 0);
        if (!$uid || time() - (int)$_SESSION['mfa_at'] > 300) {
            flash('error', __('Your sign-in took too long. Please start again.'));
            redirect('/login');
        }
        $key = 'mfa:' . $uid;
        if (Auth::throttled($key)) {
            flash('error', __('Too many attempts. Please wait 15 minutes.'));
            redirect('/login');
        }
        $u = DB::row('SELECT * FROM {p}users WHERE id = ?', [$uid]);
        $code = (string)input('code', '');
        $ok = $u && Totp::verify((string)Crypto::decrypt($u['mfa_secret']), $code);
        if (!$ok && $u && $u['mfa_recovery']) {
            $codes = json_decode((string)$u['mfa_recovery'], true) ?: [];
            $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
            foreach ($codes as $i => $h) {
                if (password_verify($norm, $h)) {
                    unset($codes[$i]);
                    DB::update('users', ['mfa_recovery' => json_encode(array_values($codes))], 'id = ?', [$uid]);
                    audit('mfa.recovery_code_used', 'user', $uid);
                    $ok = true;
                    break;
                }
            }
        }
        Auth::recordAttempt($key, $ok);
        if (!$ok) {
            flash('error', __('That code was not correct. Check the time on your phone is set automatically, then try again.'));
            redirect('/login/mfa');
        }
        $intended = $_SESSION['_intended'] ?? '/';
        Auth::login($u);
        redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/');
    }

    public function demoLogin(): void
    {
        if (!Demo::login((string)input('role', ''))) {
            abort(404);
        }
        redirect('/');
    }

    public function logout(): void
    {
        if (user()) {
            audit('logout', 'user', Auth::id());
        }
        Auth::logout();
        flash('success', __('You have been signed out.'));
        redirect('/login');
    }

    public function forgotForm(): void
    {
        view('auth/forgot', ['title' => __('Reset your password')], 'layout_auth');
    }

    public function forgot(): void
    {
        $email = mb_strtolower((string)input('email', ''));
        if (!Auth::throttled('reset:' . $email)) {
            Auth::recordAttempt('reset:' . $email, false);
            $u = DB::row("SELECT * FROM {p}users WHERE email = ? AND status = 'active'", [$email]);
            if ($u) {
                $link = Auth::resetLink((int)$u['id'], 2);
                Notify::emailAddress($u['email'], __('Reset your %s password', setting('church_name')), '<p>' . e(__('Someone asked to reset the password for your account. If it was you, use the link below within 2 hours.')) . '</p><p><a href="' . e($link) . '">' . e(__('Choose a new password')) . '</a></p><p>' . e(__('If you did not ask for this, you can ignore this email.')) . '</p>');
                Notify::processQueue(5);
                audit('password.reset_requested', 'user', (int)$u['id']);
            }
        }
        flash('success', __('If that email belongs to an account, we have sent a link to reset the password.'));
        redirect('/login');
    }

    private function tokenRow(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::row('SELECT * FROM {p}password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)]);
    }

    public function resetForm(): void
    {
        $token = (string)input('token', '');
        if (!$this->tokenRow($token)) {
            flash('error', __('That link has expired or has already been used. Please request a new one.'));
            redirect('/password/forgot');
        }
        view('auth/reset', ['title' => __('Choose a new password'), 'token' => $token], 'layout_auth');
    }

    public function reset(): void
    {
        $token = (string)input('token', '');
        $row = $this->tokenRow($token);
        if (!$row) {
            flash('error', __('That link has expired or has already been used.'));
            redirect('/password/forgot');
        }
        $pw = (string)($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            flash('error', $err);
            redirect('/password/reset', ['token' => $token]);
        }
        if ($pw !== ($_POST['password2'] ?? '')) {
            flash('error', __('The passwords do not match.'));
            redirect('/password/reset', ['token' => $token]);
        }
        DB::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'status' => 'active', 'password_changed_at' => date('Y-m-d H:i:s'), 'locked_until' => null], 'id = ?', [$row['user_id']]);
        DB::run('UPDATE {p}password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
        audit('password.reset', 'user', (int)$row['user_id']);
        flash('success', __('Your password has been set. Please sign in.'));
        redirect('/login');
    }
}
