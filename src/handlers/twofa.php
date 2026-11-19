<?php
// Two-step sign-in screens (v1.16): the code step at login, the setup page under My account, admin reset.

/** GET|POST /login/2fa — second step after the password */
function h_login_2fa(): void {
    $p = $_SESSION['pending_2fa'] ?? null;
    if (!$p || time() - (int)$p['at'] > 600) { unset($_SESSION['pending_2fa']); flash('Sign in again.', 'warn'); redirect('/login'); }
    $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [(int)$p['uid']]);
    if (!$u || !twofa_enabled($u)) { unset($_SESSION['pending_2fa']); redirect('/login'); }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $tries = (int)val("SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR email = ?) AND email LIKE '2fa:%' AND created_at > NOW() - INTERVAL 15 MINUTE", [$ip, '2fa:' . $u['id']]);
        if ($tries >= 6) {
            $error = 'Too many wrong codes. Wait 15 minutes and sign in again.';
            unset($_SESSION['pending_2fa']);
        } elseif ($how = twofa_verify($u, (string)post('code', ''))) {
            q("DELETE FROM login_attempts WHERE email = ?", ['2fa:' . $u['id']]);
            if (post('trust')) twofa_trust_device((int)$u['id']);
            if ($how === 'recovery') {
                $left = twofa_recovery_left(one('SELECT totp_recovery FROM users WHERE id = ?', [$u['id']]));
                flash("You used a recovery code ($left left). If you lost your phone, set up two-step sign-in again under My account → Two-step sign-in.", 'warn');
                twofa_notice($u, 'A recovery code was used to sign in');
            }
            login_finish($u, (string)$p['to']);
        } else {
            q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, '2fa:' . $u['id']]);
            $error = 'That code didn\'t work. Check the time on your phone and try the newest code.';
        }
    }
    view('login_2fa', ['title' => 'Sign-in code', 'error' => $error, 'u' => $u], false);
}

/** GET|POST /account/2fa — turn on, show recovery codes, new codes, forget devices, turn off */
function h_account_2fa(): void {
    $u = one('SELECT * FROM users WHERE id = ?', [uid()]);
    $err = null; $codes = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = (string)post('action');
        if ($act === 'enable' && !twofa_enabled($u)) {
            $secret = (string)($_SESSION['totp_setup'] ?? '');
            $step = $secret ? totp_match($secret, (string)post('code')) : null;
            if ($step === null) {
                $err = 'That code didn\'t match. Scan the QR code again, then type the 6-digit code your app shows now.';
            } else {
                q('UPDATE users SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_step = ? WHERE id = ?', [twofa_seal($secret), $step, $u['id']]);
                unset($_SESSION['totp_setup']);
                $codes = twofa_new_recovery((int)$u['id']);
                if (post('trust')) twofa_trust_device((int)$u['id']);
                twofa_notice($u, 'Two-step sign-in was turned on');
                $u = one('SELECT * FROM users WHERE id = ?', [uid()]);
            }
        } elseif (in_array($act, ['recovery', 'disable'], true) && twofa_enabled($u)) {
            if (!twofa_verify($u, (string)post('code'))) {
                $err = 'Enter a current code from your authenticator app (or a recovery code) to confirm.';
            } elseif ($act === 'recovery') {
                $codes = twofa_new_recovery((int)$u['id']);
                $u = one('SELECT * FROM users WHERE id = ?', [uid()]);
            } elseif (twofa_required($u)) {
                $err = 'Two-step sign-in is required for your account, so it can\'t be turned off. Lost your phone? Ask an admin to reset it.';
            } else {
                q('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL, totp_recovery = NULL WHERE id = ?', [$u['id']]);
                twofa_forget_devices((int)$u['id']);
                twofa_notice($u, 'Two-step sign-in was turned off');
                flash('Two-step sign-in is off.');
                redirect('/account/2fa');
            }
        } elseif ($act === 'forget') {
            twofa_forget_devices((int)$u['id']);
            setcookie(twofa_trust_cookie_name(), '', ['expires' => 1, 'path' => '/']);
            flash('Trusted devices forgotten. Every device will ask for a code next time.');
            redirect('/account/2fa');
        }
    }
    if (!twofa_enabled($u)) $_SESSION['totp_setup'] ??= totp_new_secret();
    view('account_2fa', ['title' => 'Two-step sign-in', 'u' => $u, 'err' => $err, 'codes' => $codes,
        'secret' => $_SESSION['totp_setup'] ?? null,
        'devices' => all('SELECT * FROM trusted_devices WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC', [$u['id']])]);
}

/** POST /users/{id}/2fa-reset — admin: lost phone, start over */
function h_user_2fa_reset(int $id): void {
    require_role('admin');
    $u = one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) not_found();
    q('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL, totp_recovery = NULL WHERE id = ?', [$id]);
    twofa_forget_devices($id);
    if ($id !== uid()) twofa_notice($u, 'Two-step sign-in was reset by ' . user()['name']);
    flash("Two-step sign-in reset for {$u['name']}." . (twofa_required($u) ? ' They\'ll set it up again at their next sign-in.' : ''));
    redirect("/users/$id/edit");
}
