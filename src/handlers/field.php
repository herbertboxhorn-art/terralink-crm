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
        'timeline'  => all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'site' AND a.entity_id = ? ORDER BY a.created_at DESC", [$id]),
    ]);
}

function h_site_delete(int $id): void {
    require_role('admin', 'tech');
    $cid = val('SELECT company_id FROM sites WHERE id = ?', [$id]);
    q("DELETE FROM activities WHERE entity_type = 'site' AND entity_id = ?", [$id]);
    q('DELETE FROM sites WHERE id = ?', [$id]);
    flash('Site deleted.');
    redirect($cid ? "/companies/$cid#sites" : '/companies');
}

// ---------- equipment ----------
function h_equipment(): void {
    $term = (string)get('q', '');
    $cat = (string)get('category', '');
    $f = (string)get('f', '');
    $w = []; $p = [];
    if ($term !== '') {
        $w[] = '(e.serial LIKE ? OR e.mac LIKE ? OR e.ip_address LIKE ? OR e.model LIKE ? OR e.make LIKE ? OR c.name LIKE ? OR e.phone_number LIKE ?)';
        array_push($p, ...array_fill(0, 7, "%$term%"));
    }
    if ($cat !== '' && in_array($cat, EQUIP_CATEGORIES, true)) { $w[] = 'e.category = ?'; $p[] = $cat; }
    if ($f === 'warranty') $w[] = "e.status = 'active' AND e.warranty_until BETWEEN CURDATE() AND CURDATE() + INTERVAL 90 DAY";
    if ($f === 'faulty') $w[] = "e.status = 'faulty'";
    $rows = all('SELECT e.*, s.name site, s.id site_id, c.name company, c.id company_id FROM equipment e
                 JOIN sites s ON s.id = e.site_id JOIN companies c ON c.id = s.company_id'
                 . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY c.name, s.name, e.category LIMIT 500', $p);
    view('equipment', ['title' => 'Equipment', 'rows' => $rows, 'term' => $term, 'cat' => $cat, 'f' => $f]);
}

function h_equipment_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM equipment WHERE id = ?', [$id]) : ['site_id' => int_or_null(get('site_id')), 'status' => 'active', 'installed_at' => date('Y-m-d')];
    if ($id && !$row) not_found();
    $siteId = (int)($row['site_id'] ?? 0);
    $site = one('SELECT s.*, c.name company FROM sites s JOIN companies c ON c.id = s.company_id WHERE s.id = ?', [$siteId]);
    if (!$site) { flash('Pick a site first, then add equipment from the site page.', 'warn'); redirect('/companies'); }
    $def = entity_def('equipment');
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if ($vals['mac']) $vals['mac'] = strtoupper(str_replace('-', ':', $vals['mac']));
        if (!$errors) {
            $vals['site_id'] = $siteId;
            $newId = save_entity($def, $vals, $id ?: null);
            $label = trim($vals['category'] . ' ' . ($vals['make'] ?? '') . ' ' . ($vals['model'] ?? '')) . ($vals['serial'] ? " (S/N {$vals['serial']})" : '');
            log_activity('system', 'site', $siteId, ($id ? 'Equipment updated: ' : 'Equipment installed: ') . $label, (int)$site['company_id']);
            flash($id ? 'Equipment saved.' : 'Equipment added.');
            redirect("/sites/$siteId#equipment");
        }
        $row = array_merge($row, $vals);
    }
    view('form', ['title' => ($id ? 'Edit equipment' : 'Add equipment') . ' — ' . $site['name'], 'def' => $def, 'row' => $row,
        'errors' => $errors, 'cancel' => "/sites/$siteId", 'deleteUrl' => $id ? "/equipment/$id/delete" : null,
        'subtitle' => $site['company'] . ' · ' . $site['name']]);
}

function h_equipment_delete(int $id): void {
    $sid = val('SELECT site_id FROM equipment WHERE id = ?', [$id]);
    q('DELETE FROM equipment WHERE id = ?', [$id]);
    flash('Equipment removed.');
    redirect($sid ? "/sites/$sid#equipment" : '/equipment');
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
