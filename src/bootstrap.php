<?php
declare(strict_types=1);

const APP_VERSION = '1.15.0';
const SCHEMA_VERSION = '1.15.0';
const APP_ROOT = __DIR__ . '/..';

date_default_timezone_set(getenv('APP_TZ') ?: 'America/Vancouver');

require APP_ROOT . '/vendor/autoload.php';

// ---------- config ----------
function env(string $k, ?string $default = null): ?string {
    $v = getenv($k);
    if ($v === false || $v === '') {
        // Docker-secret style: FOO_FILE=/path → contents of that file
        $f = getenv($k . '_FILE');
        if ($f && is_readable($f)) $v = trim((string)file_get_contents($f));
    }
    return ($v === false || $v === '') ? $default : $v;
}

// Shared hosting: settings live in crm-config.php OUTSIDE the web root (never in git).
// It returns ['DB_HOST' => ..., 'DB_PASS' => ..., ...]; values become env vars.
(function () {
    // Look in each parent folder of the app (up to 5 levels), e.g. domains/<site>/crm-config.php
    $d = realpath(APP_ROOT) ?: APP_ROOT;
    for ($i = 0; $i < 5 && $d !== dirname($d); $i++) {
        $d = dirname($d);
        $f = $d . '/crm-config.php';
        if (!is_file($f)) continue;
        if (!defined('CONFIG_DIR')) define('CONFIG_DIR', $d);
        $cfg = require $f;
        if (is_array($cfg)) foreach ($cfg as $k => $v) if (getenv((string)$k) === false) putenv("$k=$v");
        break;
    }
})();

// Optional local .env (dev only; in Docker the vars come from compose)
(function () {
    $f = APP_ROOT . '/.env';
    if (!is_file($f)) return;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (getenv($k) === false) putenv("$k=" . trim($v, "\"'"));
    }
})();

define('IS_DEV', env('APP_ENV', 'production') === 'development');
ini_set('display_errors', IS_DEV ? '1' : '0');
error_reporting(E_ALL);

// ---------- database ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'terralink_crm'));
    $pdo = new PDO($dsn, env('DB_USER', 'crm'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Match the tables' collation so string comparisons never mix rules (shared MySQL/MariaDB defaults differ)
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}
require __DIR__ . '/errors.php';

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []): mixed { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

/** Brings the database up to SCHEMA_VERSION: base schema (sql/*.sql) + versioned migrations. Cheap no-op when current. */
function ensure_schema(): void {
    $has = function (string $v): bool {
        try { return (bool)val('SELECT 1 FROM schema_version WHERE version = ?', [$v]); }
        catch (PDOException) { return false; }   // table missing → fresh database
    };
    if ($has(SCHEMA_VERSION)) return;
    if (!val("SELECT GET_LOCK('terralink_crm_migrate', 30)")) throw new RuntimeException('Could not get migration lock');
    try {
        if (!$has('1.3.0')) foreach (glob(APP_ROOT . '/sql/*.sql') as $f) db()->exec(file_get_contents($f));
        require_once __DIR__ . '/migrations.php';
        foreach (migrations() as $v => $fn) {
            if ($has($v)) continue;
            $fn();
            q('INSERT IGNORE INTO schema_version (version) VALUES (?)', [$v]);
        }
    } finally {
        val("SELECT RELEASE_LOCK('terralink_crm_migrate')");
    }
}

function setting(string $k, string $default = ''): string {
    static $cache = null;
    if ($cache === null) $cache = array_column(all('SELECT k, v FROM settings'), 'v', 'k');
    return (string)($cache[$k] ?? $default);
}

/** Atomic counter → e.g. Q-2026-0007 / T-0042 */
function next_number(string $name): string {
    // Single atomic UPDATE; LAST_INSERT_ID(expr) is per-connection, so this is race-free
    // and safe to call inside a caller's transaction.
    q('UPDATE counters SET value = LAST_INSERT_ID(value + 1) WHERE name = ?', [$name]);
    $n = (int)db()->lastInsertId();
    return match ($name) {
        'quote'   => sprintf('Q-%s-%04d', date('Y'), $n),
        'invoice' => sprintf('INV-%04d', $n),
        default   => sprintf('T-%04d', $n),
    };
}

// ---------- sessions / security ----------
function start_session(string $name = 'tlcrm'): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name($name);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.gc_maxlifetime', '43200'); // 12 h — a full field day
    session_start();
}
function h(mixed $s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    // An upload bigger than post_max_size arrives with an empty $_POST: say so instead of "session expired"
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('That upload is too big for the server (limit ' . ini_get('post_max_size') . ' per upload). Try fewer files at once.', 'bad');
        redirect(back('/'));
    }
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) { http_response_code(419); exit('Session expired — go back and reload the page.'); }
}

function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
/** Same-site Referer path, else $default (avoids open redirects) */
function back(string $default = '/'): string {
    $r = $_SERVER['HTTP_REFERER'] ?? '';
    $u = parse_url($r);
    $host = ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
    if (!$r || $host !== ($_SERVER['HTTP_HOST'] ?? '')) return $default;
    return ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '');
}
function not_found(): never { http_response_code(404); view('error', ['title' => 'Not found', 'msg' => 'That record does not exist or was deleted.'], (bool)user()); exit; }
function forbidden(): never { http_response_code(403); view('error', ['title' => 'No access', 'msg' => 'Your role does not allow this action.'], (bool)user()); exit; }

// ---------- auth ----------
function user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    $u = $id ? one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]) : null;
    return $u;
}
function uid(): ?int { return user()['id'] ?? null; }
function require_login(): void { if (!user()) { $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/'; redirect('/login'); } }
function is_role(string ...$roles): bool { return in_array(user()['role'] ?? '', $roles, true); }
function require_role(string ...$roles): void { if (!is_role(...$roles)) forbidden(); }

// ---------- input ----------
function post(string $k, mixed $d = null): mixed { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function get(string $k, mixed $d = null): mixed { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function int_or_null(mixed $v): ?int { return ($v === null || $v === '' || !is_numeric($v)) ? null : (int)$v; }
function str_or_null(mixed $v): ?string { $v = is_string($v) ? trim($v) : $v; return ($v === null || $v === '') ? null : (string)$v; }

// ---------- formatting ----------
function money(mixed $n): string { $n = (float)$n; return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 2); }
/** Whole dollars for summaries/KPIs ($45,500), cents only when they matter */
function money0(mixed $n): string { $n = (float)$n; return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), abs($n - round($n)) > 0.004 && abs($n) < 1000 ? 2 : 0); }
/** Coloured initials dot for a user */
function avatar(?string $name, mixed $id = 0, string $size = ''): string {
    if (!$name) return '';
    $parts = preg_split('/\s+/', trim($name));
    $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $hue = [24, 205, 150, 280, 340, 45, 190, 100][((int)$id) % 8];
    return '<span class="av' . ($size ? " av-$size" : '') . '" style="--h:' . $hue . '" title="' . h($name) . '">' . h($ini) . '</span>';
}
/** Minutes as "45 min", "2 h" or "1 h 30 min" */
function dur(int $m): string {
    if ($m <= 0) return '—';
    $h = intdiv($m, 60); $r = $m % 60;
    return $h ? $h . ' h' . ($r ? " $r min" : '') : "$r min";
}

function fdate(?string $d, string $fmt = 'M j, Y'): string { return $d ? date($fmt, strtotime($d)) : ''; }
function ago(?string $d): string {
    if (!$d) return '';
    $s = time() - strtotime($d);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 7 * 86400) return floor($s / 86400) . ' d ago';
    return fdate($d);
}
function tel(?string $p): string {
    if (!$p) return '';
    return '<a class="tel" href="tel:' . h(preg_replace('/[^0-9+]/', '', $p)) . '">' . h($p) . '</a>';
}
function maplink(?string $addr, ?string $lat = null, ?string $lng = null): string {
    $qv = ($lat && $lng) ? "$lat,$lng" : $addr;
    if (!$qv) return '';
    return '<a href="https://maps.google.com/?q=' . rawurlencode($qv) . '" target="_blank" rel="noopener">Map ↗</a>';
}
function contact_name(array $c): string { return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')); }

// ---------- activity log ----------
function log_activity(string $type, string $entityType, int $entityId, string $body, ?int $companyId = null): void {
    q('INSERT INTO activities (entity_type, entity_id, company_id, user_id, type, body) VALUES (?,?,?,?,?,?)',
        [$entityType, $entityId, $companyId, uid(), $type, $body]);
}

// ---------- views ----------
function view(string $name, array $data = [], bool $layout = true): void {
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . "/views/$name.php";
    $content = ob_get_clean();
    if ($layout) require APP_ROOT . '/views/layout.php';
    else echo $content;
}
function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

require __DIR__ . '/constants.php';
require __DIR__ . '/forms.php';
require __DIR__ . '/services.php';
require __DIR__ . '/files.php';
require __DIR__ . '/automation.php';
require __DIR__ . '/portal.php';
