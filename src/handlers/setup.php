<?php
// One-time first-admin setup. Only works while the users table is empty AND the token matches.

function setup_token_ok(string $token): bool {
    $hash = setting('setup_token_hash');
    $exp = (int)setting('setup_token_expires', '0');
    return $hash !== '' && $token !== '' && time() < $exp && hash_equals($hash, hash('sha256', $token));
}

function h_setup(): void {
    if ((int)val('SELECT COUNT(*) FROM users') > 0) redirect('/login');
    $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
    if (!setup_token_ok($token)) {
        http_response_code(403);
        view('setup', ['title' => 'Setup', 'token' => '', 'invalid' => true, 'errors' => []], false);
        return;
    }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = (string)post('name', '');
        $email = strtolower((string)post('email', ''));
        $pw = (string)($_POST['password'] ?? '');
        if ($name === '') $errors['name'] = 'Required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Not a valid email';
        if (strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($pw !== (string)($_POST['confirm'] ?? '')) $errors['confirm'] = 'Does not match';
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            // Re-check inside the transaction so two tabs can't both create an admin
            if ((int)val('SELECT COUNT(*) FROM users FOR UPDATE') > 0) { $pdo->rollBack(); redirect('/login'); }
            q('INSERT INTO users (name, email, password_hash, role, active) VALUES (?,?,?,?,1)',
              [$name, $email, password_hash($pw, PASSWORD_DEFAULT), 'admin']);
            $id = (int)$pdo->lastInsertId();
            q("DELETE FROM settings WHERE k IN ('setup_token_hash','setup_token_expires')");
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            flash('Welcome! Your admin account is ready. Next: fill in Settings (travel rate, GST #).');
            redirect('/settings');
        }
    }
    view('setup', ['title' => 'Setup', 'token' => $token, 'invalid' => false, 'errors' => $errors], false);
}
