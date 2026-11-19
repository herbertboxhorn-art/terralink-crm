<?php
// Usage: php bin/create-admin.php "Herb Boxhorn" herb@terralinksystems.com
// Prompts for the password so it never lands in shell history.
require __DIR__ . '/../src/bootstrap.php';
[$_, $name, $email] = $argv + [null, null, null];
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Usage: php bin/create-admin.php \"Full Name\" email@example.com\n"); exit(1); }
$pw = getenv('ADMIN_PASSWORD') ?: null;
if (!$pw) {
    echo 'Password (min 10 chars): ';
    if (stream_isatty(STDIN)) shell_exec('stty -echo');
    $pw = trim((string)fgets(STDIN));
    if (stream_isatty(STDIN)) { shell_exec('stty echo'); echo PHP_EOL; }
}
if (strlen($pw) < 10) { fwrite(STDERR, "Password too short.\n"); exit(1); }
q('INSERT INTO users (name, email, password_hash, role, active) VALUES (?,?,?,?,1)
   ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = "admin", active = 1',
  [$name, strtolower($email), password_hash($pw, PASSWORD_DEFAULT), 'admin']);
echo "Admin ready: $email\n";
