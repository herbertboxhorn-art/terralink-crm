<?php
// Consumables: count-only stock (cable, connectors, mounts…) with a movement log.

function h_consumable_form(int $id = 0): void {
    $def = entity_def('consumable');
    $row = $id ? one('SELECT * FROM consumables WHERE id = ?', [$id]) : ['unit' => 'ea', 'category' => 'Cable', 'location' => stock_locations()[0]];
    if ($id && !$row) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        $opening = trim((string)post('opening_qty', ''));
        if (!$id && $opening !== '' && (!is_numeric($opening) || (float)$opening < 0)) $errors['opening_qty'] = 'Must be 0 or more';
        if (!$errors) {
            if ($vals['reorder_level'] === null || $vals['reorder_level'] === '') $vals['reorder_level'] = null;
            if ((float)($vals['unit_cost'] ?? 0) == 0.0) $vals['unit_cost'] = null;
            $newId = save_entity($def, $vals, $id ?: null);
            if (!$id && $opening !== '' && (float)$opening > 0) consumable_move($newId, (float)$opening, 'received', null, null, 'Opening stock');
            flash($id ? 'Saved.' : 'Consumable added.');
            redirect("/consumables/$newId");
        }
        $row = array_merge($row, $vals);
    }
    view('form', ['title' => $id ? 'Edit consumable' : 'New consumable', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/consumables/$id" : '/equipment?tab=consumables', 'deleteUrl' => $id ? "/consumables/$id/delete" : null,
        'extraField' => $id ? null : ['opening_qty', 'Quantity on hand now', $_POST['opening_qty'] ?? '']]);
}

function h_consumable_show(int $id): void {
    $c = one('SELECT * FROM consumables WHERE id = ?', [$id]);
    if (!$c) not_found();
    view('consumable', [
        'title' => $c['name'], 'c' => $c,
        'res' => all('SELECT r.qty, q.id, q.number, q.title, q.status FROM consumable_reservations r JOIN quotes q ON q.id = r.quote_id WHERE r.consumable_id = ? ORDER BY q.id', [$id]),
        'moves' => all('SELECT m.*, u.name who, s.name site, s.id sid, co.name company, t.number ticket
                        FROM consumable_moves m LEFT JOIN users u ON u.id = m.user_id LEFT JOIN sites s ON s.id = m.site_id
                        LEFT JOIN companies co ON co.id = s.company_id LEFT JOIN tickets t ON t.id = m.ticket_id
                        WHERE m.consumable_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT 200', [$id]),
        'sites' => all('SELECT s.id, s.name, c.name company FROM sites s JOIN companies c ON c.id = s.company_id ORDER BY c.name, s.name'),
    ]);
}

/** Applies one stock movement and logs usage on the site timeline. Returns the new on-hand quantity. */
function consumable_move(int $cid, float $change, string $reason, ?int $siteId, ?int $ticketId, ?string $note): float {
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $c = one('SELECT * FROM consumables WHERE id = ? FOR UPDATE', [$cid]);
        if (!$c) throw new RuntimeException('Consumable not found');
        q('UPDATE consumables SET qty_on_hand = qty_on_hand + ? WHERE id = ?', [$change, $cid]);
        q('INSERT INTO consumable_moves (consumable_id, qty_change, reason, site_id, ticket_id, note, user_id) VALUES (?,?,?,?,?,?,?)',
          [$cid, $change, $reason, $siteId, $ticketId, $note, uid()]);
        if ($reason === 'used' && $siteId) {
            $site = one('SELECT company_id FROM sites WHERE id = ?', [$siteId]);
            log_activity('system', 'site', $siteId, 'Used ' . qty_fmt(abs($change)) . ' ' . $c['unit'] . ' of ' . $c['name'] . ($note ? " — $note" : ''), $site ? (int)$site['company_id'] : null);
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return (float)val('SELECT qty_on_hand FROM consumables WHERE id = ?', [$cid]);
}

function qty_fmt(mixed $n): string { return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.'); }

/** Receive / use / adjust from the consumable page, or "use" from a site page */
function h_consumable_move(int $id = 0): void {
    $id = $id ?: (int)post('consumable_id', 0);
    $c = one('SELECT * FROM consumables WHERE id = ?', [$id]);
    if (!$c) not_found();
    $reason = (string)post('reason', 'used');
    if (!isset(MOVE_REASONS[$reason])) $reason = 'used';
    $qtyRaw = trim((string)post('qty', ''));
    $back = (string)post('back', "/consumables/$id");
    if (!str_starts_with($back, '/') || str_starts_with($back, '//')) $back = "/consumables/$id";
    if (!is_numeric($qtyRaw)) { flash('Enter a quantity.', 'bad'); redirect($back); }
    $qty = (float)$qtyRaw;
    $siteId = int_or_null(post('site_id'));
    $ticketId = int_or_null(post('ticket_id'));
    $note = str_or_null(post('note'));
    $change = match ($reason) {
        'received' => abs($qty),
        'used'     => -abs($qty),
        'adjust'   => $qty - (float)$c['qty_on_hand'],   // "adjust" = set the counted quantity
    };
    if ($reason === 'adjust' && $qty < 0) { flash('A count can’t be negative.', 'bad'); redirect($back); }
    if ($change == 0.0) { flash('Nothing changed.', 'warn'); redirect($back); }
    $left = consumable_move($id, $change, $reason, $siteId, $ticketId, $note ?? ($reason === 'adjust' ? 'Stock count' : null));
    $msg = match ($reason) {
        'received' => 'Received ' . qty_fmt($qty) . ' ' . $c['unit'] . '.',
        'used'     => 'Used ' . qty_fmt($qty) . ' ' . $c['unit'] . ' of ' . $c['name'] . '.',
        'adjust'   => 'Count set to ' . qty_fmt($qty) . ' ' . $c['unit'] . '.',
    };
    $low = $c['reorder_level'] !== null && $left <= (float)$c['reorder_level'];
    flash($msg . ' On hand: ' . qty_fmt($left) . ' ' . $c['unit'] . ($left < 0 ? ' (negative — count it!)' : '') . ($low ? ' ⚠ Re-order level reached.' : ''), ($low || $left < 0) ? 'warn' : 'ok');
    redirect($back);
}

function h_consumable_delete(int $id): void {
    require_role('admin');
    // Keep history if it was ever used; otherwise remove completely
    if ((int)val("SELECT COUNT(*) FROM consumable_moves WHERE consumable_id = ? AND reason = 'used'", [$id]) > 0) {
        q('UPDATE consumables SET active = 0 WHERE id = ?', [$id]);
        flash('Archived (it has usage history, so it was hidden instead of deleted).');
    } else {
        q('DELETE FROM consumables WHERE id = ?', [$id]);
        flash('Consumable deleted.');
    }
    redirect('/equipment?tab=consumables');
}
