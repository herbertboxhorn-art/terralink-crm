<?php
declare(strict_types=1);
/**
 * Client portal (portal.terralinksystems.com): shared helpers used by the portal itself
 * and by the staff side (Portal access card on a company, client-visible ticket replies).
 *
 * Clients log in per company. Each login (portal_users) has ticks for the areas it may see.
 * Money (quotes, invoices, plan fees, ticket charges) only shows with perm_quotes / perm_invoices.
 */

/** Permission ticks, in the order they're shown. key => [label, hint] */
const PORTAL_PERMS = [
    'perm_tickets'  => ['Tickets', 'See tickets, open new ones, reply and add photos'],
    'perm_sites'    => ['Sites & equipment', 'Installed gear, serial numbers and warranties'],
    'perm_plans'    => ['Service plans', 'Upcoming maintenance visits (fees only with Invoices)'],
    'perm_quotes'   => ['Quotes', 'See quotes with prices and accept or decline them'],
    'perm_invoices' => ['Invoices & payments', 'Invoices, PDFs, balance owing, charges on tickets'],
    'is_admin'      => ['Manage users', 'Add colleagues and set what they can see'],
];
const PORTAL_PRESETS = [
    'tickets' => ['label' => 'Tickets only', 'perms' => ['perm_tickets']],
    'ops'     => ['label' => 'Operations (no money)', 'perms' => ['perm_tickets', 'perm_tickets_all', 'perm_sites', 'perm_plans']],
    'full'    => ['label' => 'Full access + manage users', 'perms' => ['perm_tickets', 'perm_tickets_all', 'perm_sites', 'perm_plans', 'perm_quotes', 'perm_invoices', 'is_admin']],
];
const PORTAL_ALL_PERMS = ['perm_tickets', 'perm_tickets_all', 'perm_sites', 'perm_plans', 'perm_quotes', 'perm_invoices', 'is_admin'];

// ---------- host & urls ----------
function portal_host(): string { return strtolower((string)env('PORTAL_HOST', 'portal.terralinksystems.com')); }

/** True when this request came in on the portal hostname (staff routes are never served there) */
function is_portal_request(): bool {
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    return $host !== '' && $host === preg_replace('/:\d+$/', '', portal_host());
}

function portal_url(string $path = ''): string {
    $base = rtrim((string)env('PORTAL_URL', ''), '/');
    if ($base === '') $base = 'https://' . portal_host();
    return $base . $path;
}

// ---------- current portal user ----------
function portal_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['pid'] ?? null;
    $u = $id ? one('SELECT pu.*, c.name company FROM portal_users pu JOIN companies c ON c.id = pu.company_id WHERE pu.id = ? AND pu.active = 1', [$id]) : null;
    return $u;
}
function pu_id(): ?int { return isset(portal_user()['id']) ? (int)portal_user()['id'] : null; }
function pu_company(): int { return (int)(portal_user()['company_id'] ?? 0); }
function portal_can(string $perm, ?array $pu = null): bool { $pu ??= portal_user(); return !empty($pu[$perm]); }

/** Site ids a login is limited to, or null when it may see every site of its company (v1.15) */
function portal_site_ids(array $pu): ?array {
    static $cache = [];
    $id = (int)$pu['id'];
    if (!array_key_exists($id, $cache)) {
        $ids = array_map('intval', array_column(all('SELECT site_id FROM portal_user_sites WHERE portal_user_id = ?', [$id]), 'site_id'));
        $cache[$id] = $ids ?: null;
    }
    return $cache[$id];
}

/** SQL "AND col IN (…)" for a site-limited login, '' otherwise. Ids are ints from the database, so inlining is safe. */
function portal_site_sql(array $pu, string $col): string {
    $ids = portal_site_ids($pu);
    return $ids === null ? '' : " AND $col IN (" . implode(',', $ids) . ')';
}

/** WHERE condition (alias t) for the tickets this login may see */
function portal_ticket_scope(array $pu, string $a = 't'): array {
    $sites = portal_site_ids($pu);
    if (!empty($pu['perm_tickets_all']) && $sites !== null) {
        // site-limited: every ticket at their sites, plus their own anywhere
        return ["($a.company_id = ? AND ($a.site_id IN (" . implode(',', $sites) . ") OR $a.portal_user_id = ? OR ($a.contact_id IS NOT NULL AND $a.contact_id = ?)))",
                [(int)$pu['company_id'], (int)$pu['id'], (int)($pu['contact_id'] ?? 0)]];
    }
    if (!empty($pu['perm_tickets_all'])) return ["$a.company_id = ?", [(int)$pu['company_id']]];
    if ($pu['contact_id']) return ["($a.company_id = ? AND ($a.portal_user_id = ? OR $a.contact_id = ?))", [(int)$pu['company_id'], (int)$pu['id'], (int)$pu['contact_id']]];
    return ["($a.company_id = ? AND $a.portal_user_id = ?)", [(int)$pu['company_id'], (int)$pu['id']]];
}

/** Short summary of what a login can see, e.g. "Tickets (all), Sites, Quotes" */
function portal_perm_summary(array $pu): string {
    $out = [];
    foreach (PORTAL_PERMS as $k => [$label]) {
        if (empty($pu[$k])) continue;
        if ($k === 'perm_tickets') $label .= empty($pu['perm_tickets_all']) ? ' (own)' : ' (all)';
        $out[] = $label;
    }
    $s = $out ? implode(', ', $out) : 'Nothing yet';
    if (!empty($pu['id']) && ($ids = portal_site_ids($pu)) !== null) {
        $s .= ' · only ' . implode(', ', array_column(all('SELECT name FROM sites WHERE id IN (' . implode(',', $ids) . ') ORDER BY name'), 'name'));
    }
    return $s;
}

// ---------- create / update logins (staff side and client admins share this) ----------
/**
 * Validates and saves a portal login. $limit = perms the editor may hand out (null = any, for staff).
 * Returns [id|null, errors]. Sends the invite for new logins when $invite is true.
 */
function portal_save_user(int $companyId, array $in, ?array $existing, ?array $limit, bool $invite, string $byName, ?array $limitSites = null): array {
    $errors = [];
    $name = trim((string)($in['name'] ?? ''));
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $contactId = int_or_null($in['contact_id'] ?? null);
    if ($contactId && (int)val('SELECT company_id FROM contacts WHERE id = ?', [$contactId]) !== $companyId) $contactId = null;
    if ($contactId && ($name === '' || $email === '')) {
        $ct = one('SELECT * FROM contacts WHERE id = ?', [$contactId]);
        if ($name === '') $name = contact_name($ct);
        if ($email === '') $email = strtolower((string)$ct['email']);
    }
    if ($name === '') $errors['name'] = 'Required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Not a valid email';
    elseif ($other = one('SELECT pu.id, c.name FROM portal_users pu JOIN companies c ON c.id = pu.company_id WHERE pu.email = ? AND pu.id <> ?', [$email, (int)($existing['id'] ?? 0)])) {
        $errors['email'] = 'This email already has a portal login (' . $other['name'] . ')';
    }
    // match the contact by email when none was picked
    if (!$contactId && !$errors) $contactId = int_or_null(val('SELECT id FROM contacts WHERE company_id = ? AND LOWER(email) = ? LIMIT 1', [$companyId, $email]));

    $perms = [];
    foreach (PORTAL_ALL_PERMS as $p) {
        $want = !empty($in[$p]) ? 1 : 0;
        // A client admin can only hand out what they have themselves; keep the current value of anything else
        if ($limit !== null && !in_array($p, $limit, true)) $want = (int)($existing[$p] ?? 0);
        $perms[$p] = $want;
    }
    if (!$perms['perm_tickets']) $perms['perm_tickets_all'] = 0;
    // Site limit: only the company's own sites; a site-limited admin can only hand out (a subset of) their sites
    $companySites = array_map('intval', array_column(all('SELECT id FROM sites WHERE company_id = ?', [$companyId]), 'id'));
    $sites = array_values(array_intersect(array_map('intval', (array)($in['site_ids'] ?? [])), $companySites));
    if ($limitSites !== null) { $sites = array_values(array_intersect($sites, $limitSites)); if (!$sites) $sites = $limitSites; }
    if ($errors) return [null, $errors];

    $vals = ['name' => $name, 'email' => $email, 'contact_id' => $contactId] + $perms;
    if (array_key_exists('active', $in)) $vals['active'] = !empty($in['active']) ? 1 : 0;
    if ($existing) {
        q('UPDATE portal_users SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals))) . ' WHERE id = ?', [...array_values($vals), (int)$existing['id']]);
        portal_set_sites((int)$existing['id'], $sites);
        return [(int)$existing['id'], []];
    }
    $vals += ['company_id' => $companyId, 'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
              'created_by_user' => uid(), 'created_by_portal' => pu_id()];
    q('INSERT INTO portal_users (' . implode(',', array_keys($vals)) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')', array_values($vals));
    $id = (int)db()->lastInsertId();
    portal_set_sites($id, $sites);
    q('INSERT INTO activities (entity_type, entity_id, company_id, user_id, portal_user_id, type, body) VALUES (?,?,?,?,?,?,?)',
      ['company', $companyId, $companyId, uid(), pu_id(), 'system', "Portal login added for $name ($email) by $byName"]);
    if ($invite) portal_send_invite(one('SELECT * FROM portal_users WHERE id = ?', [$id]), $byName);
    return [$id, []];
}

function portal_set_sites(int $puId, array $siteIds): void {
    q('DELETE FROM portal_user_sites WHERE portal_user_id = ?', [$puId]);
    foreach (array_unique($siteIds) as $s) q('INSERT INTO portal_user_sites (portal_user_id, site_id) VALUES (?, ?)', [$puId, (int)$s]);
}

function portal_make_token(int $puId, string $kind): string {
    $token = bin2hex(random_bytes(32));
    q('UPDATE portal_resets SET used_at = NOW() WHERE portal_user_id = ? AND used_at IS NULL', [$puId]);
    q('INSERT INTO portal_resets (portal_user_id, token_hash, kind, expires_at) VALUES (?,?,?, NOW() + INTERVAL ' . ($kind === 'invite' ? '7 DAY' : '1 HOUR') . ')',
      [$puId, hash('sha256', $token), $kind]);
    return $token;
}

function portal_send_invite(array $pu, string $byName): bool {
    $co = (string)val('SELECT name FROM companies WHERE id = ?', [$pu['company_id']]);
    $link = portal_url('/reset?token=' . portal_make_token((int)$pu['id'], 'invite'));
    q('UPDATE portal_users SET invited_at = NOW() WHERE id = ?', [$pu['id']]);
    return mail_send([[
        'to' => $pu['email'], 'subject' => 'Your ' . setting('company_name', 'TerraLink Systems') . ' client portal login',
        'html' => '<p>Hi ' . h(explode(' ', $pu['name'])[0]) . ',</p><p>' . h($byName) . ' set up a client portal login for you at <b>' . h($co) . '</b>.</p>'
            . '<p>In the portal you can see: <b>' . h(portal_perm_summary($pu)) . '</b>.</p>'
            . '<p>Choose your password to get started. Your login is <b>' . h($pu['email']) . '</b>.</p>' . mail_button($link, 'Set my password')
            . '<p style="color:#7a8a97;font-size:13px">This link works once and expires in 7 days. Afterwards, sign in any time at ' . h(portal_url()) . '</p>',
    ]]);
}

// ---------- notifications ----------
/** Staff to tell about client activity on a ticket: the assignee, else all admins */
function portal_staff_emails(array $t): array {
    if (!empty($t['assigned_to'])) {
        $e = val('SELECT email FROM users WHERE id = ? AND active = 1', [$t['assigned_to']]);
        if ($e) return [$e];
    }
    return array_column(all("SELECT email FROM users WHERE role = 'admin' AND active = 1"), 'email');
}

/** Portal logins that can see a ticket and should hear about replies: the person who opened it, or the login linked to its contact */
function portal_ticket_watchers(array $t): array {
    return all('SELECT * FROM portal_users WHERE active = 1 AND perm_tickets = 1 AND company_id = ? AND (id = ? OR (contact_id IS NOT NULL AND contact_id = ?))',
        [(int)$t['company_id'], (int)($t['portal_user_id'] ?? 0), (int)($t['contact_id'] ?? 0)]);
}

/** Emails the client watchers of a ticket about a staff reply. Returns how many were emailed. */
function portal_notify_client(array $t, string $message, ?string $newStatus = null): int {
    $w = portal_ticket_watchers($t);
    if (!$w) return 0;
    $link = portal_url('/tickets/' . (int)$t['id']);
    $by = user()['name'] ?? setting('company_name');
    $msgs = [];
    foreach ($w as $pu) {
        $msgs[] = ['to' => $pu['email'], 'subject' => 'Update on ' . $t['number'] . ': ' . $t['subject'],
            'html' => '<p>Hi ' . h(explode(' ', $pu['name'])[0]) . ',</p><p><b>' . h($by) . '</b> replied to your ticket <b>' . h($t['number']) . '</b>:</p>'
                . '<blockquote style="margin:12px 0;padding:10px 14px;background:#f3f5f7;border-left:3px solid #FF7A1A;white-space:pre-wrap">' . h($message) . '</blockquote>'
                . ($newStatus ? '<p>Status: <b>' . h(TICKET_STATUS[$newStatus] ?? $newStatus) . '</b></p>' : '')
                . mail_button($link, 'View or reply in the portal')];
    }
    return mail_send($msgs) ? count($msgs) : 0;
}

/** Does this company have an active portal login with the given permission? (for "view online" buttons in emails) */
function portal_company_has(int $companyId, string $perm): bool {
    if (!in_array($perm, PORTAL_ALL_PERMS, true)) return false;
    return (bool)val("SELECT 1 FROM portal_users WHERE company_id = ? AND active = 1 AND $perm = 1 LIMIT 1", [$companyId]);
}

/** Tells the client watchers a ticket is resolved (v1.15). Returns how many were emailed. */
function portal_notify_closed(array $t): int {
    $w = portal_ticket_watchers($t);
    if (!$w) return 0;
    $link = portal_url('/tickets/' . (int)$t['id']);
    $msgs = [];
    foreach ($w as $pu) {
        $msgs[] = ['to' => $pu['email'], 'subject' => 'Resolved: ' . $t['number'] . ' ' . $t['subject'],
            'html' => '<p>Hi ' . h(explode(' ', $pu['name'])[0]) . ',</p><p>We\'ve closed your ticket <b>' . h($t['number']) . '</b>: ' . h($t['subject']) . '.</p>'
                . '<p>You can see what was done and download the work summary in the portal. If it\'s not fixed, open a follow-up ticket there or call us at ' . h(setting('company_phone')) . '.</p>'
                . mail_button($link, 'View the ticket')];
    }
    return mail_send($msgs) ? count($msgs) : 0;
}
