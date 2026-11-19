<?php
declare(strict_types=1);
/**
 * Recurring service plans and the daily run (called every morning by n8n → POST /api/cron/daily).
 *  - plans whose next visit is within their lead time get a ticket, then move on by their interval
 *  - every user with daily_email = 1 gets one summary email (skipped when there's nothing for them)
 */

/** $anchorDay keeps "the 31st" as the 31st (or month end) instead of drifting to the 28th after February */
function plan_add_months(string $date, int $months, ?int $anchorDay = null): string {
    $d = new DateTime($date);
    $day = $anchorDay ?: (int)$d->format('j');
    $d->modify('first day of this month')->modify("+$months month");
    $d->setDate((int)$d->format('Y'), (int)$d->format('n'), min($day, (int)$d->format('t')));   // Jan 31 + 1 → Feb 28
    return $d->format('Y-m-d');
}

/** Opens the ticket for a plan's next visit and moves next_due on. Returns the ticket id. */
function plan_create_ticket(array $p, ?int $byUser = null): int {
    $due = $p['next_due'];
    $number = next_number('ticket');
    $desc = trim("Scheduled visit from service plan \"{$p['title']}\" (" . (PLAN_EVERY[(int)$p['every_months']] ?? $p['every_months'] . ' months') . ").\nDue: " . date('D M j, Y', strtotime($due))
        . ($p['description'] ? "\n\n" . $p['description'] : ''));
    q("INSERT INTO tickets (number, company_id, site_id, plan_id, subject, description, priority, status, assigned_to, created_by, first_response_at)
       VALUES (?,?,?,?,?,?,?, 'open', ?, ?, NOW())",
      [$number, $p['company_id'], $p['site_id'], $p['id'], mb_substr($p['title'] . ' — due ' . date('M j', strtotime($due)), 0, 190), $desc,
       $p['priority'], $p['assigned_to'], $byUser ?? $p['created_by']]);
    $tid = (int)db()->lastInsertId();
    $every = max(1, (int)$p['every_months']); $anchor = (int)($p['due_day'] ?? 0) ?: null;
    $next = plan_add_months($due, $every, $anchor);
    while ($next <= date('Y-m-d')) $next = plan_add_months($next, $every, $anchor);   // long-missed plans: no backlog of tickets
    q('UPDATE service_plans SET next_due = ?, last_ticket_id = ? WHERE id = ?', [$next, $tid, $p['id']]);
    q('INSERT INTO activities (entity_type, entity_id, company_id, user_id, type, body) VALUES (?,?,?,?,?,?)',
      ['ticket', $tid, $p['company_id'], $byUser, 'system', "Ticket $number opened from service plan: {$p['title']}"]);
    return $tid;
}

/** All plans that are due for a ticket now. Returns [plan id => ticket id] */
function plans_generate(): array {
    $made = [];
    $due = all("SELECT * FROM service_plans WHERE active = 1 AND DATE_SUB(next_due, INTERVAL lead_days DAY) <= CURDATE() ORDER BY next_due");
    foreach ($due as $p) $made[(int)$p['id']] = plan_create_ticket($p);
    return $made;
}

/** The daily summary for one user, or null when there's nothing worth an email */
function daily_digest(array $u): ?array {
    $id = (int)$u['id'];
    $today = date('Y-m-d');
    $tasks = all("SELECT t.title, t.due_date, c.name company FROM tasks t LEFT JOIN companies c ON c.id = t.company_id
                  WHERE t.done_at IS NULL AND t.assigned_to = ? AND t.due_date <= ? ORDER BY t.due_date, t.id LIMIT 30", [$id, $today]);
    $tickets = all("SELECT t.*, c.name company, s.name site FROM tickets t JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id
                    WHERE t.status <> 'closed' AND t.assigned_to = ?
                    ORDER BY FIELD(t.priority,'urgent','high','normal','low'), t.created_at LIMIT 30", [$id]);
    $leads = in_array($u['role'], ['admin', 'sales'], true)
        ? all("SELECT d.title, c.name company, d.created_at FROM deals d LEFT JOIN companies c ON c.id = d.company_id
               WHERE d.stage = 'new' AND d.created_at >= NOW() - INTERVAL 1 DAY ORDER BY d.id DESC LIMIT 15") : [];
    $quotes = in_array($u['role'], ['admin', 'sales'], true)
        ? all("SELECT q.number, q.title, q.valid_until, c.name company FROM quotes q JOIN companies c ON c.id = q.company_id
               WHERE q.status = 'sent' AND q.valid_until BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY ORDER BY q.valid_until") : [];
    $low = $u['role'] === 'admin'
        ? all('SELECT name, qty_on_hand, unit, reorder_level FROM consumables WHERE active = 1 AND reorder_level > 0 AND qty_on_hand <= reorder_level ORDER BY name') : [];
    if (!$tasks && !$tickets && !$leads && !$quotes && !$low) return null;

    $li = fn(string $s) => '<li style="margin:0 0 6px">' . $s . '</li>';
    $sec = fn(string $title, array $items) => $items ? '<h3 style="font-size:16px;margin:20px 0 8px;color:#0F1A24">' . h($title) . '</h3><ul style="padding-left:18px;margin:0">' . implode('', $items) . '</ul>' : '';
    $red = fn(string $s) => '<b style="color:#C62828">' . $s . '</b>';
    $html = '<p style="margin:0 0 6px">Good morning ' . h(strtok($u['name'], ' ')) . ', here\'s your ' . h(date('l, F j')) . '.</p>';
    $html .= $sec('Tickets assigned to you', array_map(function ($t) use ($li, $red) {
        $sla = sla_state($t);
        return $li('<a href="' . h(app_url('/tickets/' . $t['id'])) . '">' . h($t['number'] . ' · ' . $t['subject']) . '</a> — ' . h($t['company'] . ($t['site'] ? ' · ' . $t['site'] : ''))
            . ($t['priority'] === 'urgent' || $t['priority'] === 'high' ? ' · ' . h(TICKET_PRIORITY[$t['priority']]) : '')
            . ($sla && $sla['overdue'] ? ' · ' . $red(h($sla['label'])) : ''));
    }, $tickets));
    $html .= $sec('Tasks due', array_map(fn($t) => $li(h($t['title']) . ($t['company'] ? ' — ' . h($t['company']) : '') . ' · '
        . ($t['due_date'] < $today ? $red('overdue since ' . h(date('M j', strtotime($t['due_date'])))) : 'today')), $tasks));
    $html .= $sec('New leads (last 24 h)', array_map(fn($d) => $li(h($d['title']) . ($d['company'] ? ' — ' . h($d['company']) : '')), $leads));
    $html .= $sec('Quotes expiring soon', array_map(fn($q) => $li(h($q['number'] . ' · ' . $q['title']) . ' — ' . h($q['company']) . ' · valid until ' . h(date('M j', strtotime($q['valid_until'])))), $quotes));
    $html .= $sec('Low stock', array_map(fn($c) => $li(h($c['name']) . ': ' . h(rtrim(rtrim(number_format((float)$c['qty_on_hand'], 2, '.', ''), '0'), '.') . ' ' . $c['unit']) . ' left (re-order at ' . h((string)(float)$c['reorder_level']) . ')'), $low));
    $html .= mail_button(app_url('/today'), 'Open My day');
    $html .= '<p style="font-size:12px;color:#7a8a97;margin:0">Don\'t want this email? Turn it off under My account in the CRM.</p>';

    $bits = [];
    if ($tickets) $bits[] = count($tickets) . ' ticket' . (count($tickets) === 1 ? '' : 's');
    if ($tasks) $bits[] = count($tasks) . ' task' . (count($tasks) === 1 ? '' : 's');
    if ($leads) $bits[] = count($leads) . ' new lead' . (count($leads) === 1 ? '' : 's');
    if (!$bits && $quotes) $bits[] = 'quotes expiring';
    if (!$bits && $low) $bits[] = 'low stock';
    return ['to' => $u['email'], 'subject' => 'Your day, ' . date('D M j') . ': ' . implode(', ', $bits), 'html' => $html];
}

/** The morning run. Only once per day unless $force. */
function cron_daily(bool $force = false, bool $send = true): array {
    $today = date('Y-m-d');
    if (!$force && setting('cron_daily_last') === $today) return ['ok' => true, 'skipped' => 'already ran today'];
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', ['cron_daily_last', $today]);
    $made = plans_generate();
    $mails = [];
    foreach (all("SELECT * FROM users WHERE active = 1 AND daily_email = 1 AND email <> ''") as $u) {
        $m = daily_digest($u);
        if ($m) $mails[] = $m;
    }
    $sent = ($send && $mails) ? mail_send($mails) : null;
    // old one-time links are useless after a day
    q('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 7 DAY');
    return ['ok' => true, 'date' => $today, 'plan_tickets' => count($made), 'emails' => count($mails), 'mail_ok' => $sent];
}
