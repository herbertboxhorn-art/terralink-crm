<?php
declare(strict_types=1);
// Recurring service plans: a visit every N months becomes a ticket automatically (see src/automation.php).

function h_plans(): void {
    $f = get('f', 'active');
    $where = $f === 'all' ? '1' : ($f === 'paused' ? 'p.active = 0' : 'p.active = 1');
    $plans = all("SELECT p.*, c.name company, s.name site, u.name assignee, t.number last_number, t.status last_status
                  FROM service_plans p JOIN companies c ON c.id = p.company_id LEFT JOIN sites s ON s.id = p.site_id
                  LEFT JOIN users u ON u.id = p.assigned_to LEFT JOIN tickets t ON t.id = p.last_ticket_id
                  WHERE $where ORDER BY p.active DESC, p.next_due, c.name");
    $mrr = (float)val('SELECT COALESCE(SUM(fee / every_months), 0) FROM service_plans WHERE active = 1');
    $soon = (int)val('SELECT COUNT(*) FROM service_plans WHERE active = 1 AND next_due <= CURDATE() + INTERVAL 30 DAY');
    view('plans', ['title' => 'Service plans', 'plans' => $plans, 'f' => $f, 'mrr' => $mrr, 'soon' => $soon,
        'activeCount' => (int)val('SELECT COUNT(*) FROM service_plans WHERE active = 1')]);
}

function h_plan_form(int $id = 0): void {
    $row = $id ? one('SELECT * FROM service_plans WHERE id = ?', [$id]) : [
        'company_id' => int_or_null(get('company_id')), 'site_id' => int_or_null(get('site_id')), 'every_months' => 3,
        'next_due' => date('Y-m-d', strtotime('+1 month')), 'lead_days' => 7, 'priority' => 'normal', 'active' => 1, 'assigned_to' => uid()];
    if ($id && !$row) not_found();
    if (!$id && $row['site_id'] && !$row['company_id']) $row['company_id'] = entity_company('site', (int)$row['site_id']);
    $cid = int_or_null($_POST['company_id'] ?? $row['company_id'] ?? null);
    $def = entity_def('plan', ['company_id' => $cid]);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        if ($vals['site_id'] && (int)val('SELECT company_id FROM sites WHERE id = ?', [$vals['site_id']]) !== (int)$vals['company_id']) $vals['site_id'] = null;
        $vals['lead_days'] = max(0, min(90, (int)($vals['lead_days'] ?? 7)));
        $vals['every_months'] = isset(PLAN_EVERY[(int)$vals['every_months']]) ? (int)$vals['every_months'] : 3;
        if (!$errors) {
            if (!$id) $vals['created_by'] = uid();
            $vals['due_day'] = (int)date('j', strtotime($vals['next_due']));
            $newId = save_entity($def, $vals, $id ?: null);
            log_activity('system', 'company', (int)$vals['company_id'], ($id ? 'Service plan updated: ' : 'Service plan added: ') . $vals['title']
                . ' (' . PLAN_EVERY[$vals['every_months']] . ', next ' . date('M j, Y', strtotime($vals['next_due'])) . ')', (int)$vals['company_id']);
            flash($id ? 'Plan saved.' : 'Service plan added. Its first ticket opens ' . $vals['lead_days'] . ' days before ' . date('M j', strtotime($vals['next_due'])) . '.');
            redirect('/plans');
        }
        $row = array_merge($row, $vals);
    }
    $recent = $id ? all('SELECT id, number, status, created_at FROM tickets WHERE plan_id = ? ORDER BY id DESC LIMIT 8', [$id]) : [];
    view('form', ['title' => $id ? 'Edit service plan' : 'New service plan', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'subtitle' => 'A ticket is opened automatically before each visit is due', 'cancel' => '/plans',
        'deleteUrl' => ($id && is_role('admin', 'sales')) ? "/plans/$id/delete" : null, 'dependent' => true,
        'planTickets' => $recent, 'planId' => $id]);
}

function h_plan_run(int $id): void {
    $p = one('SELECT * FROM service_plans WHERE id = ?', [$id]);
    if (!$p) not_found();
    $tid = plan_create_ticket($p, uid());
    flash('Ticket opened for ' . $p['title'] . '. Next visit moved to ' . date('M j, Y', strtotime((string)val('SELECT next_due FROM service_plans WHERE id = ?', [$id]))) . '.');
    redirect("/tickets/$tid");
}

function h_plan_delete(int $id): void {
    require_role('admin', 'sales');
    $p = one('SELECT * FROM service_plans WHERE id = ?', [$id]);
    if (!$p) not_found();
    q('DELETE FROM service_plans WHERE id = ?', [$id]);
    log_activity('system', 'company', (int)$p['company_id'], 'Service plan removed: ' . $p['title'], (int)$p['company_id']);
    flash('Plan deleted. Tickets it already opened stay.');
    redirect('/plans');
}

/** Plans card for company/site pages */
function plans_for(string $col, int $id): array {
    return all("SELECT p.*, s.name site FROM service_plans p LEFT JOIN sites s ON s.id = p.site_id WHERE p.$col = ? ORDER BY p.active DESC, p.next_due", [$id]);
}
