<?php

function h_pipeline(): void {
    $owner = get('owner', '');
    $service = (string)get('service', '');
    $w = []; $p = [];
    if ($owner === 'me') { $w[] = 'd.owner_id = ?'; $p[] = uid(); }
    if ($service !== '' && in_array($service, SERVICES, true)) { $w[] = 'd.service = ?'; $p[] = $service; }
    // Won/lost columns only show the last 90 days so the board stays readable
    $w[] = "(d.stage NOT IN ('won','lost') OR d.stage_changed_at > NOW() - INTERVAL 90 DAY)";
    $rows = all('SELECT d.*, c.name company, u.name owner, u.id owner_uid FROM deals d
                 LEFT JOIN companies c ON c.id = d.company_id LEFT JOIN users u ON u.id = d.owner_id
                 WHERE ' . implode(' AND ', $w) . ' ORDER BY d.stage_changed_at DESC', $p);
    $cols = array_fill_keys(array_keys(STAGES), []);
    foreach ($rows as $r) $cols[$r['stage']][] = $r;
    view('pipeline', ['title' => 'Pipeline', 'cols' => $cols, 'owner' => $owner, 'service' => $service]);
}

function h_deal_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM deals WHERE id = ?', [$id])
               : ['company_id' => int_or_null(get('company_id')), 'owner_id' => uid(), 'stage' => 'new'];
    if ($id && !$row) not_found();
    $cid = int_or_null($_POST['company_id'] ?? $row['company_id'] ?? null);
    $def = entity_def('deal', ['company_id' => $cid]);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if (!$errors) {
            $oldStage = $row['stage'] ?? null;
            if ($id && $oldStage !== $vals['stage']) $vals['stage_changed_at'] = date('Y-m-d H:i:s');
            $newId = save_entity($def, $vals, $id ?: null);
            if (!$id) log_activity('system', 'deal', $newId, 'Deal created: ' . $vals['title'], $vals['company_id']);
            elseif ($oldStage !== $vals['stage']) log_activity('stage', 'deal', $newId, STAGES[$oldStage] . ' → ' . STAGES[$vals['stage']] . ' (' . $vals['title'] . ')', $vals['company_id']);
            flash($id ? 'Deal saved.' : 'Deal created.');
            redirect("/deals/$newId");
        }
        $row = $vals;
    }
    view('form', ['title' => $id ? 'Edit deal' : 'New deal', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => $id ? "/deals/$id" : '/pipeline', 'deleteUrl' => $id ? "/deals/$id/delete" : null, 'dependent' => true]);
}

function h_deal_show(int $id): void {
    $d = one('SELECT d.*, c.name company, u.name owner, ct.first_name, ct.last_name, ct.phone ct_phone, ct.mobile ct_mobile, ct.email ct_email
              FROM deals d LEFT JOIN companies c ON c.id = d.company_id LEFT JOIN users u ON u.id = d.owner_id
              LEFT JOIN contacts ct ON ct.id = d.contact_id WHERE d.id = ?', [$id]);
    if (!$d) not_found();
    view('deal', [
        'title'    => $d['title'],
        'd'        => $d,
        'quotes'   => all('SELECT * FROM quotes WHERE deal_id = ? ORDER BY issued_on DESC', [$id]),
        'tasks'    => all('SELECT t.*, u.name assignee FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.deal_id = ? ORDER BY t.done_at IS NOT NULL, t.due_date', [$id]),
        'timeline' => all("SELECT a.*, u.name who FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'deal' AND a.entity_id = ? ORDER BY a.created_at DESC, a.id DESC", [$id]),
    ]);
}

/** Stage change from the pipeline board (AJAX) or the deal page (form) */
function h_deal_stage(int $id): void {
    $d = one('SELECT * FROM deals WHERE id = ?', [$id]);
    if (!$d) not_found();
    $stage = (string)post('stage', '');
    if (!isset(STAGES[$stage])) json_out(['error' => 'bad stage'], 422);
    if ($stage !== $d['stage']) {
        q('UPDATE deals SET stage = ?, stage_changed_at = NOW() WHERE id = ?', [$stage, $id]);
        log_activity('stage', 'deal', $id, STAGES[$d['stage']] . ' → ' . STAGES[$stage] . ' (' . $d['title'] . ')', $d['company_id'] ? (int)$d['company_id'] : null);
    }
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') json_out(['ok' => true]);
    flash('Stage updated.');
    redirect("/deals/$id");
}

function h_deal_delete(int $id): void {
    q("DELETE FROM activities WHERE entity_type = 'deal' AND entity_id = ?", [$id]);
    q('DELETE FROM deals WHERE id = ?', [$id]);
    flash('Deal deleted.');
    redirect('/pipeline');
}

// ---------- activities ----------
function entity_company(string $type, int $id): ?int {
    $sql = match ($type) {
        'company' => 'SELECT id FROM companies WHERE id = ?',
        'contact' => 'SELECT company_id FROM contacts WHERE id = ?',
        'deal'    => 'SELECT company_id FROM deals WHERE id = ?',
        'site'    => 'SELECT company_id FROM sites WHERE id = ?',
        'ticket'  => 'SELECT company_id FROM tickets WHERE id = ?',
        'quote'   => 'SELECT company_id FROM quotes WHERE id = ?',
        default   => null,
    };
    if (!$sql) return null;
    $v = val($sql, [$id]);
    return $v ? (int)$v : null;
}
function entity_url(string $type, int $id): string {
    return match ($type) { 'company' => "/companies/$id", 'contact' => "/contacts/$id", 'deal' => "/deals/$id",
        'site' => "/sites/$id", 'ticket' => "/tickets/$id", 'quote' => "/quotes/$id", default => '/' };
}

function h_activity_add(): void {
    $type = (string)post('entity_type', '');
    $eid = (int)post('entity_id', 0);
    $kind = (string)post('type', 'note');
    $body = (string)post('body', '');
    if (!in_array($type, ['company', 'contact', 'deal', 'site', 'ticket', 'quote'], true) || !$eid) not_found();
    if (!isset(ACTIVITY_TYPES[$kind])) $kind = 'note';
    if ($body !== '') {
        log_activity($kind, $type, $eid, $body, entity_company($type, $eid));
        if ($type === 'ticket') ticket_responded($eid);
        flash('Logged.');
    }
    redirect(entity_url($type, $eid) . '#timeline');
}

function h_activity_delete(int $id): void {
    $a = one('SELECT * FROM activities WHERE id = ?', [$id]);
    if (!$a) not_found();
    if ((int)$a['user_id'] !== uid() && !is_role('admin')) forbidden();
    q('DELETE FROM activities WHERE id = ?', [$id]);
    redirect(entity_url($a['entity_type'], (int)$a['entity_id']) . '#timeline');
}

// ---------- tasks ----------
function h_tasks(): void {
    $f = (string)get('f', 'mine');
    $w = match ($f) {
        'all'     => 't.done_at IS NULL',
        'overdue' => 't.done_at IS NULL AND t.due_date < CURDATE()',
        'done'    => 't.done_at IS NOT NULL',
        default   => 't.done_at IS NULL AND t.assigned_to = ' . (int)uid(),
    };
    $order = $f === 'done' ? 't.done_at DESC' : 't.due_date IS NULL, t.due_date, t.id';
    $rows = all("SELECT t.*, u.name assignee, u.id assignee_uid, c.name company, d.title deal, tk.number ticket
                 FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to LEFT JOIN companies c ON c.id = t.company_id
                 LEFT JOIN deals d ON d.id = t.deal_id LEFT JOIN tickets tk ON tk.id = t.ticket_id
                 WHERE $w ORDER BY $order LIMIT 300");
    view('tasks', ['title' => 'Tasks', 'rows' => $rows, 'f' => $f]);
}

function h_task_add(): void {
    $title = (string)post('title', '');
    $back = (string)post('back', '/tasks');
    if (!str_starts_with($back, '/') || str_starts_with($back, '//')) $back = '/tasks';
    if ($title !== '') {
        $deal = int_or_null(post('deal_id'));
        $ticket = int_or_null(post('ticket_id'));
        $company = int_or_null(post('company_id'))
            ?? ($deal ? entity_company('deal', $deal) : null)
            ?? ($ticket ? entity_company('ticket', $ticket) : null);
        $due = str_or_null(post('due_date'));
        if ($due && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = null;
        q('INSERT INTO tasks (title, notes, due_date, assigned_to, created_by, company_id, deal_id, ticket_id) VALUES (?,?,?,?,?,?,?,?)', [
            $title, str_or_null(post('notes')), $due,
            int_or_null(post('assigned_to')) ?? uid(), uid(), $company, $deal, $ticket,
        ]);
        flash('Task added.');
    }
    redirect($back);
}

function h_task_toggle(int $id): void {
    q('UPDATE tasks SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ?', [$id]);
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') json_out(['ok' => true]);
    redirect(back('/tasks'));
}

function h_task_delete(int $id): void {
    q('DELETE FROM tasks WHERE id = ?', [$id]);
    redirect(back('/tasks'));
}
