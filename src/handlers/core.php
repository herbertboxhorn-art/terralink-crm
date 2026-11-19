<?php

function h_dashboard(): void {
    $me = uid();
    $data = [
        'title'        => 'Dashboard',
        'myTasks'      => all('SELECT t.*, c.name company FROM tasks t LEFT JOIN companies c ON c.id = t.company_id
                               WHERE t.done_at IS NULL AND t.assigned_to = ? ORDER BY t.due_date IS NULL, t.due_date LIMIT 12', [$me]),
        'openTickets'  => all("SELECT t.*, c.name company FROM tickets t JOIN companies c ON c.id = t.company_id
                               WHERE t.status <> 'closed' ORDER BY FIELD(t.priority,'urgent','high','normal','low'), t.created_at LIMIT 10"),
        'pipeline'     => array_column(all("SELECT stage, COUNT(*) n, COALESCE(SUM(value),0) v FROM deals GROUP BY stage"), null, 'stage'),
        'newLeads'     => all("SELECT d.*, c.name company FROM deals d LEFT JOIN companies c ON c.id = d.company_id
                               WHERE d.stage = 'new' ORDER BY d.created_at DESC LIMIT 6"),
        'warranty'     => all("SELECT e.*, s.name site, c.name company, c.id company_id FROM equipment e
                               JOIN sites s ON s.id = e.site_id JOIN companies c ON c.id = s.company_id
                               WHERE e.status = 'active' AND e.warranty_until BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY
                               ORDER BY e.warranty_until LIMIT 8"),
        'overdueCount' => (int)val('SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND due_date < CURDATE() AND assigned_to = ?', [$me]),
        'lowStock'     => all('SELECT id, name, unit, qty_on_hand, reorder_level FROM consumables WHERE active = 1 AND reorder_level IS NOT NULL AND qty_on_hand <= reorder_level ORDER BY name LIMIT 8'),
        'travelUnset'  => (float)setting('travel_rate_per_km', '0') <= 0 && is_role('admin'),
    ];
    view('dashboard', $data);
}

function h_search(): void {
    $term = (string)get('q', '');
    $res = ['companies' => [], 'contacts' => [], 'deals' => [], 'tickets' => [], 'equipment' => [], 'quotes' => []];
    if (mb_strlen($term) >= 2) {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $res['companies'] = all('SELECT id, name, town, industry FROM companies WHERE name LIKE ? OR town LIKE ? OR phone LIKE ? ORDER BY name LIMIT 20', [$like, $like, $like]);
        $res['contacts']  = all("SELECT ct.*, c.name company FROM contacts ct LEFT JOIN companies c ON c.id = ct.company_id
                                 WHERE CONCAT_WS(' ', ct.first_name, ct.last_name) LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ? OR ct.mobile LIKE ? LIMIT 20", [$like, $like, $like, $like]);
        $res['deals']     = all('SELECT d.id, d.title, d.stage, c.name company FROM deals d LEFT JOIN companies c ON c.id = d.company_id WHERE d.title LIKE ? LIMIT 20', [$like]);
        $res['tickets']   = all('SELECT t.id, t.number, t.subject, t.status, c.name company FROM tickets t JOIN companies c ON c.id = t.company_id WHERE t.subject LIKE ? OR t.number LIKE ? LIMIT 20', [$like, $like]);
        $res['equipment'] = all('SELECT e.*, s.name site, s.id site_id, c.name company FROM equipment e LEFT JOIN sites s ON s.id = e.site_id LEFT JOIN companies c ON c.id = s.company_id
                                 WHERE e.serial LIKE ? OR e.mac LIKE ? OR e.ip_address LIKE ? OR e.model LIKE ? OR e.phone_number LIKE ? LIMIT 20', [$like, $like, $like, $like, $like]);
        $res['quotes']    = all('SELECT q.id, q.number, q.title, q.status, c.name company FROM quotes q JOIN companies c ON c.id = q.company_id WHERE q.number LIKE ? OR q.title LIKE ? LIMIT 20', [$like, $like]);
    }
    view('search', ['title' => 'Search', 'term' => $term, 'res' => $res]);
}

// ---------- companies ----------
function h_companies(): void {
    $term = (string)get('q', '');
    $ind = (string)get('industry', '');
    $where = []; $p = [];
    if ($term !== '') { $where[] = '(c.name LIKE ? OR c.town LIKE ?)'; $p[] = "%$term%"; $p[] = "%$term%"; }
    if ($ind !== '' && in_array($ind, INDUSTRIES, true)) { $where[] = 'c.industry = ?'; $p[] = $ind; }
    $sql = "SELECT c.*,
              (SELECT COUNT(*) FROM deals d WHERE d.company_id = c.id AND d.stage NOT IN ('won','lost')) open_deals,
              (SELECT COUNT(*) FROM tickets t WHERE t.company_id = c.id AND t.status <> 'closed') open_tickets
            FROM companies c" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.name LIMIT 500';
    view('companies', ['title' => 'Companies', 'rows' => all($sql, $p), 'term' => $term, 'ind' => $ind]);
}

function h_company_form(int $id = 0): void {
    $def = entity_def('company');
    $row = $id ? one('SELECT * FROM companies WHERE id = ?', [$id]) : ['owner_id' => uid()];
    if ($id && !$row) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if (!$errors) {
            $newId = save_entity($def, $vals, $id ?: null);
            if (!$id) log_activity('system', 'company', $newId, 'Company created', $newId);
            flash($id ? 'Company saved.' : 'Company created.');
            redirect("/companies/$newId");
        }
        $row = $vals;
    }
    view('form', ['title' => $id ? 'Edit company' : 'New company', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/companies/$id" : '/companies', 'deleteUrl' => ($id && is_role('admin')) ? "/companies/$id/delete" : null]);
}

function h_company_show(int $id): void {
    $c = one('SELECT c.*, u.name owner FROM companies c LEFT JOIN users u ON u.id = c.owner_id WHERE c.id = ?', [$id]);
    if (!$c) not_found();
    view('company', [
        'title'    => $c['name'],
        'c'        => $c,
        'contacts' => all('SELECT * FROM contacts WHERE company_id = ? ORDER BY is_primary DESC, first_name', [$id]),
        'deals'    => all('SELECT d.*, u.name owner FROM deals d LEFT JOIN users u ON u.id = d.owner_id WHERE d.company_id = ? ORDER BY FIELD(d.stage,"new","audit_booked","quoted","won","lost"), d.created_at DESC', [$id]),
        'sites'    => all('SELECT s.*, (SELECT COUNT(*) FROM equipment e WHERE e.site_id = s.id AND e.status <> "retired") n_equip FROM sites s WHERE s.company_id = ? ORDER BY s.name', [$id]),
        'tickets'  => all('SELECT t.*, u.name assignee FROM tickets t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.company_id = ? ORDER BY t.status = "closed", t.created_at DESC LIMIT 50', [$id]),
        'quotes'   => all('SELECT q.*, (SELECT COALESCE(SUM(qty*unit_price),0) FROM quote_items qi WHERE qi.quote_id = q.id) subtotal FROM quotes q WHERE q.company_id = ? ORDER BY q.issued_on DESC', [$id]),
        'tasks'    => all('SELECT t.*, u.name assignee FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.company_id = ? AND t.done_at IS NULL ORDER BY t.due_date IS NULL, t.due_date', [$id]),
        'timeline' => all('SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.company_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 100', [$id]),
    ]);
}

function h_company_delete(int $id): void {
    require_role('admin');
    q('DELETE FROM activities WHERE company_id = ?', [$id]);
    q('DELETE FROM companies WHERE id = ?', [$id]);
    flash('Company deleted.');
    redirect('/companies');
}

/** JSON lists of contacts / sites / deals for dependent dropdowns */
function h_company_options(int $id): void {
    json_out(['contacts' => contact_opts($id), 'sites' => site_opts($id), 'deals' => deal_opts($id)]);
}

// ---------- contacts ----------
function h_contacts(): void {
    $term = (string)get('q', '');
    $p = []; $w = '';
    if ($term !== '') { $w = "WHERE CONCAT_WS(' ', ct.first_name, ct.last_name) LIKE ? OR ct.email LIKE ? OR c.name LIKE ?"; $p = ["%$term%", "%$term%", "%$term%"]; }
    $rows = all("SELECT ct.*, c.name company FROM contacts ct LEFT JOIN companies c ON c.id = ct.company_id $w ORDER BY ct.first_name, ct.last_name LIMIT 500", $p);
    view('contacts', ['title' => 'Contacts', 'rows' => $rows, 'term' => $term]);
}

function h_contact_form(int $id = 0): void {
    $def = entity_def('contact');
    $row = $id ? one('SELECT * FROM contacts WHERE id = ?', [$id]) : ['company_id' => int_or_null(get('company_id'))];
    if ($id && !$row) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if (!$errors) {
            $newId = save_entity($def, $vals, $id ?: null);
            if ($vals['is_primary'] && $vals['company_id']) {
                q('UPDATE contacts SET is_primary = 0 WHERE company_id = ? AND id <> ?', [$vals['company_id'], $newId]);
            }
            if (!$id) log_activity('system', 'contact', $newId, 'Contact added: ' . trim($vals['first_name'] . ' ' . $vals['last_name']), $vals['company_id']);
            flash($id ? 'Contact saved.' : 'Contact created.');
            redirect($vals['company_id'] && !$id ? "/companies/{$vals['company_id']}#contacts" : "/contacts/$newId");
        }
        $row = $vals;
    }
    view('form', ['title' => $id ? 'Edit contact' : 'New contact', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/contacts/$id" : (($row['company_id'] ?? null) ? "/companies/{$row['company_id']}" : '/contacts'),
        'deleteUrl' => $id ? "/contacts/$id/delete" : null]);
}

function h_contact_show(int $id): void {
    $ct = one('SELECT ct.*, c.name company FROM contacts ct LEFT JOIN companies c ON c.id = ct.company_id WHERE ct.id = ?', [$id]);
    if (!$ct) not_found();
    view('contact', [
        'title'    => contact_name($ct),
        'ct'       => $ct,
        'deals'    => all('SELECT * FROM deals WHERE contact_id = ? ORDER BY created_at DESC', [$id]),
        'tickets'  => all('SELECT * FROM tickets WHERE contact_id = ? ORDER BY created_at DESC LIMIT 20', [$id]),
        'timeline' => all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'contact' AND a.entity_id = ? ORDER BY a.created_at DESC", [$id]),
    ]);
}

function h_contact_delete(int $id): void {
    $cid = val('SELECT company_id FROM contacts WHERE id = ?', [$id]);
    q("DELETE FROM activities WHERE entity_type = 'contact' AND entity_id = ?", [$id]);
    q('DELETE FROM contacts WHERE id = ?', [$id]);
    flash('Contact deleted.');
    redirect($cid ? "/companies/$cid" : '/contacts');
}
