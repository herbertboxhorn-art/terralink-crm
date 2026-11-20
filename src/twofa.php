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
const PORTAL_TWOFA_POLICIES = ['optional' => 'Optional for every client login', 'money' => 'Required for logins with Invoices or Manage users', 'all' => 'Required for every client login'];

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
function totp_uri(string $secret, string $email, string $app = 'CRM'): string {
    $issuer = setting('company_name', 'TerraLink') . ' ' . $app;
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

// ---------- secret storage ----------
/**
 * Encryption key for stored 2FA secrets (v1.17). Independent of the API key, which lives in n8n:
 * TWOFA_KEY in crm-config.php if set, else a random key file next to crm-config.php (created on first use,
 * outside the web root and not in database backups). Secrets sealed with it start with "k:".
 */
function twofa_key_file(): string { return (defined('CONFIG_DIR') ? CONFIG_DIR : APP_ROOT . '/storage') . '/crm-2fa.key'; }
function twofa_key(): ?string {
    static $k = false;
    if ($k !== false) return $k;
    try {
        if ($env = env('TWOFA_KEY')) return $k = sodium_crypto_generichash('terralink-crm-2fa-v2|' . $env, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $f = twofa_key_file();
        if (!is_file($f)) {
            if (!is_dir(dirname($f))) @mkdir(dirname($f), 0750, true);
            // 'x' = create only if missing (atomic; another request may win the race). No link(): often disabled on shared hosting.
            $h = @fopen($f, 'x');
            if ($h) { fwrite($h, bin2hex(random_bytes(32))); fclose($h); @chmod($f, 0600); }
        }
        $raw = is_readable($f) ? trim((string)file_get_contents($f)) : '';
        if (!preg_match('/^[0-9a-f]{64}$/', $raw)) return $k = null;   // can't create/read it: fall back to the legacy key
        return $k = sodium_crypto_generichash('terralink-crm-2fa-v2|' . $raw, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    } catch (\Throwable $e) {
        app_error_record('2fa key: ' . $e->getMessage(), $e->getFile(), $e->getLine());
        return $k = null;
    }
}
/** Pre-1.17 key (derived from CRM_API_KEY). Only used to read old "s:" secrets until the migration re-seals them. */
function twofa_key_legacy(): string { return sodium_crypto_generichash('terralink-crm-2fa|' . (string)env('CRM_API_KEY', 'dev'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES); }
function twofa_seal(string $secret): string {
    if (!function_exists('sodium_crypto_secretbox')) return 'p:' . $secret;
    $key = twofa_key();
    [$prefix, $key] = $key ? ['k:', $key] : ['s:', twofa_key_legacy()];
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $prefix . base64_encode($n . sodium_crypto_secretbox($secret, $n, $key));
}
function twofa_open(?string $stored): ?string {
    if (!$stored) return null;
    if (str_starts_with($stored, 'p:')) return substr($stored, 2);
    $key = str_starts_with($stored, 'k:') ? twofa_key() : twofa_key_legacy();
    $raw = base64_decode(substr($stored, 2), true);
    if (!$key || $raw === false || !function_exists('sodium_crypto_secretbox_open')) return null;
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    return $plain === false ? null : $plain;
}
/** Re-seals old "s:"/"p:" secrets with the new key (migration 1.17.0; safe to repeat) */
function twofa_reseal_all(): int {
    if (!twofa_key()) return 0;
    $n = 0;
    foreach (['users', 'portal_users'] as $t) {
        if (!col_exists($t, 'totp_secret')) continue;
        foreach (all("SELECT id, totp_secret FROM `$t` WHERE totp_secret IS NOT NULL AND totp_secret NOT LIKE 'k:%'") as $r) {
            $plain = twofa_open($r['totp_secret']);
            if ($plain === null) continue;
            q("UPDATE `$t` SET totp_secret = ? WHERE id = ?", [twofa_seal($plain), $r['id']]);
            $n++;
        }
    }
    return $n;
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
function twofa_verify(array $u, string $code, string $table = 'users'): ?string {
    $table = $table === 'portal_users' ? 'portal_users' : 'users';
    $secret = twofa_open($u['totp_secret'] ?? null);
    if ($secret && ($step = totp_match($secret, $code)) !== null) {
        if ($u['totp_last_step'] !== null && $step <= (int)$u['totp_last_step']) return null;   // same code can't be used twice
        q("UPDATE `$table` SET totp_last_step = ? WHERE id = ?", [$step, $u['id']]);
        return 'totp';
    }
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    if (strlen($c) === 10) {
        $list = json_decode((string)($u['totp_recovery'] ?? '[]'), true) ?: [];
        foreach ($list as $i => $hash) {
            if (password_verify($c, $hash)) {
                unset($list[$i]);
                q("UPDATE `$table` SET totp_recovery = ? WHERE id = ?", [json_encode(array_values($list)), $u['id']]);
                return 'recovery';
            }
        }
    }
    return null;
}

/** New recovery codes (shown once). Stores hashes, returns the plain codes formatted XXXXX-XXXXX. */
function twofa_new_recovery(int $userId, string $table = 'users'): array {
    $table = $table === 'portal_users' ? 'portal_users' : 'users';
    $plain = []; $hashes = [];
    for ($i = 0; $i < TWOFA_RECOVERY_CODES; $i++) {
        $c = strtoupper(substr(b32_encode(random_bytes(8)), 0, 10));
        $plain[] = substr($c, 0, 5) . '-' . substr($c, 5);
        $hashes[] = password_hash($c, PASSWORD_DEFAULT);
    }
    q("UPDATE `$table` SET totp_recovery = ? WHERE id = ?", [json_encode($hashes), $userId]);
    return $plain;
}
function twofa_recovery_left(array $u): int { return count(json_decode((string)($u['totp_recovery'] ?? '[]'), true) ?: []); }

// ---------- trusted devices ----------
/** kind 'staff' (users, cookie tl2fa) or 'portal' (portal_users, cookie tlp2fa) */
function twofa_trust_cookie_name(string $kind = 'staff'): string { return $kind === 'portal' ? 'tlp2fa' : 'tl2fa'; }
function twofa_trust_table(string $kind): string { return $kind === 'portal' ? 'portal_trusted_devices' : 'trusted_devices'; }
function twofa_trust_device(int $userId, string $kind = 'staff'): void {
    $token = bin2hex(random_bytes(32));
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190);
    q('INSERT INTO `' . twofa_trust_table($kind) . '` (user_id, token_hash, label, expires_at) VALUES (?,?,?, NOW() + INTERVAL ' . TWOFA_TRUST_DAYS . ' DAY)', [$userId, hash('sha256', $token), $ua]);
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(twofa_trust_cookie_name($kind), $userId . ':' . $token, ['expires' => time() + TWOFA_TRUST_DAYS * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
}
function twofa_device_trusted(int $userId, string $kind = 'staff'): bool {
    $c = (string)($_COOKIE[twofa_trust_cookie_name($kind)] ?? '');
    if (!preg_match('/^(\d+):([0-9a-f]{64})$/', $c, $m) || (int)$m[1] !== $userId) return false;
    $t = twofa_trust_table($kind);
    $id = val("SELECT id FROM `$t` WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()", [$userId, hash('sha256', $m[2])]);
    if ($id) q("UPDATE `$t` SET last_used_at = NOW() WHERE id = ?", [$id]);
    return (bool)$id;
}
function twofa_forget_devices(int $userId, string $kind = 'staff'): void { q('DELETE FROM `' . twofa_trust_table($kind) . '` WHERE user_id = ?', [$userId]); }

// ---------- client portal (v1.17) ----------
/** Portal policy: optional | money (logins that see invoices or manage users) | all */
function portal_twofa_required(array $pu): bool {
    $p = setting('portal_twofa_policy', 'money');
    return !empty($pu['totp_required']) || $p === 'all' || ($p === 'money' && (!empty($pu['perm_invoices']) || !empty($pu['is_admin'])));
}
function portal_twofa_must_setup(?array $pu): bool { return $pu && portal_twofa_required($pu) && !twofa_enabled($pu); }

/** Security notice to the user whenever 2FA changes on their account */
function twofa_notice(array $u, string $what, string $app = 'CRM'): void {
    mail_send([['to' => $u['email'], 'subject' => 'Security: ' . $what . ' on your ' . setting('company_name', 'TerraLink') . ' ' . $app . ' account',
        'html' => '<p>Hi ' . h(explode(' ', $u['name'])[0]) . ',</p><p><b>' . h($what) . '</b> on your ' . h($app) . ' account (' . h($u['email']) . ') on ' . h(date('M j, Y \a\t g:i a')) . '.</p>'
            . '<p>If this wasn\'t you or your admin, tell ' . h(setting('company_name')) . ' right away.</p>']]);
}
