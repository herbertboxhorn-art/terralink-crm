<?php
declare(strict_types=1);
/**
 * Two-step sign-in for staff (v1.16): time-based one-time codes (RFC 6238 TOTP, SHA-1, 6 digits, 30 s),
 * the standard used by Google/Microsoft Authenticator, 1Password, Authy etc.
 * Secrets are encrypted at rest (libsodium, key derived from CRM_API_KEY) so database backups don't expose them.
 * Policy (Settings → twofa_policy): optional | admins | all, plus users.totp_required per person.
 */

const TWOFA_TRUST_DAYS = 30;
const TWOFA_RECOVERY_CODES = 8;
const TWOFA_POLICIES = ['optional' => 'Optional for everyone', 'admins' => 'Required for admins', 'all' => 'Required for everyone'];

// ---------- base32 ----------
function b32_encode(string $bin): string {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function b32_decode(string $s): string {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

// ---------- TOTP ----------
function totp_code(string $secretB32, int $step): string {
    $h = hash_hmac('sha1', pack('J', $step), b32_decode($secretB32), true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Returns the matching time step (±1 step = ±30 s for phone clock drift), or null */
function totp_match(string $secretB32, string $code, ?int $now = null): ?int {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return null;
    $t = intdiv($now ?? time(), 30);
    foreach ([0, -1, 1] as $d) if (hash_equals(totp_code($secretB32, $t + $d), $code)) return $t + $d;
    return null;
}
function totp_new_secret(): string { return b32_encode(random_bytes(20)); }
function totp_uri(string $secret, string $email): string {
    $issuer = setting('company_name', 'TerraLink') . ' CRM';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

// ---------- secret storage ----------
function twofa_key(): string { return sodium_crypto_generichash('terralink-crm-2fa|' . (string)env('CRM_API_KEY', 'dev'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES); }
function twofa_seal(string $secret): string {
    if (!function_exists('sodium_crypto_secretbox')) return 'p:' . $secret;
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 's:' . base64_encode($n . sodium_crypto_secretbox($secret, $n, twofa_key()));
}
function twofa_open(?string $stored): ?string {
    if (!$stored) return null;
    if (str_starts_with($stored, 'p:')) return substr($stored, 2);
    $raw = base64_decode(substr($stored, 2), true);
    if ($raw === false || !function_exists('sodium_crypto_secretbox_open')) return null;
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), twofa_key());
    return $plain === false ? null : $plain;
}

// ---------- policy ----------
function twofa_enabled(array $u): bool { return !empty($u['totp_enabled_at']) && !empty($u['totp_secret']); }
function twofa_required(array $u): bool {
    $p = setting('twofa_policy', 'optional');
    return !empty($u['totp_required']) || $p === 'all' || ($p === 'admins' && ($u['role'] ?? '') === 'admin');
}
function twofa_must_setup(?array $u): bool { return $u && twofa_required($u) && !twofa_enabled($u); }

/**
 * Checks a code for a user: authenticator code (no replay of a used time step) or a one-time recovery code.
 * Returns 'totp', 'recovery' or null. Marks what was used.
 */
function twofa_verify(array $u, string $code): ?string {
    $secret = twofa_open($u['totp_secret'] ?? null);
    if ($secret && ($step = totp_match($secret, $code)) !== null) {
        if ($u['totp_last_step'] !== null && $step <= (int)$u['totp_last_step']) return null;   // same code can't be used twice
        q('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $u['id']]);
        return 'totp';
    }
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    if (strlen($c) === 10) {
        $list = json_decode((string)($u['totp_recovery'] ?? '[]'), true) ?: [];
        foreach ($list as $i => $hash) {
            if (password_verify($c, $hash)) {
                unset($list[$i]);
                q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode(array_values($list)), $u['id']]);
                return 'recovery';
            }
        }
    }
    return null;
}

/** New recovery codes (shown once). Stores hashes, returns the plain codes formatted XXXXX-XXXXX. */
function twofa_new_recovery(int $userId): array {
    $plain = []; $hashes = [];
    for ($i = 0; $i < TWOFA_RECOVERY_CODES; $i++) {
        $c = strtoupper(substr(b32_encode(random_bytes(8)), 0, 10));
        $plain[] = substr($c, 0, 5) . '-' . substr($c, 5);
        $hashes[] = password_hash($c, PASSWORD_DEFAULT);
    }
    q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode($hashes), $userId]);
    return $plain;
}
function twofa_recovery_left(array $u): int { return count(json_decode((string)($u['totp_recovery'] ?? '[]'), true) ?: []); }

// ---------- trusted devices ----------
function twofa_trust_cookie_name(): string { return 'tl2fa'; }
function twofa_trust_device(int $userId): void {
    $token = bin2hex(random_bytes(32));
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190);
    q('INSERT INTO trusted_devices (user_id, token_hash, label, expires_at) VALUES (?,?,?, NOW() + INTERVAL ' . TWOFA_TRUST_DAYS . ' DAY)', [$userId, hash('sha256', $token), $ua]);
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(twofa_trust_cookie_name(), $userId . ':' . $token, ['expires' => time() + TWOFA_TRUST_DAYS * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
}
function twofa_device_trusted(int $userId): bool {
    $c = (string)($_COOKIE[twofa_trust_cookie_name()] ?? '');
    if (!preg_match('/^(\d+):([0-9a-f]{64})$/', $c, $m) || (int)$m[1] !== $userId) return false;
    $id = val('SELECT id FROM trusted_devices WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()', [$userId, hash('sha256', $m[2])]);
    if ($id) q('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ?', [$id]);
    return (bool)$id;
}
function twofa_forget_devices(int $userId): void { q('DELETE FROM trusted_devices WHERE user_id = ?', [$userId]); }

/** Security notice to the user whenever 2FA changes on their account */
function twofa_notice(array $u, string $what): void {
    mail_send([['to' => $u['email'], 'subject' => 'Security: ' . $what . ' on your ' . setting('company_name', 'TerraLink') . ' CRM account',
        'html' => '<p>Hi ' . h(explode(' ', $u['name'])[0]) . ',</p><p><b>' . h($what) . '</b> on your CRM account (' . h($u['email']) . ') on ' . h(date('M j, Y \a\t g:i a')) . '.</p>'
            . '<p>If this wasn\'t you or your admin, tell ' . h(setting('company_name')) . ' right away.</p>']]);
}
