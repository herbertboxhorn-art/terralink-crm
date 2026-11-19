<?php
declare(strict_types=1);
/**
 * App error log (v1.15). Shared hosting gives us no PHP error log, so uncaught exceptions, fatal
 * errors and warnings are written to the app_errors table (deduplicated) and shown to admins at /errors.
 */

/** Records an error. Never throws: logging must not break the page any further. */
function app_error_record(string $message, ?string $file = null, ?int $line = null, string $trace = ''): void {
    static $busy = false;
    if ($busy) return;
    $busy = true;
    try {
        $root = realpath(APP_ROOT) ?: APP_ROOT;
        $file = $file ? str_replace($root . '/', '', $file) : null;
        $msg = mb_substr(trim($message), 0, 1000);
        // same error on the same line counts as one entry, whatever ids/values are in the message
        $fp = sha1(preg_replace('/\d+/', '#', $msg) . '|' . $file . '|' . $line);
        $who = isset($_SESSION['uid']) ? 'staff #' . (int)$_SESSION['uid'] : (isset($_SESSION['pid']) ? 'portal #' . (int)$_SESSION['pid'] : null);
        $url = PHP_SAPI === 'cli' ? 'cli' : mb_substr(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), 0, 500);
        q('INSERT INTO app_errors (fingerprint, message, file, line, trace, method, url, who) VALUES (?,?,?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE hits = hits + 1, last_at = NOW(), url = VALUES(url), who = VALUES(who), method = VALUES(method), trace = VALUES(trace)',
          [$fp, $msg, $file, $line, mb_substr($trace, 0, 8000), $_SERVER['REQUEST_METHOD'] ?? null, $url, $who]);
    } catch (\Throwable) {
        // database down or table missing: nothing more we can do
    } finally {
        $busy = false;
    }
}

function app_error_page(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title>'
        . '<div style="font-family:Arial,sans-serif;max-width:520px;margin:12vh auto;padding:24px;border:1px solid #dde3e8;border-radius:12px;border-top:4px solid #FF7A1A">'
        . '<h1 style="margin:0 0 8px;font-size:22px">Something went wrong</h1><p style="color:#40525f">The problem was logged and we\'ll look into it. '
        . 'Go back and try again; if it keeps happening, call ' . htmlspecialchars((string)(getenv('COMPANY_PHONE') ?: '778-648-2400')) . '.</p>'
        . '<p><a href="/" style="color:#1E64B4">Back to the start</a></p></div>';
}

set_exception_handler(function (\Throwable $e): void {
    app_error_record(get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, (string)$e . "\n"); exit(1); }
    if (IS_DEV) { http_response_code(500); echo '<pre>' . htmlspecialchars((string)$e) . '</pre>'; return; }
    app_error_page();
});

set_error_handler(function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) return false;   // silenced with @
    if (in_array($no, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE], true)) {
        app_error_record(($no === E_WARNING || $no === E_USER_WARNING ? 'Warning: ' : 'Notice: ') . $str, $file, $line);
    }
    return false;   // let PHP carry on as normal
});

register_shutdown_function(function (): void {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    app_error_record('Fatal: ' . $e['message'], $e['file'], $e['line']);
    if (!IS_DEV) app_error_page();
});
