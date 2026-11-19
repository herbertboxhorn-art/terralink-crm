<?php
declare(strict_types=1);
/**
 * Invoices (v1.13). On the 1st of each month the daily job turns every company's ready-to-bill tickets
 * (closed, billable, not billed) plus its service plan fees for the coming month into a DRAFT invoice.
 * Herb reviews each draft, then "Approve & email" gives it a number and emails the PDF to the client.
 */

const INVOICE_STATUS = ['draft' => 'Draft', 'approved' => 'Approved, not sent', 'sent' => 'Sent', 'paid' => 'Paid', 'void' => 'Void'];
const PAY_METHODS = ['etransfer' => 'Interac e-Transfer', 'eft' => 'EFT / direct deposit', 'card' => 'Credit card'];

function inv_terms_default(): int { return max(0, (int)setting('invoice_terms_days', '15')); }

function inv_bill_to(array $company): string {
    if (!empty($company['billing_email'])) return (string)$company['billing_email'];
    if (!empty($company['email'])) return (string)$company['email'];
    return (string)(val("SELECT email FROM contacts WHERE company_id = ? AND email IS NOT NULL AND email <> '' ORDER BY is_primary DESC, id LIMIT 1", [$company['id']]) ?? '');
}

function inv_month_label(string $ym): string { return date('F Y', strtotime($ym . '-01')); }

/** Recalculates and stores an invoice's totals */
function invoice_recalc(int $id): array {
    $inv = one('SELECT * FROM invoices WHERE id = ?', [$id]);
    $tot = quote_totals(all('SELECT * FROM invoice_items WHERE invoice_id = ?', [$id]), (float)$inv['gst_rate'], (float)$inv['pst_rate']);
    q('UPDATE invoices SET subtotal = ?, gst = ?, pst = ?, total = ? WHERE id = ?', [$tot['subtotal'], $tot['gst'], $tot['pst'], $tot['total'], $id]);
    return $tot;
}

function inv_log(int $invId, int $companyId, string $body): void {
    log_activity('system', 'invoice', $invId, $body, $companyId);
}

/** Labour item used when logged time is billed automatically: managed rate for clients on a paid plan, else field rate */
function inv_auto_labour_item(int $companyId): ?array {
    $managed = (int)val('SELECT COUNT(*) FROM service_plans WHERE company_id = ? AND active = 1 AND fee > 0', [$companyId]) > 0;
    foreach ($managed ? ['LAB-MANAGED', 'LAB-FIELD'] : ['LAB-FIELD'] as $sku) {
        $c = one("SELECT * FROM catalog_items WHERE sku = ? AND active = 1", [$sku]);
        if ($c) return $c;
    }
    return one("SELECT * FROM catalog_items WHERE active = 1 AND kind = 'labour' AND LOWER(IFNULL(unit,'')) IN ('h','hr','hour','hours') ORDER BY price DESC LIMIT 1") ?: null;
}

/** The company's open draft for a month, or a new one */
function inv_draft_for(array $company, string $period): int {
    $id = val("SELECT id FROM invoices WHERE company_id = ? AND status = 'draft' AND period = ? ORDER BY id LIMIT 1", [$company['id'], $period]);
    if ($id) return (int)$id;
    q('INSERT INTO invoices (company_id, status, period, terms_days, bill_to_email, gst_rate, pst_rate) VALUES (?,?,?,?,?,?,?)', [
        $company['id'], 'draft', $period, $company['payment_terms_days'] !== null ? (int)$company['payment_terms_days'] : inv_terms_default(),
        str_or_null(inv_bill_to($company)), CHARGE_GST, CHARGE_PST]);
    $nid = (int)db()->lastInsertId();
    inv_log($nid, (int)$company['id'], 'Draft invoice created for ' . inv_month_label($period));
    return $nid;
}

/** Moves one ready ticket onto a draft invoice (auto-billing its logged time first). Returns lines added. */
function inv_add_ticket(int $invId, array $t): int {
    $mins = ticket_unbilled_minutes($t);
    if ($mins > 0 && ($lab = inv_auto_labour_item((int)$t['company_id']))) {
        q('INSERT INTO ticket_charges (ticket_id, catalog_id, kind, description, qty, unit, unit_price, pst_applies, minutes, created_by) VALUES (?,?,?,?,?,?,?,?,?,NULL)',
          [$t['id'], $lab['id'], 'labour', mb_substr($lab['description'] ?: $lab['name'], 0, 255), minutes_to_hours($mins), 'h', $lab['price'], (int)$lab['pst_applies'], $mins]);
        $autoId = (int)db()->lastInsertId();
        q('UPDATE tickets SET minutes_billed = minutes_billed + ? WHERE id = ?', [$mins, $t['id']]);
        log_activity('system', 'ticket', (int)$t['id'], 'Logged time billed automatically at month end: ' . dur($mins) . ' → ' . qty_fmt(minutes_to_hours($mins)) . ' h × ' . $lab['name'], (int)$t['company_id']);
    }
    $pos = (int)val('SELECT COALESCE(MAX(position), 0) FROM invoice_items WHERE invoice_id = ?', [$invId]);
    $n = 0;
    $autoId ??= 0;
    foreach (all('SELECT * FROM ticket_charges WHERE ticket_id = ? AND invoice_id IS NULL ORDER BY id', [$t['id']]) as $c) {
        q('INSERT INTO invoice_items (invoice_id, position, kind, description, qty, unit, unit_price, pst_applies, ticket_id, charge_id, auto_added) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
          [$invId, ++$pos, $c['kind'], $c['description'], $c['qty'], $c['unit'], $c['unit_price'], $c['pst_applies'], $t['id'], $c['id'], (int)($autoId && $autoId === (int)$c['id'])]);
        q('UPDATE ticket_charges SET invoice_id = ? WHERE id = ?', [$invId, $c['id']]);
        $n++;
    }
    if ($n) q('UPDATE tickets SET invoice_id = ?, billed_at = NOW() WHERE id = ?', [$invId, $t['id']]);
    return $n;
}

/**
 * Builds/extends draft invoices: ready tickets (any close date) + plan fees for $period (billed in advance).
 * Safe to run again: tickets already on an invoice and plan months already billed are skipped.
 */
function invoices_generate(string $period): array {
    $made = []; $lines = 0;
    $companies = all("SELECT DISTINCT c.* FROM companies c WHERE c.id IN (
            SELECT t.company_id FROM tickets t WHERE t.billing = 'billable' AND t.status = 'closed' AND t.billed_at IS NULL AND t.invoice_id IS NULL
              AND (t.minutes_spent > t.minutes_billed OR EXISTS (SELECT 1 FROM ticket_charges x WHERE x.ticket_id = t.id AND x.invoice_id IS NULL))
            UNION SELECT p.company_id FROM service_plans p WHERE p.active = 1 AND p.fee > 0)
        ORDER BY c.name");
    foreach ($companies as $co) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $invId = null;
            // Plan fees for the month, in advance
            foreach (all('SELECT p.*, s.name site FROM service_plans p LEFT JOIN sites s ON s.id = p.site_id WHERE p.company_id = ? AND p.active = 1 AND p.fee > 0 ORDER BY p.title', [$co['id']]) as $p) {
                $done = (int)val("SELECT COUNT(*) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.plan_id = ? AND ii.plan_period = ? AND i.status <> 'void'", [$p['id'], $period]);
                if ($done) continue;
                $invId ??= inv_draft_for($co, $period);
                $pos = (int)val('SELECT COALESCE(MAX(position), 0) FROM invoice_items WHERE invoice_id = ?', [$invId]);
                q('INSERT INTO invoice_items (invoice_id, position, kind, description, qty, unit, unit_price, pst_applies, plan_id, plan_period) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                    $invId, $pos + 1, 'other', mb_substr('Service plan: ' . $p['title'] . ($p['site'] ? ' — ' . $p['site'] : '') . ' — ' . inv_month_label($period), 0, 255),
                    1, 'mo', round((float)$p['fee'] / max(1, (int)$p['every_months']), 2), 0, $p['id'], $period]);
                $lines++;
            }
            // Ready-to-bill tickets
            foreach (all("SELECT * FROM tickets WHERE company_id = ? AND billing = 'billable' AND status = 'closed' AND billed_at IS NULL AND invoice_id IS NULL ORDER BY closed_at, id", [$co['id']]) as $t) {
                if (ticket_unbilled_minutes($t) <= 0 && !val('SELECT 1 FROM ticket_charges WHERE ticket_id = ? AND invoice_id IS NULL LIMIT 1', [$t['id']])) continue;
                $invId ??= inv_draft_for($co, $period);
                $lines += inv_add_ticket($invId, $t);
            }
            if ($invId) { invoice_recalc($invId); $made[$invId] = true; }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    $ids = array_keys($made);
    $total = $ids ? (float)val('SELECT COALESCE(SUM(total), 0) FROM invoices WHERE id IN (' . implode(',', $ids) . ')') : 0.0;
    return ['invoices' => count($ids), 'lines' => $lines, 'total' => $total];
}

/** Daily job hook: once per month (first run on/after the 1st) build drafts and tell Herb */
function invoices_monthly(bool $send = true): ?array {
    $month = date('Y-m');
    if ((string)val("SELECT v FROM settings WHERE k = 'invoice_last_month'") === $month) return null;   // not setting(): it caches per request
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', ['invoice_last_month', $month]);
    $r = invoices_generate($month);
    $to = setting('invoice_review_email') ?: (string)val("SELECT email FROM users WHERE active = 1 AND role = 'admin' ORDER BY id LIMIT 1");
    $drafts = all("SELECT i.*, c.name company FROM invoices i JOIN companies c ON c.id = i.company_id WHERE i.status = 'draft' ORDER BY c.name");
    if ($send && $to && $drafts) {
        $url = rtrim((string)env('APP_URL', ''), '/') . '/invoices';
        $rows = implode('', array_map(fn($d) => '<tr><td style="padding:4px 10px 4px 0">' . h($d['company']) . '</td><td style="padding:4px 0;text-align:right">' . h(money($d['total'])) . '</td></tr>', $drafts));
        mail_send([['to' => $to, 'subject' => count($drafts) . ' draft invoice' . (count($drafts) === 1 ? '' : 's') . ' ready to review — ' . inv_month_label($month),
            'html' => '<p>The month-end invoices are ready. Nothing has been sent to clients yet.</p><table>' . $rows . '</table>'
                . '<p><b>Total: ' . h(money(array_sum(array_column($drafts, 'total')))) . '</b> incl. tax</p>'
                . '<p><a href="' . h($url) . '">Review and approve them in the CRM</a>. Each one is emailed to the client when you approve it.</p>']]);
    }
    return $r + ['drafts' => count($drafts), 'notified' => $to];
}

// ---------- loading ----------
function load_invoice(int $id): array {
    $inv = one('SELECT i.*, c.name company, c.address c_address, c.town c_town, c.province c_province, c.email c_email, c.phone c_phone,
                       u.name approver FROM invoices i JOIN companies c ON c.id = i.company_id LEFT JOIN users u ON u.id = i.approved_by WHERE i.id = ?', [$id]);
    if (!$inv) not_found();
    $items = all('SELECT ii.*, t.number t_number, t.subject t_subject, t.closed_at t_closed, s.name t_site
                  FROM invoice_items ii LEFT JOIN tickets t ON t.id = ii.ticket_id LEFT JOIN sites s ON s.id = t.site_id
                  WHERE ii.invoice_id = ? ORDER BY ii.plan_id IS NULL, ii.ticket_id IS NULL, t.closed_at, ii.ticket_id, ii.position, ii.id', [$id]);
    // groups: plans, each ticket, other lines
    $groups = [];
    foreach ($items as $it) {
        $k = $it['plan_id'] ? 'plans' : ($it['ticket_id'] ? 't' . $it['ticket_id'] : 'other');
        $groups[$k] ??= ['key' => $k, 'ticket_id' => $it['ticket_id'], 'title' => $k === 'plans' ? 'Service plans' : ($k === 'other' ? 'Other charges and credits' : $it['t_number'] . ' — ' . $it['t_subject']),
                         'sub' => $it['ticket_id'] ? trim(($it['t_site'] ?? '') . ($it['t_closed'] ? ($it['t_site'] ? ' · ' : '') . 'completed ' . fdate($it['t_closed']) : '')) : '', 'items' => []];
        $groups[$k]['items'][] = $it;
    }
    $tot = quote_totals($items, (float)$inv['gst_rate'], (float)$inv['pst_rate']);
    return [$inv, array_values($groups), $tot, $items];
}

function inv_overdue(array $inv): bool {
    return in_array($inv['status'], ['approved', 'sent'], true) && $inv['due_on'] && $inv['due_on'] < date('Y-m-d');
}

function inv_label(array $inv): string { return $inv['number'] ?: 'Draft #' . $inv['id']; }

// ---------- pages ----------
function h_invoices(): void {
    require_role('admin', 'sales');
    $f = (string)get('f', 'draft');
    $where = match ($f) {
        'open'    => "i.status IN ('approved','sent')",
        'overdue' => "i.status IN ('approved','sent') AND i.due_on < CURDATE()",
        'paid'    => "i.status = 'paid'",
        'void'    => "i.status = 'void'",
        'all'     => '1',
        default   => "i.status = 'draft'",
    };
    if (!in_array($f, ['draft', 'open', 'overdue', 'paid', 'void', 'all'], true)) $f = 'draft';
    $rows = all("SELECT i.*, c.name company, (SELECT COUNT(DISTINCT ii.ticket_id) FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.ticket_id IS NOT NULL) tickets,
                        (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.auto_added = 1) auto
                 FROM invoices i JOIN companies c ON c.id = i.company_id WHERE $where ORDER BY i.status = 'draft' DESC, c.name, i.id DESC LIMIT 500");
    $counts = array_column(all("SELECT status, COUNT(*) n, SUM(total) t FROM invoices GROUP BY status"), null, 'status');
    $owing = (float)val("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status IN ('approved','sent')");
    $overdue = (int)val("SELECT COUNT(*) FROM invoices WHERE status IN ('approved','sent') AND due_on < CURDATE()");
    $ready = (int)val("SELECT COUNT(*) FROM tickets t WHERE t.billing = 'billable' AND t.status = 'closed' AND t.billed_at IS NULL AND t.invoice_id IS NULL
                       AND (t.minutes_spent > t.minutes_billed OR EXISTS (SELECT 1 FROM ticket_charges x WHERE x.ticket_id = t.id))");
    view('invoices', ['title' => 'Invoices', 'rows' => $rows, 'f' => $f, 'counts' => $counts, 'owing' => $owing, 'overdue' => $overdue, 'ready' => $ready,
        'nextRun' => date('M j', strtotime('first day of next month'))]);
}

function h_invoice_show(int $id): void {
    require_role('admin', 'sales');
    [$inv, $groups, $tot] = load_invoice($id);
    $timeline = all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'invoice' AND a.entity_id = ? ORDER BY a.created_at DESC, a.id DESC", [$id]);
    view('invoice', ['title' => inv_label($inv) . ' — ' . $inv['company'], 'inv' => $inv, 'groups' => $groups, 'tot' => $tot, 'timeline' => $timeline]);
}

function inv_draft_or_back(int $id): array {
    require_role('admin', 'sales');
    $inv = one('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$inv) not_found();
    if ($inv['status'] !== 'draft') { flash('Only drafts can be changed. Void it and re-run if it needs to change.', 'warn'); redirect("/invoices/$id"); }
    return $inv;
}

/** POST /invoices/{id}/edit — bill-to email, terms, notes (drafts) */
function h_invoice_edit(int $id): void {
    $inv = inv_draft_or_back($id);
    $email = trim((string)post('bill_to_email'));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('That billing email doesn\'t look right.', 'bad'); redirect("/invoices/$id"); }
    $terms = max(0, min(120, (int)post('terms_days', $inv['terms_days'])));
    q('UPDATE invoices SET bill_to_email = ?, terms_days = ?, notes = ? WHERE id = ?', [str_or_null($email), $terms, str_or_null((string)post('notes')), $id]);
    flash('Invoice details saved.');
    redirect("/invoices/$id");
}

/** POST /invoices/{id}/lines — add a custom line to a draft */
function h_invoice_line_add(int $id): void {
    $inv = inv_draft_or_back($id);
    $desc = trim((string)post('description'));
    $price = post('unit_price');
    $qty = is_numeric(post('qty')) ? (float)post('qty') : 1;
    if ($desc === '' || !is_numeric($price)) { flash('Enter a description and a unit price.', 'bad'); redirect("/invoices/$id#lines"); }
    $kind = (string)post('kind', 'other');
    $pos = (int)val('SELECT COALESCE(MAX(position), 0) FROM invoice_items WHERE invoice_id = ?', [$id]);
    q('INSERT INTO invoice_items (invoice_id, position, kind, description, qty, unit, unit_price, pst_applies) VALUES (?,?,?,?,?,?,?,?)',
      [$id, $pos + 1, isset(ITEM_KINDS[$kind]) ? $kind : 'other', mb_substr($desc, 0, 255), $qty, str_or_null(mb_substr((string)post('unit'), 0, 20)), round((float)$price, 2), post('pst') ? 1 : 0]);
    invoice_recalc($id);
    inv_log($id, (int)$inv['company_id'], 'Line added: ' . qty_fmt($qty) . ' × ' . $desc . ' @ ' . money($price));
    flash('Line added.');
    redirect("/invoices/$id#lines");
}

/** Puts a ticket back to "Ready to bill" and takes its lines off the invoice */
function inv_release_ticket(int $invId, int $ticketId): void {
    q('DELETE FROM invoice_items WHERE invoice_id = ? AND ticket_id = ?', [$invId, $ticketId]);
    q('UPDATE ticket_charges SET invoice_id = NULL WHERE ticket_id = ? AND invoice_id = ?', [$ticketId, $invId]);
    q('UPDATE tickets SET invoice_id = NULL, billed_at = NULL WHERE id = ? AND invoice_id = ?', [$ticketId, $invId]);
}

/** POST /invoices/{id}/lines/{lid}/delete — a ticket line takes its whole ticket off; a plan or custom line is just removed */
function h_invoice_line_delete(int $id, int $lineId): void {
    $inv = inv_draft_or_back($id);
    $ln = one('SELECT * FROM invoice_items WHERE id = ? AND invoice_id = ?', [$lineId, $id]);
    if (!$ln) not_found();
    if ($ln['ticket_id']) {
        $tn = (string)val('SELECT number FROM tickets WHERE id = ?', [$ln['ticket_id']]);
        inv_release_ticket($id, (int)$ln['ticket_id']);
        log_activity('system', 'ticket', (int)$ln['ticket_id'], 'Taken off draft invoice; back in Ready to bill', (int)$inv['company_id']);
        inv_log($id, (int)$inv['company_id'], "Ticket $tn taken off this invoice");
        flash("Ticket $tn is off this invoice and back in Ready to bill. Fix it there; it goes on the next invoice run, or use \"Make drafts now\".");
    } else {
        q('DELETE FROM invoice_items WHERE id = ?', [$lineId]);
        inv_log($id, (int)$inv['company_id'], 'Line removed: ' . $ln['description']);
        flash('Line removed.' . ($ln['plan_id'] ? ' That plan month is not billed now.' : ''));
    }
    invoice_recalc($id);
    redirect("/invoices/$id#lines");
}

/** POST /invoices/generate — run month-end now (adds ready tickets and this month's plan fees to drafts) */
function h_invoices_generate(): void {
    require_role('admin');
    $r = invoices_generate(date('Y-m'));
    flash($r['invoices'] ? "Drafts updated: {$r['invoices']} invoice" . ($r['invoices'] === 1 ? '' : 's') . ", {$r['lines']} new line" . ($r['lines'] === 1 ? '' : 's') . '. Review them below.' : 'Nothing new to invoice.');
    redirect('/invoices');
}

/** Approves a draft (gives it a number and dates) and emails it. Returns [ok, message]. */
function invoice_approve_send(int $id): array {
    $inv = one('SELECT i.*, c.name company FROM invoices i JOIN companies c ON c.id = i.company_id WHERE i.id = ?', [$id]);
    if (!$inv || !in_array($inv['status'], ['draft', 'approved'], true)) return [false, 'Not a draft.'];
    if (!(int)val('SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?', [$id])) return [false, $inv['company'] . ': the invoice has no lines.'];
    $to = trim((string)$inv['bill_to_email']);
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, $inv['company'] . ': add a billing email first (on the invoice, or on the company).'];
    if ($inv['status'] === 'draft') {
        $pdo = db();
        $pdo->beginTransaction();
        $num = next_number('invoice');
        $issued = date('Y-m-d');
        q("UPDATE invoices SET status = 'approved', number = ?, issued_on = ?, due_on = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
          [$num, $issued, date('Y-m-d', strtotime("+{$inv['terms_days']} days")), uid(), $id]);
        invoice_recalc($id);
        $pdo->commit();
        inv_log($id, (int)$inv['company_id'], "Approved as $num");
    }
    [$inv] = load_invoice($id);
    [$bytes, $fname] = invoice_pdf($id);
    $co = setting('company_name', 'TerraLink Systems');
    $pay = setting('payment_email', 'payment@terralinksystems.com');
    $html = '<p>Hello,</p><p>Please find attached invoice <b>' . h($inv['number']) . '</b> from ' . h($co) . ' for <b>' . h(money($inv['total'])) . ' CAD</b>, due <b>'
        . h(fdate($inv['due_on'], 'F j, Y')) . '</b>.</p><p><b>How to pay</b><br>Interac e-Transfer to <b>' . h($pay) . '</b> (please put ' . h($inv['number']) . ' in the message)'
        . (setting('eft_details') ? '<br>EFT / direct deposit: ' . nl2br(h(setting('eft_details')), false) : '<br>EFT / direct deposit: reply to this email for our banking details')
        . (setting('cc_details') ? '<br>Credit card: ' . nl2br(h(setting('cc_details')), false) : '<br>Credit card: call us at ' . h(setting('company_phone')))
        . '<br>We don\'t accept cheques.</p><p>Thank you for your business.<br>' . h($co) . (setting('company_phone') ? ' · ' . h(setting('company_phone')) : '') . '</p>';
    $ok = mail_send([['to' => $to, 'subject' => "Invoice {$inv['number']} from $co — due " . fdate($inv['due_on'], 'M j'), 'html' => $html,
        'reply_to' => setting('payment_email') ?: (user()['email'] ?? ''), 'attachment' => ['name' => $fname, 'mime' => 'application/pdf', 'data' => $bytes]]]);
    if (!$ok) {
        inv_log($id, (int)$inv['company_id'], "Email to $to failed");
        return [false, "{$inv['number']} is approved but the email to $to failed. See Settings → Recent emails, then press Send."];
    }
    q("UPDATE invoices SET status = 'sent', sent_at = NOW(), sent_to = ? WHERE id = ?", [$to, $id]);
    inv_log($id, (int)$inv['company_id'], "Emailed to $to ($fname)");
    return [true, "{$inv['number']} emailed to $to."];
}

function h_invoice_approve(int $id): void {
    require_role('admin', 'sales');
    [$ok, $msg] = invoice_approve_send($id);
    flash($msg, $ok ? 'ok' : 'bad');
    redirect("/invoices/$id");
}

/** POST /invoices/approve — approve & email the ticked drafts */
function h_invoices_approve(): void {
    require_role('admin', 'sales');
    $ok = []; $bad = [];
    foreach (array_filter(array_map('intval', (array)($_POST['ids'] ?? []))) as $id) {
        [$good, $msg] = invoice_approve_send($id);
        if ($good) $ok[] = $msg; else $bad[] = $msg;
    }
    if (!$ok && !$bad) flash('Tick the drafts to approve.', 'warn');
    else flash(($ok ? count($ok) . ' invoice' . (count($ok) === 1 ? '' : 's') . ' approved and emailed. ' : '') . implode(' ', $bad), $bad ? 'warn' : 'ok');
    redirect('/invoices' . ($bad ? '' : '?f=open'));
}

function h_invoice_paid(int $id): void {
    require_role('admin', 'sales');
    $inv = one('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$inv) not_found();
    if (!in_array($inv['status'], ['approved', 'sent'], true)) redirect("/invoices/$id");
    $date = (string)post('paid_on', date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $m = (string)post('paid_method', 'etransfer');
    if (!isset(PAY_METHODS[$m])) $m = 'etransfer';
    q("UPDATE invoices SET status = 'paid', paid_on = ?, paid_method = ? WHERE id = ?", [$date, $m, $id]);
    inv_log($id, (int)$inv['company_id'], 'Paid ' . fdate($date) . ' by ' . PAY_METHODS[$m] . ' (' . money($inv['total']) . ')');
    flash($inv['number'] . ' marked paid.');
    redirect("/invoices/$id");
}

/** POST /invoices/{id}/void — cancels a sent invoice (tickets go back to Ready to bill) or deletes a draft */
function h_invoice_void(int $id): void {
    require_role('admin');
    $inv = one('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$inv) not_found();
    $pdo = db();
    $pdo->beginTransaction();
    foreach (all('SELECT DISTINCT ticket_id FROM invoice_items WHERE invoice_id = ? AND ticket_id IS NOT NULL', [$id]) as $r) inv_release_ticket($id, (int)$r['ticket_id']);
    if ($inv['status'] === 'draft') {
        q('DELETE FROM activities WHERE entity_type = ? AND entity_id = ?', ['invoice', $id]);
        q('DELETE FROM invoices WHERE id = ?', [$id]);
        $pdo->commit();
        flash('Draft deleted. Its tickets are back in Ready to bill.');
        redirect('/invoices');
    }
    if ($inv['status'] === 'paid') { $pdo->rollBack(); flash('A paid invoice can\'t be voided.', 'bad'); redirect("/invoices/$id"); }
    q("UPDATE invoices SET status = 'void' WHERE id = ?", [$id]);
    inv_log($id, (int)$inv['company_id'], 'Voided. Its tickets are back in Ready to bill.');
    $pdo->commit();
    flash($inv['number'] . ' voided. Its tickets are back in Ready to bill; let the client know it\'s cancelled.');
    redirect("/invoices/$id");
}

// ---------- PDF ----------
function invoice_pdf(int $id): array {
    [$inv, $groups, $tot] = load_invoice($id);
    ob_start();
    view('invoice_pdf', ['inv' => $inv, 'groups' => $groups, 'tot' => $tot], false);
    $html = ob_get_clean();
    $opts = new \Dompdf\Options();
    $opts->set('isRemoteEnabled', false);
    $opts->set('defaultFont', 'Helvetica');
    $opts->setChroot(APP_ROOT . '/public');
    $pdf = new \Dompdf\Dompdf($opts);
    $pdf->loadHtml($html);
    $pdf->setPaper('letter');
    $pdf->render();
    $fname = preg_replace('/[^A-Za-z0-9_-]+/', '-', inv_label($inv) . '-' . $inv['company']) . '.pdf';
    return [$pdf->output(), $fname];
}

function h_invoice_pdf(int $id): void {
    require_role('admin', 'sales');
    [$bytes, $fname] = invoice_pdf($id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $fname . '"');
    echo $bytes;
}
