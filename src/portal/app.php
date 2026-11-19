<?php
declare(strict_types=1);
/**
 * Client portal app, served on PORTAL_HOST (portal.terralinksystems.com).
 * Separate session cookie (tlportal) and its own logins (portal_users). Staff routes never run here.
 * Every query is scoped to the login's company; each area also needs its permission tick.
 */

// Staff handler files only define functions (quote/invoice/PDF helpers are reused); none of their routes are reachable here.
foreach (glob(APP_ROOT . '/src/handlers/*.php') as $f) if (!str_ends_with($f, 'api.php')) require_once $f;

function portal_dispatch(string $method, string $path): void {
    start_session('tlportal');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex');

    // [methods, regex, handler, permission|null|'public']
    $routes = [
        ['GET|POST', '/login', 'p_login', 'public'],
        ['GET|POST', '/forgot', 'p_forgot', 'public'],
        ['GET|POST', '/reset', 'p_reset', 'public'],
        ['POST', '/logout', 'p_logout', 'public'],
        ['GET', '/', 'p_home', null],
        ['GET|POST', '/account', 'p_account', null],

        ['GET', '/tickets', 'p_tickets', 'perm_tickets'],
        ['GET|POST', '/tickets/new', 'p_ticket_new', 'perm_tickets'],
        ['GET', '/tickets/(\d+)', 'p_ticket_show', 'perm_tickets'],
        ['POST', '/tickets/(\d+)/reply', 'p_ticket_reply', 'perm_tickets'],
        ['GET', '/tickets/(\d+)/summary', 'p_ticket_summary', 'perm_tickets'],
        ['GET', '/files/(\d+)', 'p_file', 'perm_tickets'],
        ['GET', '/files/(\d+)/thumb', 'p_file_thumb', 'perm_tickets'],

        ['GET', '/sites', 'p_sites', 'perm_sites'],
        ['GET', '/plans', 'p_plans', 'perm_plans'],

        ['GET', '/quotes', 'p_quotes', 'perm_quotes'],
        ['GET', '/quotes/(\d+)', 'p_quote_show', 'perm_quotes'],
        ['GET', '/quotes/(\d+)/pdf', 'p_quote_pdf', 'perm_quotes'],
        ['POST', '/quotes/(\d+)/decide', 'p_quote_decide', 'perm_quotes'],

        ['GET', '/invoices', 'p_invoices', 'perm_invoices'],
        ['GET', '/invoices/(\d+)/pdf', 'p_invoice_pdf', 'perm_invoices'],

        ['GET', '/users', 'p_users', 'is_admin'],
        ['GET|POST', '/users/new', 'p_user_form', 'is_admin'],
        ['GET|POST', '/users/(\d+)/edit', 'p_user_form', 'is_admin'],
        ['POST', '/users/(\d+)/invite', 'p_user_invite', 'is_admin'],
    ];
    foreach ($routes as [$methods, $pattern, $handler, $perm]) {
        if (!in_array($method, explode('|', $methods), true)) continue;
        if (!preg_match('#^' . $pattern . '$#', $path, $m)) continue;
        if ($perm !== 'public') {
            if (!portal_user()) { if ($method === 'GET') $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/'; redirect('/login'); }
            if ($perm !== null && !portal_can($perm)) p_forbidden();
        }
        if ($method === 'POST') csrf_check();
        array_shift($m);
        $handler(...array_map(fn($x) => ctype_digit($x) ? (int)$x : $x, $m));
        exit;
    }
    p_not_found();
}

// ---------- rendering ----------
function pview(string $name, array $data = []): void {
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . "/views/portal/$name.php";
    $content = ob_get_clean();
    require APP_ROOT . '/views/portal/layout.php';
}
function p_not_found(): never { http_response_code(404); portal_user() ? pview('error', ['title' => 'Not found', 'msg' => 'That page does not exist, or it isn\'t shared with your login.']) : redirect('/login'); exit; }
function p_forbidden(): never { http_response_code(403); pview('error', ['title' => 'Not available', 'msg' => 'Your login doesn\'t include this part of the portal. Ask your company\'s portal admin or ' . setting('company_name', 'TerraLink') . ' if you need it.']); exit; }

/** Activity entry written by the portal user */
function p_log(string $type, string $entityType, int $entityId, string $body, bool $public = false): void {
    q('INSERT INTO activities (entity_type, entity_id, company_id, user_id, portal_user_id, type, body, is_public) VALUES (?,?,?,NULL,?,?,?,?)',
      [$entityType, $entityId, pu_company(), pu_id(), $type, $body, $public ? 1 : 0]);
}

// ---------- auth ----------
function p_login(): void {
    if (portal_user()) redirect('/');
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $email = strtolower((string)post('email', ''));
        if ((int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]) >= 8) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $pu = one('SELECT * FROM portal_users WHERE email = ? AND active = 1', [$email]);
            if ($pu && password_verify((string)post('password', ''), $pu['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION = ['pid' => (int)$pu['id'], 'after_login' => $_SESSION['after_login'] ?? null];
                q('UPDATE portal_users SET last_login_at = NOW() WHERE id = ?', [$pu['id']]);
                q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
                $to = $_SESSION['after_login'] ?? '/';
                unset($_SESSION['after_login']);
                redirect(is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/');
            }
            q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, 'portal:' . $email]);
            $error = 'Email or password is wrong.';
        }
    }
    p_auth('login', ['title' => 'Sign in', 'error' => $error]);
}

function p_auth(string $name, array $data): void {
    extract($data, EXTR_SKIP);
    require APP_ROOT . "/views/portal/$name.php";
}

function p_logout(): void {
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

function p_forgot(): void {
    if (portal_user()) redirect('/');
    $sent = false; $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $email = strtolower((string)post('email', ''));
        if ((int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]) >= 8) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, 'portal-reset:' . $email]);
            $pu = one('SELECT * FROM portal_users WHERE email = ? AND active = 1', [$email]);
            if ($pu) {
                $link = portal_url('/reset?token=' . portal_make_token((int)$pu['id'], 'reset'));
                mail_send([[
                    'to' => $pu['email'], 'subject' => 'Reset your ' . setting('company_name', 'TerraLink') . ' client portal password',
                    'html' => '<p>Hi ' . h(explode(' ', $pu['name'])[0]) . ',</p><p>Someone (hopefully you) asked to reset your client portal password.</p>'
                        . mail_button($link, 'Choose a new password')
                        . '<p style="color:#7a8a97;font-size:13px">This link works once and expires in 1 hour. If you didn\'t ask for this, ignore this email; your password stays the same.</p>',
                ]]);
            }
            $sent = true;   // same answer whether or not the login exists
        }
    }
    p_auth('forgot', ['title' => 'Forgot password', 'sent' => $sent, 'error' => $error]);
}

function p_reset(): void {
    $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
    $row = $token !== '' ? one('SELECT r.*, pu.name, pu.email FROM portal_resets r JOIN portal_users pu ON pu.id = r.portal_user_id
                                 WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND pu.active = 1', [hash('sha256', $token)]) : null;
    $errors = [];
    if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10) $errors['password'] = 'At least 10 characters';
        if ($pw !== (string)($_POST['confirm'] ?? '')) $errors['confirm'] = 'Does not match';
        if (!$errors) {
            q('UPDATE portal_users SET password_hash = ?, last_login_at = NOW() WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['portal_user_id']]);
            q('UPDATE portal_resets SET used_at = NOW() WHERE portal_user_id = ? AND used_at IS NULL', [$row['portal_user_id']]);
            session_regenerate_id(true);
            $_SESSION = ['pid' => (int)$row['portal_user_id']];
            flash($row['kind'] === 'invite' ? 'Welcome! Your password is set.' : 'Password changed. You\'re signed in.');
            redirect('/');
        }
    }
    p_auth('reset', ['title' => 'Set password', 'row' => $row, 'token' => $token, 'errors' => $errors]);
}

function p_account(): void {
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pu = one('SELECT * FROM portal_users WHERE id = ?', [pu_id()]);
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $pu['password_hash'])) $errors['current'] = 'Current password is wrong';
        if (strlen($new) < 10) $errors['new'] = 'At least 10 characters';
        if ($new !== (string)($_POST['confirm'] ?? '')) $errors['confirm'] = 'Does not match';
        if (!$errors) {
            q('UPDATE portal_users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), pu_id()]);
            flash('Password changed.');
            redirect('/account');
        }
    }
    pview('account', ['title' => 'My account', 'errors' => $errors]);
}

// ---------- home ----------
function p_home(): void {
    $pu = portal_user(); $cid = pu_company();
    $d = ['title' => 'Home'];
    if (portal_can('perm_tickets')) {
        [$w, $p] = portal_ticket_scope($pu);
        $d['openTickets'] = (int)val("SELECT COUNT(*) FROM tickets t WHERE $w AND t.status <> 'closed'", $p);
        $d['waiting'] = (int)val("SELECT COUNT(*) FROM tickets t WHERE $w AND t.status = 'waiting'", $p);
        $d['recent'] = all("SELECT t.*, s.name site FROM tickets t LEFT JOIN sites s ON s.id = t.site_id WHERE $w ORDER BY t.status = 'closed', t.updated_at DESC LIMIT 6", $p);
    }
    if (portal_can('perm_plans')) $d['nextVisit'] = one('SELECT sp.*, s.name site FROM service_plans sp LEFT JOIN sites s ON s.id = sp.site_id WHERE sp.company_id = ? AND sp.active = 1 ORDER BY sp.next_due LIMIT 1', [$cid]);
    if (portal_can('perm_quotes')) $d['quotesWaiting'] = (int)val("SELECT COUNT(*) FROM quotes WHERE company_id = ? AND status = 'sent' AND (valid_until IS NULL OR valid_until >= CURDATE())", [$cid]);
    if (portal_can('perm_invoices')) {
        $d['owing'] = (float)val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE company_id = ? AND status IN ('approved','sent')", [$cid]);
        $d['overdue'] = (int)val("SELECT COUNT(*) FROM invoices WHERE company_id = ? AND status IN ('approved','sent') AND due_on < CURDATE()", [$cid]);
    }
    if (portal_can('perm_sites')) $d['devices'] = (int)val("SELECT COUNT(*) FROM equipment e JOIN sites s ON s.id = e.site_id WHERE s.company_id = ? AND e.status <> 'retired'", [$cid]);
    pview('home', $d);
}

// ---------- tickets ----------
function p_ticket_load(int $id): array {
    [$w, $p] = portal_ticket_scope(portal_user());
    $t = one("SELECT t.*, s.name site, u.name assignee, pu.name opener, ct.first_name, ct.last_name,
                     e.category e_cat, e.make e_make, e.model e_model, e.serial e_serial, sp.title plan_title
              FROM tickets t LEFT JOIN sites s ON s.id = t.site_id LEFT JOIN users u ON u.id = t.assigned_to
              LEFT JOIN portal_users pu ON pu.id = t.portal_user_id LEFT JOIN contacts ct ON ct.id = t.contact_id
              LEFT JOIN equipment e ON e.id = t.equipment_id LEFT JOIN service_plans sp ON sp.id = t.plan_id
              WHERE t.id = ? AND $w", [$id, ...$p]);
    if (!$t) p_not_found();
    return $t;
}

function p_tickets(): void {
    $pu = portal_user();
    [$w, $p] = portal_ticket_scope($pu);
    $f = (string)get('f', 'open');
    $cond = match ($f) { 'closed' => "t.status = 'closed'", 'all' => '1=1', default => "t.status <> 'closed'" };
    $rows = all("SELECT t.*, s.name site, pu.name opener, ct.first_name, ct.last_name FROM tickets t LEFT JOIN sites s ON s.id = t.site_id
                 LEFT JOIN portal_users pu ON pu.id = t.portal_user_id LEFT JOIN contacts ct ON ct.id = t.contact_id
                 WHERE $w AND $cond ORDER BY t.status = 'closed', t.updated_at DESC LIMIT 300", $p);
    pview('tickets', ['title' => 'Tickets', 'rows' => $rows, 'f' => $f]);
}

function p_ticket_new(): void {
    $pu = portal_user(); $cid = pu_company();
    $sites = all('SELECT id, name FROM sites WHERE company_id = ? ORDER BY name', [$cid]);
    $row = ['subject' => (string)get('subject', ''), 'site_id' => int_or_null(get('site')), 'equipment_id' => int_or_null(get('equipment')), 'priority' => 'normal', 'description' => ''];
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $row = ['subject' => mb_substr((string)post('subject', ''), 0, 190), 'site_id' => int_or_null(post('site_id')), 'equipment_id' => int_or_null(post('equipment_id')),
                'priority' => (string)post('priority', 'normal'), 'description' => (string)post('description', '')];
        if ($row['subject'] === '') $errors['subject'] = 'Tell us in a few words what\'s wrong';
        if ($row['description'] === '') $errors['description'] = 'Required';
        if (!isset(TICKET_PRIORITY[$row['priority']])) $row['priority'] = 'normal';
        if ($row['site_id'] && (int)val('SELECT company_id FROM sites WHERE id = ?', [$row['site_id']]) !== $cid) $row['site_id'] = null;
        if ($row['equipment_id']) {
            $eq = one('SELECT e.site_id FROM equipment e JOIN sites s ON s.id = e.site_id WHERE e.id = ? AND s.company_id = ?', [$row['equipment_id'], $cid]);
            if (!$eq) $row['equipment_id'] = null; else $row['site_id'] ??= (int)$eq['site_id'];
        }
        if (!$errors) {
            $num = next_number('ticket');
            q('INSERT INTO tickets (number, company_id, site_id, contact_id, equipment_id, subject, description, priority, status, portal_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
              [$num, $cid, $row['site_id'], $pu['contact_id'], $row['equipment_id'], $row['subject'], $row['description'], $row['priority'], 'open', pu_id()]);
            $id = (int)db()->lastInsertId();
            p_log('system', 'ticket', $id, "Ticket $num opened in the client portal by {$pu['name']}: {$row['subject']}");
            $n = 0;
            foreach (uploaded_list() as $u) {
                if ($u['error'] === UPLOAD_ERR_NO_FILE) continue;
                [$fid] = store_upload($u, 'ticket', $id);
                if ($fid) { q('UPDATE files SET uploaded_by = NULL, portal_user_id = ?, is_public = 1 WHERE id = ?', [pu_id(), $fid]); $n++; }
            }
            $t = one('SELECT * FROM tickets WHERE id = ?', [$id]);
            $site = $row['site_id'] ? (string)val('SELECT name FROM sites WHERE id = ?', [$row['site_id']]) : '';
            $staff = [];
            foreach (portal_staff_emails($t) as $e) $staff[] = ['to' => $e, 'reply_to' => $pu['email'], 'subject' => "New portal ticket $num ({$pu['company']}): {$row['subject']}",
                'html' => '<p><b>' . h($pu['name']) . '</b> at <b>' . h($pu['company']) . '</b> opened a ticket in the client portal.</p>'
                    . '<p><b>' . h($row['subject']) . '</b><br>Priority: ' . h(TICKET_PRIORITY[$row['priority']]) . ($site ? ' · Site: ' . h($site) : '') . ($n ? " · $n file" . ($n === 1 ? '' : 's') : '') . '</p>'
                    . '<blockquote style="margin:12px 0;padding:10px 14px;background:#f3f5f7;border-left:3px solid #FF7A1A;white-space:pre-wrap">' . h($row['description']) . '</blockquote>'
                    . mail_button(app_url("/tickets/$id"), 'Open in the CRM')];
            $staff[] = ['to' => $pu['email'], 'subject' => "We got your request: $num {$row['subject']}",
                'html' => '<p>Hi ' . h(explode(' ', $pu['name'])[0]) . ',</p><p>Thanks, your ticket <b>' . h($num) . '</b> is in. We\'ll reply in the portal and by email.</p>'
                    . '<p>For anything urgent, call us at <b>' . h(setting('company_phone')) . '</b>.</p>' . mail_button(portal_url("/tickets/$id"), 'View your ticket')];
            mail_send($staff);
            flash("Ticket $num sent. We'll get back to you soon.");
            redirect("/tickets/$id");
        }
    }
    $equip = $row['equipment_id'] ? one('SELECT e.* FROM equipment e JOIN sites s ON s.id = e.site_id WHERE e.id = ? AND s.company_id = ?', [$row['equipment_id'], $cid]) : null;
    pview('ticket_new', ['title' => 'New ticket', 'row' => $row, 'errors' => $errors, 'sites' => $sites, 'equip' => $equip]);
}

/** Client-visible conversation: description, public replies (both ways) and status changes */
function p_ticket_thread(array $t): array {
    $acts = all("SELECT a.*, u.name staff, pu.name client FROM activities a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN portal_users pu ON pu.id = a.portal_user_id
                 WHERE a.entity_type = 'ticket' AND a.entity_id = ? ORDER BY a.created_at, a.id", [(int)$t['id']]);
    $out = [];
    foreach ($acts as $a) {
        $body = (string)$a['body'];
        if ($a['is_public']) {
            $out[] = ['kind' => 'msg', 'mine' => (bool)$a['portal_user_id'], 'who' => $a['portal_user_id'] ? $a['client'] : ($a['staff'] ?? setting('company_name')),
                      'uid' => $a['user_id'], 'at' => $a['created_at'], 'body' => $body];
            continue;
        }
        if (preg_match('/Status: .+ → ([^·]+)/u', $body, $m)) $out[] = ['kind' => 'status', 'at' => $a['created_at'], 'body' => 'Status changed to ' . trim(preg_replace('/\s*\(.*\)\s*$/u', '', $m[1]))];
    }
    return $out;
}

function p_ticket_show(int $id): void {
    $t = p_ticket_load($id);
    $files = all('SELECT f.*, COALESCE(pu.name, u.name) uploader FROM files f LEFT JOIN portal_users pu ON pu.id = f.portal_user_id LEFT JOIN users u ON u.id = f.uploaded_by
                  WHERE f.entity_type = ? AND f.entity_id = ? AND (f.is_public = 1 OR f.portal_user_id IS NOT NULL) ORDER BY f.is_image DESC, f.id DESC', ['ticket', $id]);
    $charges = portal_can('perm_invoices') ? ticket_charge_rows($id) : [];
    pview('ticket', ['title' => $t['number'] . ' — ' . $t['subject'], 't' => $t, 'thread' => p_ticket_thread($t), 'files' => $files,
        'charges' => $charges, 'ctot' => $charges ? ticket_charge_totals($charges) : null]);
}

function p_ticket_reply(int $id): void {
    $t = p_ticket_load($id);
    $pu = portal_user();
    if ($t['status'] === 'closed') { flash('This ticket is closed. Open a new one and we\'ll pick it up.', 'warn'); redirect("/tickets/$id"); }
    $msg = trim((string)post('message', ''));
    $n = 0; $errs = [];
    foreach (uploaded_list() as $u) {
        if ($u['error'] === UPLOAD_ERR_NO_FILE) continue;
        [$fid, $err] = store_upload($u, 'ticket', $id);
        if ($fid) { q('UPDATE files SET uploaded_by = NULL, portal_user_id = ?, is_public = 1 WHERE id = ?', [pu_id(), $fid]); $n++; } else $errs[] = $err;
    }
    if ($msg === '' && !$n) { flash($errs ? 'Not uploaded: ' . implode('; ', $errs) : 'Write a message or add a photo.', 'bad'); redirect("/tickets/$id#reply"); }
    $body = $msg !== '' ? $msg : "Added $n file" . ($n === 1 ? '' : 's');
    if ($msg !== '' && $n) $body .= " (+ $n file" . ($n === 1 ? '' : 's') . ')';
    p_log('note', 'ticket', $id, $body, true);
    // A reply to "waiting on client" puts the ball back in our court
    if ($t['status'] === 'waiting') {
        q("UPDATE tickets SET status = 'open' WHERE id = ?", [$id]);
        p_log('stage', 'ticket', $id, 'Status: ' . TICKET_STATUS['waiting'] . ' → ' . TICKET_STATUS['open'] . ' (client replied)');
    } else {
        q('UPDATE tickets SET updated_at = NOW() WHERE id = ?', [$id]);
    }
    $msgs = [];
    foreach (portal_staff_emails($t) as $e) $msgs[] = ['to' => $e, 'reply_to' => $pu['email'], 'subject' => "Client reply on {$t['number']}: {$t['subject']}",
        'html' => '<p><b>' . h($pu['name']) . '</b> (' . h($pu['company']) . ') replied in the client portal:</p>'
            . '<blockquote style="margin:12px 0;padding:10px 14px;background:#f3f5f7;border-left:3px solid #FF7A1A;white-space:pre-wrap">' . h($body) . '</blockquote>'
            . mail_button(app_url("/tickets/$id"), 'Open in the CRM')];
    mail_send($msgs);
    flash($errs ? 'Sent, but some files were not uploaded: ' . implode('; ', $errs) : 'Reply sent.', $errs ? 'warn' : 'ok');
    redirect("/tickets/$id#reply");
}

function p_ticket_summary(int $id): void {
    $t = p_ticket_load($id);
    if ($t['status'] !== 'closed') p_not_found();
    [$bytes, $fname] = ticket_summary_pdf($id, true, !portal_can('perm_invoices'));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fname . '"');
    echo $bytes;
}

function p_file(int $id, bool $thumb = false): void {
    $f = one("SELECT * FROM files WHERE id = ? AND entity_type = 'ticket' AND (is_public = 1 OR portal_user_id IS NOT NULL)", [$id]);
    if (!$f) p_not_found();
    p_ticket_load((int)$f['entity_id']);   // 404s unless the ticket is in this login's scope
    file_send($f, $thumb);
}
function p_file_thumb(int $id): void { p_file($id, true); }

// ---------- sites, plans ----------
function p_sites(): void {
    $cid = pu_company();
    $sites = all('SELECT * FROM sites WHERE company_id = ? ORDER BY name', [$cid]);
    $equip = all("SELECT e.* FROM equipment e JOIN sites s ON s.id = e.site_id WHERE s.company_id = ? AND e.status <> 'retired' ORDER BY e.category, e.make, e.model", [$cid]);
    $by = [];
    foreach ($equip as $e) $by[(int)$e['site_id']][] = $e;
    pview('sites', ['title' => 'Sites & equipment', 'sites' => $sites, 'by' => $by]);
}

function p_plans(): void {
    $plans = all('SELECT sp.*, s.name site, (SELECT MAX(t.closed_at) FROM tickets t WHERE t.plan_id = sp.id AND t.status = "closed") last_visit
                  FROM service_plans sp LEFT JOIN sites s ON s.id = sp.site_id WHERE sp.company_id = ? AND sp.active = 1 ORDER BY sp.next_due', [pu_company()]);
    pview('plans', ['title' => 'Service plans', 'plans' => $plans]);
}

// ---------- quotes ----------
function p_quote_load(int $id): array {
    $qt = one("SELECT id FROM quotes WHERE id = ? AND company_id = ? AND status <> 'draft'", [$id, pu_company()]);
    if (!$qt) p_not_found();
    return load_quote($id);
}
function p_quote_open(array $qt): bool { return $qt['status'] === 'sent' && (!$qt['valid_until'] || $qt['valid_until'] >= date('Y-m-d')); }

function p_quotes(): void {
    $rows = all("SELECT q.*, (SELECT COALESCE(SUM(qty*unit_price),0) FROM quote_items qi WHERE qi.quote_id = q.id) subtotal FROM quotes q
                 WHERE q.company_id = ? AND q.status <> 'draft' ORDER BY q.status = 'sent' DESC, q.issued_on DESC, q.id DESC", [pu_company()]);
    foreach ($rows as &$r) $r['tot'] = load_quote((int)$r['id'])[2]['total'];
    unset($r);
    pview('quotes', ['title' => 'Quotes', 'rows' => $rows]);
}

function p_quote_show(int $id): void {
    [$qt, $items, $tot] = p_quote_load($id);
    pview('quote', ['title' => $qt['number'] . ' — ' . $qt['title'], 'qt' => $qt, 'items' => $items, 'tot' => $tot, 'open' => p_quote_open($qt)]);
}

function p_quote_pdf(int $id): void {
    p_quote_load($id);
    [$bytes, $fname] = quote_pdf($id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $fname . '"');
    echo $bytes;
}

function p_quote_decide(int $id): void {
    [$qt, , $tot] = p_quote_load($id);
    $pu = portal_user();
    $st = (string)post('decision', '');
    if (!in_array($st, ['accepted', 'declined'], true)) redirect("/quotes/$id");
    if (!p_quote_open($qt)) { flash('This quote can no longer be accepted or declined here. Contact us and we\'ll help.', 'warn'); redirect("/quotes/$id"); }
    if ($st === 'accepted' && !post('authorized')) { flash('Tick the box to confirm you can approve this for ' . $pu['company'] . '.', 'bad'); redirect("/quotes/$id#decide"); }
    $raw = one('SELECT * FROM quotes WHERE id = ?', [$id]);
    quote_set_status($raw, $st, ['name' => $pu['name'], 'portal_user_id' => pu_id()]);
    $reason = trim((string)post('reason', ''));
    if ($reason !== '') p_log('note', 'quote', $id, ($st === 'accepted' ? 'Client note on acceptance: ' : 'Reason for declining: ') . $reason);
    $to = $qt['created_by'] ? array_filter([val('SELECT email FROM users WHERE id = ? AND active = 1', [$qt['created_by']])]) : [];
    if (!$to) $to = array_column(all("SELECT email FROM users WHERE role = 'admin' AND active = 1"), 'email');
    $verb = $st === 'accepted' ? 'ACCEPTED' : 'declined';
    mail_send(array_map(fn($e) => ['to' => $e, 'reply_to' => $pu['email'], 'subject' => "Quote {$qt['number']} $verb by {$pu['company']}",
        'html' => '<p><b>' . h($pu['name']) . '</b> (' . h($pu['company']) . ') <b>' . $verb . '</b> quote <b>' . h($qt['number']) . '</b> — ' . h($qt['title']) . ' (' . h(money($tot['total'])) . ' incl. tax) in the client portal.</p>'
            . ($reason !== '' ? '<blockquote style="margin:12px 0;padding:10px 14px;background:#f3f5f7;border-left:3px solid #FF7A1A;white-space:pre-wrap">' . h($reason) . '</blockquote>' : '')
            . mail_button(app_url("/quotes/$id"), 'Open the quote')], $to));
    flash($st === 'accepted' ? 'Thank you! Quote accepted. We\'ll be in touch to schedule the work.' : 'Quote declined. Thanks for letting us know.');
    redirect("/quotes/$id");
}

// ---------- invoices ----------
function p_invoices(): void {
    $cid = pu_company();
    $rows = all("SELECT * FROM invoices WHERE company_id = ? AND status IN ('approved','sent','paid') ORDER BY issued_on DESC, id DESC", [$cid]);
    $owing = 0.0; $overdue = 0.0;
    foreach ($rows as $r) if (in_array($r['status'], ['approved', 'sent'], true)) { $owing += (float)$r['total']; if (inv_overdue($r)) $overdue += (float)$r['total']; }
    pview('invoices', ['title' => 'Invoices', 'rows' => $rows, 'owing' => $owing, 'overdue' => $overdue]);
}

function p_invoice_pdf(int $id): void {
    if (!one("SELECT id FROM invoices WHERE id = ? AND company_id = ? AND status IN ('approved','sent','paid')", [$id, pu_company()])) p_not_found();
    [$bytes, $fname] = invoice_pdf($id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $fname . '"');
    echo $bytes;
}

// ---------- users (client admin) ----------
/** Perms this client admin may hand out: only what they have themselves */
function p_grantable(): array { $pu = portal_user(); return array_values(array_filter(PORTAL_ALL_PERMS, fn($p) => !empty($pu[$p]))); }

function p_users(): void {
    pview('users', ['title' => 'Portal users', 'users' => all('SELECT * FROM portal_users WHERE company_id = ? ORDER BY active DESC, name', [pu_company()])]);
}

function p_user_form(int $id = 0): void {
    $cid = pu_company();
    $u = $id ? one('SELECT * FROM portal_users WHERE id = ? AND company_id = ?', [$id, $cid]) : ['perm_tickets' => 1, 'active' => 1];
    if ($id && !$u) p_not_found();
    if ($id === pu_id()) { flash('You can\'t change your own access. Ask another admin or ' . setting('company_name') . '.', 'warn'); redirect('/users'); }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = $_POST;
        if (!$id) $in['active'] = 1;
        [$newId, $errors] = portal_save_user($cid, $in, $id ? $u : null, p_grantable(), !$id, portal_user()['name']);
        if (!$errors) {
            if ($id) p_log('system', 'company', $cid, "Portal access for {$in['name']} changed by " . portal_user()['name'] . ': ' . portal_perm_summary(one('SELECT * FROM portal_users WHERE id = ?', [$newId])));
            flash($id ? 'Saved.' : 'Login created and an invite was emailed.');
            redirect('/users');
        }
        $u = array_merge($u, array_intersect_key($_POST, array_flip(['name', 'email'])));
        foreach (PORTAL_ALL_PERMS as $p) $u[$p] = !empty($_POST[$p]) ? 1 : 0;
    }
    pview('user_form', ['title' => $id ? 'Edit ' . $u['name'] : 'Add a colleague', 'u' => $u, 'id' => $id, 'errors' => $errors, 'grantable' => p_grantable()]);
}

function p_user_invite(int $id): void {
    $u = one('SELECT * FROM portal_users WHERE id = ? AND company_id = ? AND active = 1', [$id, pu_company()]);
    if (!$u) p_not_found();
    flash(portal_send_invite($u, portal_user()['name']) ? "Invite emailed to {$u['email']}." : 'The invite could not be sent. Try again later.', 'ok');
    redirect('/users');
}
