<?php

function quote_totals(array $items, float $gst, float $pst): array {
    $sub = 0.0; $pstBase = 0.0;
    foreach ($items as $i) {
        $line = round((float)$i['qty'] * (float)$i['unit_price'], 2);
        $sub += $line;
        if (!empty($i['pst_applies'])) $pstBase += $line;
    }
    $g = round($sub * $gst / 100, 2);
    $p = round($pstBase * $pst / 100, 2);
    return ['subtotal' => $sub, 'gst' => $g, 'pst' => $p, 'pst_base' => $pstBase, 'total' => $sub + $g + $p];
}

function h_quotes(): void {
    $f = (string)get('f', '');
    $w = isset(QUOTE_STATUS[$f]) ? 'WHERE q.status = ' . db()->quote($f) : '';
    $rows = all("SELECT q.*, c.name company,
                   (SELECT COALESCE(SUM(qty*unit_price),0) FROM quote_items qi WHERE qi.quote_id = q.id) subtotal
                 FROM quotes q JOIN companies c ON c.id = q.company_id $w ORDER BY q.issued_on DESC, q.id DESC LIMIT 300");
    view('quotes', ['title' => 'Quotes', 'rows' => $rows, 'f' => $f]);
}

function read_items(): array {
    $items = [];
    $in = $_POST['items'] ?? [];
    if (!is_array($in) || !isset($in['description']) || !is_array($in['description'])) return [];
    foreach ($in['description'] as $i => $desc) {
        $desc = trim((string)$desc);
        if ($desc === '') continue;
        $kind = (string)($in['kind'][$i] ?? 'labour');
        $items[] = [
            'kind'        => isset(ITEM_KINDS[$kind]) ? $kind : 'other',
            'description' => mb_substr($desc, 0, 255),
            'qty'         => is_numeric($in['qty'][$i] ?? null) ? (float)$in['qty'][$i] : 1,
            'unit'        => str_or_null($in['unit'][$i] ?? null),
            'unit_price'  => is_numeric($in['unit_price'][$i] ?? null) ? (float)$in['unit_price'][$i] : 0,
            'pst_applies' => !empty($in['pst'][$i]) && $in['pst'][$i] !== '0' ? 1 : 0,
        ];
    }
    return $items;
}

function h_quote_form(int $id = 0): void {
    $validDays = (int)setting('quote_valid_days', '30');
    $row = $id ? one('SELECT * FROM quotes WHERE id = ?', [$id]) : [
        'company_id' => int_or_null(get('company_id')), 'deal_id' => int_or_null(get('deal_id')),
        'issued_on' => date('Y-m-d'), 'valid_until' => date('Y-m-d', strtotime("+$validDays days")),
        'gst_rate' => '5', 'pst_rate' => '7'];
    if ($id && !$row) not_found();
    if (!$id && $row['deal_id'] && !$row['company_id']) $row['company_id'] = entity_company('deal', (int)$row['deal_id']);
    if (!$id && $row['deal_id']) {
        $d = one('SELECT title, contact_id FROM deals WHERE id = ?', [$row['deal_id']]);
        if ($d) { $row['title'] = $d['title']; $row['contact_id'] = $d['contact_id']; }
    }
    foreach (['gst_rate', 'pst_rate'] as $k) if (isset($row[$k])) $row[$k] = (string)(float)$row[$k];
    $items = $id ? all('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position, id', [$id]) : [];
    $cid = int_or_null($_POST['company_id'] ?? $row['company_id'] ?? null);
    $def = entity_def('quote', ['company_id' => $cid]);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        $items = read_items();
        if (!$items) $errors['_items'] = 'Add at least one line item.';
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            if (!$id) { $vals['number'] = next_number('quote'); $vals['created_by'] = uid(); $vals['status'] = 'draft'; }
            $qid = save_entity($def, $vals, $id ?: null);
            q('DELETE FROM quote_items WHERE quote_id = ?', [$qid]);
            foreach ($items as $pos => $it) {
                q('INSERT INTO quote_items (quote_id, position, kind, description, qty, unit, unit_price, pst_applies) VALUES (?,?,?,?,?,?,?,?)',
                  [$qid, $pos, $it['kind'], $it['description'], $it['qty'], $it['unit'], $it['unit_price'], $it['pst_applies']]);
            }
            $pdo->commit();
            if (!$id) {
                log_activity('system', 'quote', $qid, "Quote {$vals['number']} created: {$vals['title']}", $vals['company_id']);
                if ($vals['deal_id']) {
                    $deal = one('SELECT stage, title FROM deals WHERE id = ?', [$vals['deal_id']]);
                    if ($deal && in_array($deal['stage'], ['new', 'audit_booked'], true)) {
                        q("UPDATE deals SET stage = 'quoted', stage_changed_at = NOW() WHERE id = ?", [$vals['deal_id']]);
                        log_activity('stage', 'deal', $vals['deal_id'], STAGES[$deal['stage']] . ' → Quoted (' . $deal['title'] . ')', $vals['company_id']);
                    }
                }
            }
            flash($id ? 'Quote saved.' : 'Quote created.');
            redirect("/quotes/$qid");
        }
        $row = array_merge($row, $vals);
    }
    view('quote_form', ['title' => $id ? 'Edit quote ' . $row['number'] : 'New quote', 'def' => $def, 'row' => $row, 'items' => $items,
        'errors' => $errors, 'id' => $id, 'travelRate' => (float)setting('travel_rate_per_km', '0')]);
}

function load_quote(int $id): array {
    $qt = one('SELECT q.*, c.name company, c.address c_address, c.town c_town, c.province c_province, c.phone c_phone, c.email c_email,
                      ct.first_name, ct.last_name, ct.email ct_email, d.title deal, u.name author
               FROM quotes q JOIN companies c ON c.id = q.company_id LEFT JOIN contacts ct ON ct.id = q.contact_id
               LEFT JOIN deals d ON d.id = q.deal_id LEFT JOIN users u ON u.id = q.created_by WHERE q.id = ?', [$id]);
    if (!$qt) not_found();
    $items = all('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position, id', [$id]);
    return [$qt, $items, quote_totals($items, (float)$qt['gst_rate'], (float)$qt['pst_rate'])];
}

function h_quote_show(int $id): void {
    [$qt, $items, $tot] = load_quote($id);
    $timeline = all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'quote' AND a.entity_id = ? ORDER BY a.created_at DESC", [$id]);
    view('quote', ['title' => $qt['number'] . ' — ' . $qt['title'], 'qt' => $qt, 'items' => $items, 'tot' => $tot, 'timeline' => $timeline]);
}

function h_quote_pdf(int $id): void {
    [$qt, $items, $tot] = load_quote($id);
    ob_start();
    view('quote_pdf', ['qt' => $qt, 'items' => $items, 'tot' => $tot], false);
    $html = ob_get_clean();
    $opts = new \Dompdf\Options();
    $opts->set('isRemoteEnabled', false);
    $opts->set('defaultFont', 'Helvetica');
    $opts->setChroot(APP_ROOT . '/public');
    $pdf = new \Dompdf\Dompdf($opts);
    $pdf->loadHtml($html);
    $pdf->setPaper('letter');
    $pdf->render();
    $fname = preg_replace('/[^A-Za-z0-9_-]+/', '-', $qt['number'] . '-' . $qt['company']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $fname . '"');
    echo $pdf->output();
}

function h_quote_status(int $id): void {
    $qt = one('SELECT * FROM quotes WHERE id = ?', [$id]);
    if (!$qt) not_found();
    $st = (string)post('status', '');
    if (!isset(QUOTE_STATUS[$st]) || $st === $qt['status']) redirect("/quotes/$id");
    q('UPDATE quotes SET status = ? WHERE id = ?', [$st, $id]);
    log_activity('stage', 'quote', $id, "Quote {$qt['number']}: " . QUOTE_STATUS[$qt['status']] . ' → ' . QUOTE_STATUS[$st], (int)$qt['company_id']);
    // Accepted/declined quote moves its deal to won/lost
    if ($qt['deal_id'] && in_array($st, ['accepted', 'declined'], true)) {
        $new = $st === 'accepted' ? 'won' : 'lost';
        $deal = one('SELECT stage, title FROM deals WHERE id = ?', [$qt['deal_id']]);
        if ($deal && $deal['stage'] !== $new) {
            [$tot] = [quote_totals(all('SELECT * FROM quote_items WHERE quote_id = ?', [$id]), (float)$qt['gst_rate'], (float)$qt['pst_rate'])];
            q('UPDATE deals SET stage = ?, stage_changed_at = NOW()' . ($new === 'won' ? ', value = ?' : '') . ' WHERE id = ?',
              $new === 'won' ? [$new, $tot['subtotal'], $qt['deal_id']] : [$new, $qt['deal_id']]);
            log_activity('stage', 'deal', (int)$qt['deal_id'], STAGES[$deal['stage']] . ' → ' . STAGES[$new] . " (via quote {$qt['number']})", (int)$qt['company_id']);
        }
    }
    flash('Quote marked ' . strtolower(QUOTE_STATUS[$st]) . '.');
    redirect("/quotes/$id");
}

function h_quote_duplicate(int $id): void {
    [$qt, $items] = load_quote($id);
    $validDays = (int)setting('quote_valid_days', '30');
    $num = next_number('quote');
    q('INSERT INTO quotes (number, company_id, contact_id, deal_id, title, status, issued_on, valid_until, gst_rate, pst_rate, notes, created_by)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$num, $qt['company_id'], $qt['contact_id'], $qt['deal_id'], $qt['title'] . ' (rev)', 'draft',
       date('Y-m-d'), date('Y-m-d', strtotime("+$validDays days")), $qt['gst_rate'], $qt['pst_rate'], $qt['notes'], uid()]);
    $nid = (int)db()->lastInsertId();
    foreach ($items as $it) {
        q('INSERT INTO quote_items (quote_id, position, kind, description, qty, unit, unit_price, pst_applies) VALUES (?,?,?,?,?,?,?,?)',
          [$nid, $it['position'], $it['kind'], $it['description'], $it['qty'], $it['unit'], $it['unit_price'], $it['pst_applies']]);
    }
    log_activity('system', 'quote', $nid, "Quote $num created as a copy of {$qt['number']}", (int)$qt['company_id']);
    flash("Copied to $num.");
    redirect("/quotes/$nid/edit");
}

function h_quote_delete(int $id): void {
    require_role('admin', 'sales');
    q("DELETE FROM activities WHERE entity_type = 'quote' AND entity_id = ?", [$id]);
    q('DELETE FROM quotes WHERE id = ?', [$id]);
    flash('Quote deleted.');
    files_purge_orphans();
    redirect('/quotes');
}
