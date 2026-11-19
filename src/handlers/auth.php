<?php

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function h_login(): void {
    if (user()) redirect('/');
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $recent = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]);
        $email = strtolower((string)post('email', ''));
        if ($recent >= 8) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
            if ($u && password_verify((string)post('password', ''), $u['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
                q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
                $to = $_SESSION['after_login'] ?? '/';
                unset($_SESSION['after_login']);
                redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/');
            }
            q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, $email]);
            $error = 'Email or password is wrong.';
        }
    }
    if (!$error && (int)val('SELECT COUNT(*) FROM users') === 0) $error = 'No accounts yet. Use the one-time setup link to create the first admin.';
    view('login', ['title' => 'Sign in', 'error' => $error], false);
}

function h_logout(): void {
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

// ---------- users (admin) ----------
function h_users(): void {
    require_role('admin');
    view('users', ['title' => 'Users', 'users' => all('SELECT * FROM users ORDER BY active DESC, name')]);
}

function h_user_form(int $id = 0): void {
    require_role('admin');
    $u = $id ? one('SELECT * FROM users WHERE id = ?', [$id]) : ['role' => 'tech', 'active' => 1];
    if ($id && !$u) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $vals = [
            'name'   => (string)post('name', ''),
            'email'  => strtolower((string)post('email', '')),
            'phone'  => str_or_null(post('phone')),
            'role'   => (string)post('role', 'tech'),
            'active' => post('active') ? 1 : 0,
        ];
        $pw = (string)($_POST['password'] ?? '');
        if ($vals['name'] === '') $errors['name'] = 'Required';
        if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Not a valid email';
        elseif (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$vals['email'], $id])) $errors['email'] = 'Already in use';
        if (!isset(ROLES[$vals['role']])) $errors['role'] = 'Invalid';
        $invite = !$id && post('invite');
        if (!$id && !$invite && strlen($pw) < 10) $errors['password'] = 'At least 10 characters (or tick "Email an invite")';
        if ($invite && $pw === '') $pw = bin2hex(random_bytes(24));   // unusable until they set their own
        if ($id && $pw !== '' && strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($id === uid() && (!$vals['active'] || $vals['role'] !== 'admin')) $errors['role'] = 'You cannot demote or deactivate yourself';
        if (!$errors) {
            if ($pw !== '') $vals['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $cols = array_keys($vals);
            if ($id) {
                q('UPDATE users SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($vals), $id]);
            } else {
                q('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($vals));
                if ($invite) {
                    $nu = one('SELECT * FROM users WHERE id = ?', [(int)db()->lastInsertId()]);
                    flash(send_invite($nu) ? "User created and invite emailed to {$nu['email']}." : 'User created, but the invite email failed. Check Settings → Recent emails.', 'warn');
                    redirect('/users');
                }
            }
            flash($id ? 'User updated.' : 'User created.');
            redirect('/users');
        }
        $u = array_merge($u, $vals);
    }
    view('user_form', ['title' => $id ? 'Edit user' : 'New user', 'u' => $u, 'id' => $id, 'errors' => $errors]);
}

function h_account(): void {
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('prefs')) {
        q('UPDATE users SET daily_email = ? WHERE id = ?', [post('daily_email') ? 1 : 0, uid()]);
        flash(post('daily_email') ? 'You\'ll get a summary email each morning.' : 'Morning summary email turned off.');
        redirect('/account');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, user()['password_hash'])) $errors['current'] = 'Current password is wrong';
        if (strlen($new) < 10) $errors['new'] = 'At least 10 characters';
        if ($new !== (string)($_POST['confirm'] ?? '')) $errors['confirm'] = 'Does not match';
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), uid()]);
            flash('Password changed.');
            redirect('/account');
        }
    }
    view('account', ['title' => 'My account', 'errors' => $errors]);
}

function h_settings(): void {
    require_role('admin');
    $keys = ['company_name', 'company_phone', 'company_email', 'company_address', 'gst_number',
             'travel_rate_per_km', 'quote_valid_days', 'quote_terms', 'stock_locations', 'mail_webhook_url', 'sla_urgent', 'sla_high', 'sla_normal', 'sla_low'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (post('test_email')) {
            $ok = mail_send([['to' => user()['email'], 'subject' => 'CRM test email', 'html' => '<p>It works! The CRM can send email through n8n.</p>']]);
            flash($ok ? 'Test email sent to ' . user()['email'] . '.' : 'Test email failed. See Recent emails below.', $ok ? 'ok' : 'bad');
            redirect('/settings#email');
        }
        foreach (['sla_urgent', 'sla_high', 'sla_normal', 'sla_low'] as $k) if (isset($_POST[$k])) $_POST[$k] = (string)max(1, (int)$_POST[$k]);
        foreach ($keys as $k) {
            if (!array_key_exists($k, $_POST)) continue;   // only change what the form sent
            q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)post($k, '')]);
        }
        flash('Settings saved.');
        redirect('/settings');
    }
    $s = array_column(all('SELECT k, v FROM settings'), 'v', 'k');
    view('settings', ['title' => 'Settings', 's' => $s, 'mails' => all('SELECT * FROM mail_log ORDER BY id DESC LIMIT 15'), 'apiKey' => (string)env('CRM_API_KEY', ''), 'apiUrl' => rtrim((string)env('APP_URL', ''), '/') . '/api/leads']);
}

// ---------- password reset & invites ----------
/** Creates a one-time link for a user. kind=reset (1 h) or invite (72 h). Returns the raw token. */
function make_reset_token(int $userId, string $kind): string {
    $token = bin2hex(random_bytes(32));
    q('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);   // only the newest link works
    q('INSERT INTO password_resets (user_id, token_hash, kind, expires_at) VALUES (?,?,?, NOW() + INTERVAL ' . ($kind === 'invite' ? '72' : '1') . ' HOUR)',
      [$userId, hash('sha256', $token), $kind]);
    return $token;
}

function send_invite(array $u): bool {
    $link = app_url('/reset?token=' . make_reset_token((int)$u['id'], 'invite'));
    $by = user()['name'] ?? setting('company_name');
    return mail_send([[
        'to' => $u['email'], 'subject' => 'You\'ve been invited to the ' . setting('company_name', 'TerraLink') . ' CRM',
        'html' => '<p>Hi ' . h(explode(' ', $u['name'])[0]) . ',</p><p>' . h($by) . ' added you to the ' . h(setting('company_name')) . ' CRM as <b>' . h(ROLES[$u['role']]) . '</b>.</p>'
            . '<p>Choose your password to get started. Your login is <b>' . h($u['email']) . '</b>.</p>' . mail_button($link, 'Set my password')
            . '<p style="color:#7a8a97;font-size:13px">This link works once and expires in 72 hours.</p>',
    ]]);
}

function h_forgot(): void {
    if (user()) redirect('/');
    $sent = false; $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $email = strtolower((string)post('email', ''));
        if ((int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]) >= 8) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, 'reset:' . $email]);
            $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
            if ($u) {
                $link = app_url('/reset?token=' . make_reset_token((int)$u['id'], 'reset'));
                mail_send([[
                    'to' => $u['email'], 'subject' => 'Reset your ' . setting('company_name', 'TerraLink') . ' CRM password',
                    'html' => '<p>Hi ' . h(explode(' ', $u['name'])[0]) . ',</p><p>Someone (hopefully you) asked to reset your CRM password.</p>'
                        . mail_button($link, 'Choose a new password')
                        . '<p style="color:#7a8a97;font-size:13px">This link works once and expires in 1 hour. If you didn\'t ask for this, ignore this email; your password stays the same.</p>',
                ]]);
            }
            $sent = true;   // same answer whether or not the account exists
        }
    }
    view('forgot', ['title' => 'Forgot password', 'sent' => $sent, 'error' => $error], false);
}

function h_reset(): void {
    $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
    $row = $token !== '' ? one('SELECT r.*, u.name, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
                                 WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.active = 1', [hash('sha256', $token)]) : null;
    $errors = [];
    if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($pw !== (string)($_POST['confirm'] ?? '')) $errors['confirm'] = 'Does not match';
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
            q('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['user_id'];
            q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$row['user_id']]);
            flash($row['kind'] === 'invite' ? 'Welcome aboard! Your password is set.' : 'Password changed. You\'re signed in.');
            redirect('/');
        }
    }
    view('reset', ['title' => 'Set password', 'row' => $row, 'token' => $token, 'errors' => $errors], false);
}

function h_user_invite(int $id): void {
    require_role('admin');
    $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
    if (!$u) not_found();
    flash(send_invite($u) ? "Invite emailed to {$u['email']}." : 'The invite could not be sent. Check Settings → Recent emails.', 'warn');
    redirect('/users');
}
