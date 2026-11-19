<?php
// Outgoing email (via the n8n "TerraLink CRM — Outbound Email" workflow) and ticket response targets.

/**
 * Sends one or more emails through n8n, which uses the SMTP TERRALINK credential (noreply@).
 * Each message: ['to'=>, 'subject'=>, 'html'=>, 'reply_to'=>?, 'attachment' => ['name'=>, 'mime'=>, 'data'=>raw bytes]?]
 * Returns true when n8n accepted the batch. Every message is written to mail_log.
 */
function mail_send(array $messages): bool {
    if (!$messages) return true;
    $url = setting('mail_webhook_url') ?: 'https://terralinksystems.app.n8n.cloud/webhook/terralink-crm-mail';
    $key = (string)env('CRM_API_KEY', '');
    $payload = ['messages' => array_map(function (array $m) {
        $out = ['to' => $m['to'], 'subject' => $m['subject'], 'html' => mail_wrap($m['html']), 'reply_to' => $m['reply_to'] ?? setting('company_email', '')];
        if (!empty($m['attachment'])) {
            $out['attachment_b64'] = base64_encode($m['attachment']['data']);
            $out['attachment_name'] = $m['attachment']['name'];
            $out['attachment_mime'] = $m['attachment']['mime'] ?? 'application/octet-stream';
        }
        return $out;
    }, $messages)];
    $err = null; $ok = false;
    if ($url === '' || $key === '') {
        $err = 'Email is not configured (mail webhook URL or API key missing).';
    } elseif (env('MAIL_MODE') === 'log') {
        $ok = true;   // local testing: don't send, just log
        @file_put_contents(sys_get_temp_dir() . '/crm-mail.log', json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CRM-Key: ' . $key],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES)]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) $err = 'Connection failed: ' . curl_error($ch);
        elseif ($code < 200 || $code >= 300) $err = "Mail service answered HTTP $code: " . mb_substr((string)$body, 0, 200);
        else $ok = true;
        curl_close($ch);
    }
    foreach ($messages as $m) {
        q('INSERT INTO mail_log (to_addr, subject, status, error) VALUES (?,?,?,?)',
          [mb_substr($m['to'], 0, 255), mb_substr($m['subject'], 0, 255), $ok ? 'sent' : 'failed', $err ? mb_substr($err, 0, 500) : null]);
    }
    return $ok;
}

/** Branded HTML shell for all CRM emails */
function mail_wrap(string $inner): string {
    $co = h(setting('company_name', 'TerraLink Systems'));
    return '<div style="background:#F3F5F7;padding:24px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #dde3e8">'
        . '<div style="background:#0F1A24;border-bottom:4px solid #FF7A1A;padding:16px 22px;color:#fff;font-size:18px;font-weight:bold">' . $co . '</div>'
        . '<div style="padding:22px;font-size:15px;line-height:1.55;color:#14212c">' . $inner . '</div>'
        . '<div style="padding:12px 22px;background:#f3f5f7;color:#7a8a97;font-size:12px">' . $co . ' · ' . h(setting('company_phone')) . ' · ' . h(setting('company_email')) . '</div>'
        . '</div></div>';
}

function mail_button(string $url, string $label): string {
    return '<p style="margin:22px 0"><a href="' . h($url) . '" style="background:#FF7A1A;color:#1b0d00;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px;display:inline-block">' . h($label) . '</a></p>';
}

function app_url(string $path = ''): string {
    $base = rtrim((string)env('APP_URL', ''), '/');
    if ($base === '') {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $base . $path;
}

// ---------- ticket response targets (SLA) ----------
function sla_hours(string $priority): int {
    $d = ['urgent' => 4, 'high' => 8, 'normal' => 24, 'low' => 72];
    return max(1, (int)setting('sla_' . $priority, (string)($d[$priority] ?? 24)));
}

/** @return array{label:string, class:string, overdue:bool}|null  null when nothing is pending */
function sla_state(array $t): ?array {
    if (($t['status'] ?? '') === 'closed' || !empty($t['first_response_at'])) return null;
    $due = strtotime($t['created_at']) + sla_hours($t['priority']) * 3600;
    $left = $due - time();
    $fmt = function (int $s): string {
        $s = abs($s);
        if ($s < 3600) return max(1, (int)round($s / 60)) . ' min';
        if ($s < 86400 * 2) return (int)round($s / 3600) . ' h';
        return (int)round($s / 86400) . ' d';
    };
    if ($left < 0) return ['label' => 'Overdue ' . $fmt($left), 'class' => 'sla-over', 'overdue' => true];
    return ['label' => 'Respond within ' . $fmt($left), 'class' => $left < 3600 ? 'sla-soon' : 'sla-ok', 'overdue' => false];
}

function sla_badge(array $t): string {
    $s = sla_state($t);
    return $s ? '<span class="sla ' . $s['class'] . '">' . h($s['label']) . '</span>' : '';
}

/** Marks the first human response on a ticket (note, time, or status change) */
function ticket_responded(int $ticketId): void {
    q('UPDATE tickets SET first_response_at = NOW() WHERE id = ? AND first_response_at IS NULL', [$ticketId]);
}

/** Badge when the client spoke last in the portal (v1.15) */
function client_waiting_badge(array $t): string {
    if (empty($t['client_waiting_at']) || ($t['status'] ?? '') === 'closed') return '';
    return '<span class="sla sla-soon" title="The client replied in the portal">Client waiting · ' . h(ago($t['client_waiting_at'])) . '</span>';
}

/** Staff answered the client (public reply, status change or close): clears the flag */
function ticket_client_answered(int $ticketId): void {
    q('UPDATE tickets SET client_waiting_at = NULL WHERE id = ?', [$ticketId]);
}

/** SQL condition for "no response yet and past its target" (priority-specific hours from settings) */
function sla_overdue_sql(string $alias = 't'): string {
    $parts = [];
    foreach (['urgent', 'high', 'normal', 'low'] as $p) {
        $parts[] = "($alias.priority = '$p' AND $alias.created_at < NOW() - INTERVAL " . sla_hours($p) . ' HOUR)';
    }
    return "($alias.status <> 'closed' AND $alias.first_response_at IS NULL AND (" . implode(' OR ', $parts) . '))';
}
