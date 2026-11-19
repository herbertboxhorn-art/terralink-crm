<?php
declare(strict_types=1);
/**
 * Charges on tickets (v1.12): labour, travel, installs and parts from stock, with GST/PST totals,
 * a "billable / no charge" switch, a work summary PDF and a "Ready to bill" list.
 * ticket_charges.invoice_id / tickets.invoice_id are reserved for invoicing.
 */

const CHARGE_GST = 5.0;
const CHARGE_PST = 7.0;

function ticket_charge_rows(int $ticketId): array {
    return all('SELECT tc.*, u.name who FROM ticket_charges tc LEFT JOIN users u ON u.id = tc.created_by WHERE tc.ticket_id = ? ORDER BY tc.id', [$ticketId]);
}

function ticket_charge_totals(array $rows): array {
    return quote_totals($rows, CHARGE_GST, CHARGE_PST);
}

function ticket_unbilled_minutes(array $t): int {
    return max(0, (int)$t['minutes_spent'] - (int)($t['minutes_billed'] ?? 0));
}

/** Hours for a number of minutes, rounded up to the next quarter hour */
function minutes_to_hours(int $m): float {
    return ceil($m / 15) * 0.25;
}

function charge_ticket(int $id): array {
    $t = one('SELECT * FROM tickets WHERE id = ?', [$id]);
    if (!$t) not_found();
    return $t;
}

function charge_guard(array $t): void {
    if ($t['billed_at']) {
        flash('This ticket is already billed. An admin can reopen billing to change its charges.', 'warn');
        redirect("/tickets/{$t['id']}#charges");
    }
}

/** Labour price-list items billed per hour, for the "bill logged time" picker */
function charge_labour_items(): array {
    return all("SELECT id, name, sku, price FROM catalog_items WHERE active = 1 AND kind = 'labour' AND LOWER(IFNULL(unit,'')) IN ('h','hr','hrs','hour','hours') ORDER BY price, name");
}

/** All active price-list items grouped by type, for the add-charge picker */
function charge_catalog_groups(): array {
    $g = [];
    foreach (all('SELECT * FROM catalog_items WHERE active = 1 ORDER BY kind, name') as $c) {
        $k = stock_key($c);
        $c['free'] = $k ? stock_free($k) : null;
        $g[ITEM_KINDS[$c['kind']] ?? 'Other'][] = $c;
    }
    return $g;
}

/** Takes stock for a price-list item used on a ticket. Returns [consumable_id, equipment_ids, note] or throws with a message for the user. */
function charge_take_stock(array $c, float $qty, array $t): array {
    $key = stock_key($c);
    if (!$key) return [null, null, ''];
    if (str_starts_with($key, 'c:')) {
        $cid = (int)substr($key, 2);
        $con = one('SELECT * FROM consumables WHERE id = ?', [$cid]);
        if (!$con) return [null, null, ''];
        if ((float)$con['qty_on_hand'] < $qty) throw new RuntimeException('Only ' . qty_fmt($con['qty_on_hand']) . ' ' . $con['unit'] . ' of ' . $con['name'] . ' in stock. Untick "Take from stock" to charge it anyway.');
        consumable_move($cid, -$qty, 'used', $t['site_id'] ? (int)$t['site_id'] : null, (int)$t['id'], 'Ticket ' . $t['number']);
        return [$cid, null, ' Took ' . qty_fmt($qty) . ' ' . $con['unit'] . ' from stock.'];
    }
    // Equipment: whole units, installed at the ticket's site
    if (abs($qty - round($qty)) > 0.001) throw new RuntimeException('Equipment is counted in whole units. Use a whole number.');
    if (!$t['site_id']) throw new RuntimeException('Set the ticket\'s site first (Edit), so the unit can be installed there. Or untick "Take from stock".');
    [$cat, $make, $model] = explode('|', substr($key, 2), 3);
    $n = (int)round($qty);
    $ids = array_map(fn($r) => (int)$r['id'], all("SELECT id FROM equipment WHERE site_id IS NULL AND status = 'in_stock' AND reserved_quote_id IS NULL
        AND category = ? AND IFNULL(make,'') = ? AND model = ? ORDER BY purchased_at IS NULL, purchased_at, id LIMIT $n", [$cat, $make, $model]));
    if (count($ids) < $n) throw new RuntimeException('Only ' . count($ids) . ' free ' . trim($make . ' ' . $model) . ' in stock. Untick "Take from stock" to charge it anyway.');
    install_items($ids, (int)$t['site_id'], date('Y-m-d'));
    $serials = array_filter(array_map(fn($r) => $r['serial'], all('SELECT serial FROM equipment WHERE id IN (' . implode(',', $ids) . ')')));
    return [null, implode(',', $ids), ' Installed ' . $n . ' from stock' . ($serials ? ' (S/N ' . implode(', ', $serials) . ')' : '') . '.'];
}

/** Puts back whatever a charge took from stock */
function charge_return_stock(array $ch, array $t): void {
    if ($ch['consumable_id']) {
        consumable_move((int)$ch['consumable_id'], (float)$ch['qty'], 'adjust', null, (int)$t['id'], 'Returned: charge removed from ticket ' . $t['number']);
    }
    if ($ch['equipment_ids']) {
        $ids = array_map('intval', explode(',', $ch['equipment_ids']));
        foreach (all('SELECT * FROM equipment WHERE id IN (' . implode(',', $ids) . ')') as $e) {
            if ($t['site_id'] && (int)$e['site_id'] !== (int)$t['site_id']) continue;   // moved since; leave it
            q("UPDATE equipment SET site_id = NULL, status = 'in_stock', installed_at = NULL, stock_location = ? WHERE id = ?", [stock_locations()[0], $e['id']]);
            if ($e['site_id']) log_activity('system', 'site', (int)$e['site_id'], 'Back to stock (charge removed from ticket ' . $t['number'] . '): ' . equip_label($e), (int)$t['company_id']);
        }
    }
}

// ---------- handlers ----------
/** POST /tickets/{id}/charges — add from the price list or a custom line */
function h_ticket_charge_add(int $id): void {
    $t = charge_ticket($id);
    charge_guard($t);
    $qty = is_numeric(post('qty')) ? round((float)post('qty'), 2) : 1.0;
    if ($qty <= 0) { flash('Quantity must be more than 0.', 'bad'); redirect("/tickets/$id#charges"); }
    $override = post('unit_price');
    $note = '';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($cid = int_or_null(post('catalog_id'))) {
            $c = one('SELECT * FROM catalog_items WHERE id = ?', [$cid]);
            if (!$c) throw new RuntimeException('That price list item no longer exists.');
            $price = is_numeric($override) && $override !== '' ? round((float)$override, 2) : (float)$c['price'];
            [$consumable, $equip, $note] = post('take_stock') ? charge_take_stock($c, $qty, $t) : [null, null, ''];
            $desc = $c['description'] ?: $c['name'];
            q('INSERT INTO ticket_charges (ticket_id, catalog_id, kind, description, qty, unit, unit_price, pst_applies, consumable_id, equipment_ids, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
              [$id, $cid, $c['kind'], mb_substr($desc, 0, 255), $qty, $c['unit'], $price, (int)$c['pst_applies'], $consumable, $equip, uid()]);
        } else {
            $desc = trim((string)post('description'));
            if ($desc === '') throw new RuntimeException('Pick a price list item, or type a description for a custom line.');
            $kind = (string)post('kind', 'other');
            if (!is_numeric($override)) throw new RuntimeException('Enter a unit price for the custom line.');
            $price = round((float)$override, 2);
            q('INSERT INTO ticket_charges (ticket_id, kind, description, qty, unit, unit_price, pst_applies, created_by) VALUES (?,?,?,?,?,?,?,?)',
              [$id, isset(ITEM_KINDS[$kind]) ? $kind : 'other', mb_substr($desc, 0, 255), $qty, str_or_null(mb_substr((string)post('unit'), 0, 20)), $price, post('pst') ? 1 : 0, uid()]);
        }
        log_activity('system', 'ticket', $id, 'Charge added: ' . qty_fmt($qty) . ' × ' . $desc . ' @ ' . money($price) . '.' . $note, (int)$t['company_id']);
        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'bad');
        redirect("/tickets/$id#charges");
    }
    flash('Charge added.' . $note);
    redirect("/tickets/$id#charges");
}

/** POST /tickets/{id}/charges/time — turns the logged, not yet billed time into a labour line */
function h_ticket_charge_time(int $id): void {
    $t = charge_ticket($id);
    charge_guard($t);
    $mins = ticket_unbilled_minutes($t);
    if ($mins <= 0) { flash('There is no unbilled time on this ticket.', 'warn'); redirect("/tickets/$id#charges"); }
    $c = one("SELECT * FROM catalog_items WHERE id = ? AND kind = 'labour'", [int_or_null(post('catalog_id'))]);
    if (!$c) { flash('Pick the labour rate.', 'bad'); redirect("/tickets/$id#charges"); }
    $hours = minutes_to_hours($mins);
    q('INSERT INTO ticket_charges (ticket_id, catalog_id, kind, description, qty, unit, unit_price, pst_applies, minutes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$id, $c['id'], 'labour', mb_substr(($c['description'] ?: $c['name']), 0, 255), $hours, 'h', $c['price'], (int)$c['pst_applies'], $mins, uid()]);
    q('UPDATE tickets SET minutes_billed = minutes_billed + ? WHERE id = ?', [$mins, $id]);
    log_activity('system', 'ticket', $id, 'Logged time billed: ' . dur($mins) . ' → ' . qty_fmt($hours) . ' h × ' . $c['name'] . ' @ ' . money($c['price']), (int)$t['company_id']);
    flash('Added ' . qty_fmt($hours) . ' h of ' . $c['name'] . ' (' . dur($mins) . ' logged, rounded up to the quarter hour).');
    redirect("/tickets/$id#charges");
}

/** POST /tickets/{id}/charges/{cid}/delete — removes a line and puts back its stock / time */
function h_ticket_charge_delete(int $id, int $chargeId): void {
    $t = charge_ticket($id);
    charge_guard($t);
    $ch = one('SELECT * FROM ticket_charges WHERE id = ? AND ticket_id = ?', [$chargeId, $id]);
    if (!$ch) not_found();
    if ((int)$ch['created_by'] !== (int)uid() && !is_role('admin', 'sales')) forbidden();
    $pdo = db();
    $pdo->beginTransaction();
    charge_return_stock($ch, $t);
    if ($ch['minutes']) q('UPDATE tickets SET minutes_billed = GREATEST(0, CAST(minutes_billed AS SIGNED) - ?) WHERE id = ?', [(int)$ch['minutes'], $id]);
    q('DELETE FROM ticket_charges WHERE id = ?', [$chargeId]);
    log_activity('system', 'ticket', $id, 'Charge removed: ' . qty_fmt($ch['qty']) . ' × ' . $ch['description']
        . ($ch['consumable_id'] || $ch['equipment_ids'] ? ' (stock returned)' : '') . ($ch['minutes'] ? ' (time is unbilled again)' : ''), (int)$t['company_id']);
    $pdo->commit();
    flash('Charge removed.' . ($ch['consumable_id'] || $ch['equipment_ids'] ? ' Its stock was put back.' : ''));
    redirect("/tickets/$id#charges");
}

/** POST /tickets/{id}/billing — billable / no charge; mark billed / reopen */
function h_ticket_billing(int $id): void {
    $t = charge_ticket($id);
    $act = (string)post('do');
    if ($act === 'billable' || $act === 'no_charge') {
        charge_guard($t);
        q('UPDATE tickets SET billing = ? WHERE id = ?', [$act, $id]);
        log_activity('system', 'ticket', $id, $act === 'billable' ? 'Set to billable' : 'Set to no charge', (int)$t['company_id']);
        flash($act === 'billable' ? 'Ticket is billable.' : 'Ticket set to no charge. It won\'t show in Ready to bill.');
    } elseif ($act === 'billed') {
        require_role('admin', 'sales');
        if (!$t['billed_at']) {
            q('UPDATE tickets SET billed_at = NOW() WHERE id = ?', [$id]);
            $tot = ticket_charge_totals(ticket_charge_rows($id));
            log_activity('system', 'ticket', $id, 'Marked billed: ' . money($tot['total']) . ' incl. tax', (int)$t['company_id']);
        }
        flash('Marked as billed. Its charges are locked.');
    } elseif ($act === 'reopen') {
        require_role('admin');
        if ($t['invoice_id']) { flash('This ticket is on an invoice. Change it through the invoice.', 'warn'); redirect("/tickets/$id#charges"); }
        q('UPDATE tickets SET billed_at = NULL WHERE id = ?', [$id]);
        log_activity('system', 'ticket', $id, 'Billing reopened', (int)$t['company_id']);
        flash('Billing reopened. Charges can be changed again.');
    }
    redirect(post('back') === 'billing' ? '/billing' : "/tickets/$id#charges");
}

/** Called before a ticket is deleted: put back stock its charges took */
function ticket_charges_release(int $id): void {
    $t = one('SELECT * FROM tickets WHERE id = ?', [$id]);
    if (!$t) return;
    foreach (ticket_charge_rows($id) as $ch) charge_return_stock($ch, $t);
}

// ---------- work summary PDF ----------
/** $publicOnly: only notes marked visible to the client (portal copy) */
function ticket_summary_data(int $id, bool $publicOnly = false): array {
    $t = one('SELECT t.*, c.name company, c.address c_address, c.town c_town, c.province c_province, c.email c_email, c.phone c_phone,
                     s.name site, s.address s_address, s.town s_town, u.name assignee, ct.first_name, ct.last_name, sp.title plan_title
              FROM tickets t JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id LEFT JOIN users u ON u.id = t.assigned_to
              LEFT JOIN contacts ct ON ct.id = t.contact_id LEFT JOIN service_plans sp ON sp.id = t.plan_id WHERE t.id = ?', [$id]);
    if (!$t) not_found();
    $rows = ticket_charge_rows($id);
    $log = all("SELECT a.created_at, a.type, a.body, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id
                WHERE a.entity_type = 'ticket' AND a.entity_id = ? AND a.type IN ('note','call','email','visit') AND a.portal_user_id IS NULL" . ($publicOnly ? ' AND a.is_public = 1' : '') . " ORDER BY a.created_at, a.id", [$id]);
    // Keep only what the tech wrote: drop the "Status: … → …" and "+90 min" parts of quick updates
    foreach ($log as $k => &$l) {
        $parts = array_filter(explode(' · ', (string)$l['body']), fn($x) => !preg_match('/^(Status: .*|\+\d+ min)$/u', trim($x)));
        $l['body'] = trim(implode(' · ', $parts));
        if ($l['body'] === '') unset($log[$k]);
    }
    unset($l);
    return [$t, $rows, ticket_charge_totals($rows), array_values($log)];
}

function ticket_summary_pdf(int $id, bool $publicOnly = false, bool $hidePrices = false): array {
    [$t, $rows, $tot, $log] = ticket_summary_data($id, $publicOnly);
    if ($hidePrices) $rows = [];
    ob_start();
    view('ticket_pdf', ['t' => $t, 'rows' => $rows, 'tot' => $tot, 'log' => $log], false);
    $html = ob_get_clean();
    $opts = new \Dompdf\Options();
    $opts->set('isRemoteEnabled', false);
    $opts->set('defaultFont', 'Helvetica');
    $opts->setChroot(APP_ROOT . '/public');
    $pdf = new \Dompdf\Dompdf($opts);
    $pdf->loadHtml($html);
    $pdf->setPaper('letter');
    $pdf->render();
    $fname = preg_replace('/[^A-Za-z0-9_-]+/', '-', 'Work-summary-' . $t['number'] . '-' . $t['company']) . '.pdf';
    return [$pdf->output(), $fname];
}

function h_ticket_summary(int $id): void {
    [$bytes, $fname] = ticket_summary_pdf($id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $fname . '"');
    echo $bytes;
}

// ---------- ready to bill ----------
function h_billing(): void {
    require_role('admin', 'sales');
    $tab = get('tab') === 'billed' ? 'billed' : 'ready';
    $base = "SELECT t.id, t.number, t.subject, t.status, t.closed_at, t.billed_at, t.minutes_spent, t.minutes_billed, t.company_id, c.name company, s.name site,
                    (SELECT COUNT(*) FROM ticket_charges x WHERE x.ticket_id = t.id) n,
                    (SELECT COALESCE(SUM(ROUND(x.qty * x.unit_price, 2)), 0) FROM ticket_charges x WHERE x.ticket_id = t.id) subtotal,
                    (SELECT COALESCE(SUM(ROUND(x.qty * x.unit_price, 2)), 0) FROM ticket_charges x WHERE x.ticket_id = t.id AND x.pst_applies = 1) pst_base
             FROM tickets t JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id";
    $rows = $tab === 'ready'
        ? all("$base WHERE t.billing = 'billable' AND t.billed_at IS NULL AND t.status = 'closed'
               AND (EXISTS (SELECT 1 FROM ticket_charges x WHERE x.ticket_id = t.id) OR t.minutes_spent > t.minutes_billed)
               ORDER BY c.name, t.closed_at")
        : all("$base WHERE t.billed_at IS NOT NULL ORDER BY t.billed_at DESC LIMIT 200");
    foreach ($rows as &$r) {
        $g = round((float)$r['subtotal'] * CHARGE_GST / 100, 2); $p = round((float)$r['pst_base'] * CHARGE_PST / 100, 2);
        $r['total'] = (float)$r['subtotal'] + $g + $p;
        $r['unbilled'] = max(0, (int)$r['minutes_spent'] - (int)$r['minutes_billed']);
    }
    unset($r);
    $open = (int)val("SELECT COUNT(*) FROM tickets t WHERE t.billing = 'billable' AND t.billed_at IS NULL AND t.status <> 'closed'
                      AND EXISTS (SELECT 1 FROM ticket_charges x WHERE x.ticket_id = t.id)");
    view('billing', ['title' => 'Billing', 'rows' => $rows, 'tab' => $tab, 'openWithCharges' => $open]);
}

/** POST /billing/mark — mark several tickets billed at once */
function h_billing_mark(): void {
    require_role('admin', 'sales');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $n = 0;
    foreach ($ids as $id) {
        $t = one("SELECT * FROM tickets WHERE id = ? AND billed_at IS NULL AND billing = 'billable'", [$id]);
        if (!$t) continue;
        q('UPDATE tickets SET billed_at = NOW() WHERE id = ?', [$id]);
        $tot = ticket_charge_totals(ticket_charge_rows($id));
        log_activity('system', 'ticket', $id, 'Marked billed: ' . money($tot['total']) . ' incl. tax', (int)$t['company_id']);
        $n++;
    }
    flash($n ? "Marked $n ticket" . ($n === 1 ? '' : 's') . ' as billed.' : 'Tick the tickets to mark as billed.', $n ? 'ok' : 'warn');
    redirect('/billing');
}
