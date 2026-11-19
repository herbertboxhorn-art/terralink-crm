<?php
// Sites, equipment and support tickets

function h_site_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM sites WHERE id = ?', [$id]) : ['company_id' => int_or_null(get('company_id'))];
    if ($id && !$row) not_found();
    $def = entity_def('site');
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        foreach (['latitude' => 90, 'longitude' => 180] as $k => $max) {
            if ($vals[$k] !== null && abs((float)$vals[$k]) > $max) $errors[$k] = "Must be between -$max and $max";
        }
        if (!$errors) {
            $newId = save_entity($def, $vals, $id ?: null);
            if (!$id) log_activity('system', 'site', $newId, 'Site added: ' . $vals['name'], $vals['company_id']);
            flash($id ? 'Site saved.' : 'Site created.');
            redirect("/sites/$newId");
        }
        $row = $vals;
    }
    view('form', ['title' => $id ? 'Edit site' : 'New site', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/sites/$id" : (($row['company_id'] ?? null) ? "/companies/{$row['company_id']}#sites" : '/companies'),
        'deleteUrl' => ($id && is_role('admin', 'tech')) ? "/sites/$id/delete" : null, 'geo' => true]);
}

function h_site_show(int $id): void {
    $s = one('SELECT s.*, c.name company FROM sites s JOIN companies c ON c.id = s.company_id WHERE s.id = ?', [$id]);
    if (!$s) not_found();
    view('site', [
        'title'     => $s['name'] . ' — ' . $s['company'],
        's'         => $s,
        'equipment' => all("SELECT * FROM equipment WHERE site_id = ? ORDER BY status = 'retired', FIELD(category,'Starlink kit','UniFi','Phone line','VoIP handset','Server','Workstation','Printer','UPS / Power','Other'), model", [$id]),
        'tickets'   => all('SELECT * FROM tickets WHERE site_id = ? ORDER BY status = "closed", created_at DESC LIMIT 30', [$id]),
        'used'      => all("SELECT m.*, c.name item, c.unit, u.name who FROM consumable_moves m JOIN consumables c ON c.id = m.consumable_id
                            LEFT JOIN users u ON u.id = m.user_id WHERE m.site_id = ? AND m.reason = 'used' ORDER BY m.created_at DESC LIMIT 30", [$id]),
        'consumables' => all('SELECT id, name, unit, qty_on_hand FROM consumables WHERE active = 1 ORDER BY name'),
        'timeline'  => all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'site' AND a.entity_id = ? ORDER BY a.created_at DESC", [$id]),
    ]);
}

function h_site_delete(int $id): void {
    require_role('admin', 'tech');
    $cid = val('SELECT company_id FROM sites WHERE id = ?', [$id]);
    // Gear at a deleted site goes back to stock (FK also sets site_id NULL; this fixes the status)
    q("UPDATE equipment SET site_id = NULL, status = IF(status IN ('active','spare'), 'in_stock', status) WHERE site_id = ?", [$id]);
    q("DELETE FROM activities WHERE entity_type = 'site' AND entity_id = ?", [$id]);
    q('DELETE FROM sites WHERE id = ?', [$id]);
    flash('Site deleted. Its equipment was moved back to stock.');
    redirect($cid ? "/companies/$cid#sites" : '/companies');
}

// ---------- equipment ----------
function equip_label(array $e): string {
    return trim($e['category'] . ' ' . ($e['make'] ?? '') . ' ' . ($e['model'] ?? '')) . (!empty($e['serial']) ? " (S/N {$e['serial']})" : '');
}

/** Inventory page: tabs installed | stock | consumables */
function h_equipment(): void {
    $tab = (string)get('tab', 'installed');
    if (!in_array($tab, ['installed', 'stock', 'consumables'], true)) $tab = 'installed';
    $term = (string)get('q', '');
    $cat = (string)get('category', '');
    $f = (string)get('f', '');
    $data = ['title' => 'Inventory', 'tab' => $tab, 'term' => $term, 'cat' => $cat, 'f' => $f,
             'counts' => [
                 'installed'   => (int)val('SELECT COUNT(*) FROM equipment WHERE site_id IS NOT NULL'),
                 'stock'       => (int)val("SELECT COUNT(*) FROM equipment WHERE site_id IS NULL AND status = 'in_stock'"),
                 'consumables' => (int)val('SELECT COUNT(*) FROM consumables WHERE active = 1'),
                 'low'         => (int)val('SELECT COUNT(*) FROM consumables WHERE active = 1 AND reorder_level IS NOT NULL AND qty_on_hand <= reorder_level'),
             ]];
    if ($tab === 'consumables') {
        $w = ['c.active = 1']; $p = [];
        if ($term !== '') { $w[] = '(c.name LIKE ? OR c.sku LIKE ? OR c.supplier LIKE ?)'; array_push($p, "%$term%", "%$term%", "%$term%"); }
        if ($f === 'low') $w[] = 'c.reorder_level IS NOT NULL AND c.qty_on_hand <= c.reorder_level';
        $data['rows'] = all('SELECT c.* FROM consumables c WHERE ' . implode(' AND ', $w) . ' ORDER BY c.category, c.name', $p);
        $data['value'] = (float)val('SELECT COALESCE(SUM(qty_on_hand * unit_cost),0) FROM consumables WHERE active = 1 AND unit_cost IS NOT NULL');
        view('equipment', $data);
        return;
    }
    $w = [$tab === 'stock' ? 'e.site_id IS NULL' : 'e.site_id IS NOT NULL']; $p = [];
    if ($term !== '') {
        $w[] = '(e.serial LIKE ? OR e.mac LIKE ? OR e.ip_address LIKE ? OR e.model LIKE ? OR e.make LIKE ? OR c.name LIKE ? OR e.phone_number LIKE ? OR e.stock_location LIKE ?)';
        array_push($p, ...array_fill(0, 8, "%$term%"));
    }
    if ($cat !== '' && in_array($cat, EQUIP_CATEGORIES, true)) { $w[] = 'e.category = ?'; $p[] = $cat; }
    if ($tab === 'stock' && $f !== 'all') $w[] = "e.status = 'in_stock'";
    if ($tab === 'installed' && $f === 'warranty') $w[] = "e.status = 'active' AND e.warranty_until BETWEEN CURDATE() AND CURDATE() + INTERVAL 90 DAY";
    if ($tab === 'installed' && $f === 'faulty') $w[] = "e.status = 'faulty'";
    $data['rows'] = all('SELECT e.*, s.name site, c.name company, c.id company_id FROM equipment e
                 LEFT JOIN sites s ON s.id = e.site_id LEFT JOIN companies c ON c.id = s.company_id
                 WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . ($tab === 'stock' ? 'e.category, e.make, e.model, e.serial' : 'c.name, s.name, e.category') . ' LIMIT 1000', $p);
    if ($tab === 'stock') {
        $data['summary'] = all("SELECT category, COUNT(*) n, COALESCE(SUM(cost),0) v FROM equipment WHERE site_id IS NULL AND status = 'in_stock' GROUP BY category ORDER BY n DESC");
    }
    view('equipment', $data);
}

function h_equipment_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM equipment WHERE id = ?', [$id]) : [
        'site_id' => int_or_null(get('site_id')), 'installed_at' => date('Y-m-d'), 'stock_location' => stock_locations()[0]];
    if ($id && !$row) not_found();
    $siteId = $row['site_id'] ? (int)$row['site_id'] : null;
    $site = $siteId ? one('SELECT s.*, c.name company FROM sites s JOIN companies c ON c.id = s.company_id WHERE s.id = ?', [$siteId]) : null;
    if ($siteId && !$site) not_found();
    $inStock = !$site;
    if (!$id) $row['status'] = $inStock ? 'in_stock' : 'active';
    $def = entity_def('equipment', ['in_stock' => $inStock]);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if (!empty($vals['mac'])) $vals['mac'] = strtoupper(str_replace('-', ':', $vals['mac']));
        if (!$errors) {
            $vals['site_id'] = $siteId;
            if ($inStock) { $vals['installed_at'] = null; }
            else { $vals['stock_location'] = null; }
            $newId = save_entity($def, $vals, $id ?: null);
            if ($site) log_activity('system', 'site', $siteId, ($id ? 'Equipment updated: ' : 'Equipment installed: ') . equip_label($vals), (int)$site['company_id']);
            flash($id ? 'Equipment saved.' : ($inStock ? 'Added to stock.' : 'Equipment added.'));
            redirect($site ? "/sites/$siteId#equipment" : '/equipment?tab=stock');
        }
        $row = array_merge($row, $vals);
    }
    view('form', ['title' => ($id ? 'Edit equipment' : ($inStock ? 'Add to stock' : 'Add equipment')) . ($site ? ' — ' . $site['name'] : ''),
        'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $site ? "/sites/$siteId" : '/equipment?tab=stock', 'deleteUrl' => $id ? "/equipment/$id/delete" : null,
        'subtitle' => $site ? $site['company'] . ' · ' . $site['name'] : 'In stock — not at any site yet',
        'extraActions' => $id ? ($inStock
            ? [['Install at a site →', "/equipment/$id/install"]]
            : [['Return to stock', "/equipment/$id/return", true]]) : []]);
}

/** Receive several serialized items of one model at once (one row per serial, or N rows without serials) */
function h_equipment_receive(): void {
    $errors = []; $v = ['category' => 'UniFi', 'stock_location' => stock_locations()[0], 'purchased_at' => date('Y-m-d'), 'qty' => '1'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $v = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        $serials = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,;\t]+/', (string)($v['serials'] ?? ''))))));
        $qty = $serials ? count($serials) : max(0, (int)($v['qty'] ?? 0));
        if (!in_array($v['category'] ?? '', EQUIP_CATEGORIES, true)) $errors['category'] = 'Pick a category';
        if (($v['model'] ?? '') === '') $errors['model'] = 'Required';
        if ($qty < 1 || $qty > 500) $errors['serials'] = 'Enter serial numbers, or a quantity between 1 and 500';
        foreach (['cost' => 'number', 'purchased_at' => 'date', 'warranty_until' => 'date'] as $k => $t) {
            $x = $v[$k] ?? '';
            if ($x === '') continue;
            if ($t === 'number' && !is_numeric($x)) $errors[$k] = 'Must be a number';
            if ($t === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $x)) $errors[$k] = 'Use YYYY-MM-DD';
        }
        if ($serials) {
            $ph = implode(',', array_fill(0, count($serials), '?'));
            $dupes = array_column(all("SELECT serial FROM equipment WHERE serial IN ($ph)", $serials), 'serial');
            if ($dupes) $errors['serials'] = 'Already in the system: ' . implode(', ', array_slice($dupes, 0, 10));
        }
        if (!$errors) {
            $pdo = db(); $pdo->beginTransaction();
            for ($i = 0; $i < $qty; $i++) {
                q("INSERT INTO equipment (site_id, category, make, model, serial, status, stock_location, supplier, purchased_at, cost, warranty_until, notes)
                   VALUES (NULL,?,?,?,?, 'in_stock', ?,?,?,?,?,?)", [
                    $v['category'], str_or_null($v['make'] ?? null), $v['model'], $serials[$i] ?? null,
                    str_or_null($v['stock_location'] ?? null), str_or_null($v['supplier'] ?? null), str_or_null($v['purchased_at'] ?? null),
                    ($v['cost'] ?? '') === '' ? null : (float)$v['cost'], str_or_null($v['warranty_until'] ?? null), str_or_null($v['notes'] ?? null)]);
            }
            $pdo->commit();
            flash("Received $qty × " . trim(($v['make'] ?? '') . ' ' . $v['model']) . ' into stock.');
            redirect('/equipment?tab=stock');
        }
    }
    view('equipment_receive', ['title' => 'Receive stock', 'v' => $v, 'errors' => $errors]);
}

/** Install one stock item at a site (pick company → site) */
function h_equipment_install(int $id): void {
    $e = one('SELECT * FROM equipment WHERE id = ?', [$id]);
    if (!$e) not_found();
    if ($e['site_id']) { flash('That item is already installed at a site.', 'warn'); redirect("/sites/{$e['site_id']}"); }
    $errors = [];
    $cid = int_or_null($_POST['company_id'] ?? get('company_id'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sid = int_or_null(post('site_id'));
        $site = $sid ? one('SELECT * FROM sites WHERE id = ?', [$sid]) : null;
        if (!$site) $errors['site_id'] = 'Pick the site';
        $date = (string)post('installed_at', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors['installed_at'] = 'Use YYYY-MM-DD';
        if (!$errors) {
            install_items([$id], (int)$site['id'], $date);
            flash('Installed: ' . equip_label($e) . '.');
            redirect("/sites/{$site['id']}#equipment");
        }
    }
    view('equipment_install', ['title' => 'Install at a site', 'e' => $e, 'cid' => $cid, 'errors' => $errors]);
}

/** Moves stock items to a site and logs each on the site timeline */
function install_items(array $ids, int $siteId, string $date): int {
    $site = one('SELECT * FROM sites WHERE id = ?', [$siteId]);
    $n = 0;
    foreach ($ids as $id) {
        $e = one("SELECT * FROM equipment WHERE id = ? AND site_id IS NULL AND status = 'in_stock'", [(int)$id]);
        if (!$e) continue;
        q("UPDATE equipment SET site_id = ?, status = 'active', installed_at = ?, stock_location = NULL WHERE id = ?", [$siteId, $date, $e['id']]);
        log_activity('system', 'site', $siteId, 'Installed from stock: ' . equip_label($e), (int)$site['company_id']);
        $n++;
    }
    return $n;
}

function h_equipment_return(int $id): void {
    $e = one('SELECT e.*, s.company_id FROM equipment e LEFT JOIN sites s ON s.id = e.site_id WHERE e.id = ?', [$id]);
    if (!$e) not_found();
    if (!$e['site_id']) redirect('/equipment?tab=stock');
    $loc = (string)post('stock_location', stock_locations()[0]);
    $faulty = post('faulty') ? 'faulty' : 'in_stock';
    q('UPDATE equipment SET site_id = NULL, status = ?, stock_location = ?, installed_at = NULL WHERE id = ?', [$faulty, $loc, $id]);
    log_activity('system', 'site', (int)$e['site_id'], 'Removed and returned to stock' . ($faulty === 'faulty' ? ' (faulty)' : '') . ': ' . equip_label($e), (int)$e['company_id']);
    flash(equip_label($e) . ' returned to stock' . ($faulty === 'faulty' ? ' as faulty' : '') . '.');
    redirect("/sites/{$e['site_id']}#equipment");
}

/** Site page → "+ Equipment": pick from stock (multi-select) or create new */
function h_site_add_equipment(int $id): void {
    $s = one('SELECT s.*, c.name company FROM sites s JOIN companies c ON c.id = s.company_id WHERE s.id = ?', [$id]);
    if (!$s) not_found();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $date = (string)post('installed_at', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $n = $ids ? install_items($ids, $id, $date) : 0;
        flash($n ? "Installed $n item" . ($n === 1 ? '' : 's') . ' from stock.' : 'Nothing selected.', $n ? 'ok' : 'warn');
        redirect("/sites/$id#equipment");
    }
    $cat = (string)get('category', '');
    $p = []; $w = '';
    if ($cat !== '' && in_array($cat, EQUIP_CATEGORIES, true)) { $w = ' AND category = ?'; $p[] = $cat; }
    $stock = all("SELECT * FROM equipment WHERE site_id IS NULL AND status = 'in_stock'$w ORDER BY category, make, model, serial", $p);
    view('site_add_equipment', ['title' => 'Add equipment — ' . $s['name'], 's' => $s, 'stock' => $stock, 'cat' => $cat]);
}

function h_equipment_delete(int $id): void {
    $sid = val('SELECT site_id FROM equipment WHERE id = ?', [$id]);
    q('DELETE FROM equipment WHERE id = ?', [$id]);
    flash('Equipment deleted.');
    redirect($sid ? "/sites/$sid#equipment" : '/equipment?tab=stock');
}

// ---------- tickets ----------
function h_tickets(): void {
    $f = (string)get('f', 'open');
    $w = match ($f) {
        'mine'   => "t.status <> 'closed' AND t.assigned_to = " . (int)uid(),
        'closed' => "t.status = 'closed'",
        'all'    => '1=1',
        default  => "t.status <> 'closed'",
    };
    $rows = all("SELECT t.*, c.name company, s.name site, u.name assignee FROM tickets t
                 JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id LEFT JOIN users u ON u.id = t.assigned_to
                 WHERE $w ORDER BY t.status = 'closed', FIELD(t.priority,'urgent','high','normal','low'), t.created_at DESC LIMIT 300");
    view('tickets', ['title' => 'Tickets', 'rows' => $rows, 'f' => $f]);
}

function h_ticket_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM tickets WHERE id = ?', [$id]) : [
        'company_id' => int_or_null(get('company_id')), 'site_id' => int_or_null(get('site_id')),
        'equipment_id' => int_or_null(get('equipment_id')), 'status' => 'open', 'priority' => 'normal', 'assigned_to' => uid()];
    if ($id && !$row) not_found();
    if (!$id && $row['site_id'] && !$row['company_id']) $row['company_id'] = entity_company('site', (int)$row['site_id']);
    $cid = int_or_null($_POST['company_id'] ?? $row['company_id'] ?? null);
    $def = entity_def('ticket', ['company_id' => $cid]);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        // Site/contact must belong to the chosen company
        if ($vals['site_id'] && (int)val('SELECT company_id FROM sites WHERE id = ?', [$vals['site_id']]) !== (int)$vals['company_id']) $vals['site_id'] = null;
        if ($vals['contact_id'] && (int)val('SELECT company_id FROM contacts WHERE id = ?', [$vals['contact_id']]) !== (int)$vals['company_id']) $vals['contact_id'] = null;
        if (!$errors) {
            $wasClosed = ($row['status'] ?? '') === 'closed';
            if ($vals['status'] === 'closed' && !$wasClosed) $vals['closed_at'] = date('Y-m-d H:i:s');
            if ($vals['status'] !== 'closed') $vals['closed_at'] = null;
            if (!$id) {
                $vals['number'] = next_number('ticket');
                $vals['created_by'] = uid();
                $vals['equipment_id'] = $row['equipment_id'] ?? null;
            }
            $newId = save_entity($def + ['table' => 'tickets'], $vals, $id ?: null);
            if (!$id) log_activity('system', 'ticket', $newId, "Ticket {$vals['number']} opened: {$vals['subject']}", $vals['company_id']);
            elseif (($row['status'] ?? '') !== $vals['status']) log_activity('stage', 'ticket', $newId, 'Status: ' . TICKET_STATUS[$row['status']] . ' → ' . TICKET_STATUS[$vals['status']], $vals['company_id']);
            flash($id ? 'Ticket saved.' : 'Ticket opened.');
            redirect("/tickets/$newId");
        }
        $row = array_merge($row, $vals);
    }
    view('form', ['title' => $id ? 'Edit ticket ' . $row['number'] : 'New ticket', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/tickets/$id" : '/tickets', 'deleteUrl' => ($id && is_role('admin')) ? "/tickets/$id/delete" : null, 'dependent' => true]);
}

function h_ticket_show(int $id): void {
    $t = one('SELECT t.*, c.name company, s.name site, u.name assignee, ct.first_name, ct.last_name, ct.phone ct_phone, ct.mobile ct_mobile,
                     e.category e_cat, e.make e_make, e.model e_model, e.serial e_serial
              FROM tickets t JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id
              LEFT JOIN users u ON u.id = t.assigned_to LEFT JOIN contacts ct ON ct.id = t.contact_id
              LEFT JOIN equipment e ON e.id = t.equipment_id WHERE t.id = ?', [$id]);
    if (!$t) not_found();
    view('ticket', [
        'title'    => $t['number'] . ' — ' . $t['subject'],
        't'        => $t,
        'tasks'    => all('SELECT t.*, u.name assignee FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.ticket_id = ? ORDER BY t.done_at IS NOT NULL, t.due_date', [$id]),
        'timeline' => all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'ticket' AND a.entity_id = ? ORDER BY a.created_at DESC, a.id DESC", [$id]),
    ]);
}

/** Quick update from the ticket page: status + time spent + optional note in one tap */
function h_ticket_update(int $id): void {
    $t = one('SELECT * FROM tickets WHERE id = ?', [$id]);
    if (!$t) not_found();
    $status = (string)post('status', $t['status']);
    if (!isset(TICKET_STATUS[$status])) $status = $t['status'];
    $mins = max(0, min(24 * 60, (int)post('minutes', 0)));
    $note = (string)post('note', '');
    q('UPDATE tickets SET status = ?, minutes_spent = minutes_spent + ?, closed_at = ? WHERE id = ?', [
        $status, $mins, $status === 'closed' ? ($t['closed_at'] ?? date('Y-m-d H:i:s')) : null, $id]);
    $parts = [];
    if ($status !== $t['status']) $parts[] = 'Status: ' . TICKET_STATUS[$t['status']] . ' → ' . TICKET_STATUS[$status];
    if ($mins) $parts[] = '+' . $mins . ' min';
    if ($note !== '') $parts[] = $note;
    if ($parts) log_activity($status !== $t['status'] ? 'stage' : 'note', 'ticket', $id, implode(' · ', $parts), (int)$t['company_id']);
    flash('Ticket updated.');
    redirect("/tickets/$id");
}

function h_ticket_delete(int $id): void {
    require_role('admin');
    q("DELETE FROM activities WHERE entity_type = 'ticket' AND entity_id = ?", [$id]);
    q('DELETE FROM tickets WHERE id = ?', [$id]);
    flash('Ticket deleted.');
    redirect('/tickets');
}
