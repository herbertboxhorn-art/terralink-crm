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
        if (!$id && strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($id && $pw !== '' && strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($id === uid() && (!$vals['active'] || $vals['role'] !== 'admin')) $errors['role'] = 'You cannot demote or deactivate yourself';
        if (!$errors) {
            if ($pw !== '') $vals['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $cols = array_keys($vals);
            if ($id) {
                q('UPDATE users SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($vals), $id]);
            } else {
                q('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($vals));
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
             'travel_rate_per_km', 'quote_valid_days', 'quote_terms'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($keys as $k) {
            q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)post($k, '')]);
        }
        flash('Settings saved.');
        redirect('/settings');
    }
    $s = array_column(all('SELECT k, v FROM settings'), 'v', 'k');
    view('settings', ['title' => 'Settings', 's' => $s, 'apiKey' => (string)env('CRM_API_KEY', ''), 'apiUrl' => rtrim((string)env('APP_URL', ''), '/') . '/api/leads']);
}
