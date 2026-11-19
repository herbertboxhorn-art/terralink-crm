<?php
// Runs on every container start (after migrate). While no user exists, prints a
// one-time setup link (valid 48 h) to the container log. Once an admin exists it does nothing.
require __DIR__ . '/../src/bootstrap.php';
if ((int)val('SELECT COUNT(*) FROM users') > 0) {
    q("DELETE FROM settings WHERE k IN ('setup_token_hash','setup_token_expires')");
    exit(0);
}
$token = bin2hex(random_bytes(24));
q("INSERT INTO settings (k, v) VALUES ('setup_token_hash', ?), ('setup_token_expires', ?)
   ON DUPLICATE KEY UPDATE v = VALUES(v)", [hash('sha256', $token), (string)(time() + 48 * 3600)]);
$base = rtrim(env('APP_URL', 'http://localhost'), '/');
echo "==================================================================\n";
echo " TerraLink CRM first-run setup (one-time link, valid 48 hours):\n";
echo " $base/setup?token=$token\n";
echo "==================================================================\n";
