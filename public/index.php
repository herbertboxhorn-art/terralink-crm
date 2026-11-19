<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

ensure_schema();

// Client portal: its own hostname, session and logins
if (is_portal_request()) {
    require APP_ROOT . '/src/portal/app.php';
    portal_dispatch($method, $path);
    exit;
}

// Public API (header-key auth, no session)
if (str_starts_with($path, '/api/')) {
    require APP_ROOT . '/src/handlers/api.php';
    api_dispatch($method, $path);
    exit;
}

start_session();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

foreach (glob(APP_ROOT . '/src/handlers/*.php') as $f) if (!str_ends_with($f, 'api.php')) require $f;

// [methods, regex, handler, public?]
$routes = [
    ['GET|POST', '/login', 'h_login', true],
    ['GET|POST', '/setup', 'h_setup', true],
    ['GET|POST', '/forgot', 'h_forgot', true],
    ['GET|POST', '/reset', 'h_reset', true],
    ['POST', '/users/(\d+)/invite', 'h_user_invite'],
    ['GET', '/today', 'h_today'],
    ['POST', '/logout', 'h_logout', true],

    ['GET', '/', 'h_dashboard'],
    ['GET', '/search', 'h_search'],

    ['GET', '/companies', 'h_companies'],
    ['GET|POST', '/companies/new', 'h_company_form'],
    ['GET', '/companies/(\d+)', 'h_company_show'],
    ['GET|POST', '/companies/(\d+)/edit', 'h_company_form'],
    ['POST', '/companies/(\d+)/delete', 'h_company_delete'],

    ['GET', '/contacts', 'h_contacts'],
    ['GET|POST', '/contacts/new', 'h_contact_form'],
    ['GET', '/contacts/(\d+)', 'h_contact_show'],
    ['GET|POST', '/contacts/(\d+)/edit', 'h_contact_form'],
    ['POST', '/contacts/(\d+)/delete', 'h_contact_delete'],

    ['GET', '/pipeline', 'h_pipeline'],
    ['GET|POST', '/deals/new', 'h_deal_form'],
    ['GET', '/deals/(\d+)', 'h_deal_show'],
    ['GET|POST', '/deals/(\d+)/edit', 'h_deal_form'],
    ['POST', '/deals/(\d+)/stage', 'h_deal_stage'],
    ['POST', '/deals/(\d+)/delete', 'h_deal_delete'],

    ['POST', '/activities', 'h_activity_add'],
    ['POST', '/activities/(\d+)/delete', 'h_activity_delete'],

    ['GET', '/tasks', 'h_tasks'],
    ['POST', '/tasks', 'h_task_add'],
    ['POST', '/tasks/(\d+)/toggle', 'h_task_toggle'],
    ['POST', '/tasks/(\d+)/delete', 'h_task_delete'],

    ['GET|POST', '/sites/new', 'h_site_form'],
    ['GET', '/sites/(\d+)', 'h_site_show'],
    ['GET|POST', '/sites/(\d+)/edit', 'h_site_form'],
    ['POST', '/sites/(\d+)/delete', 'h_site_delete'],

    ['GET', '/equipment', 'h_equipment'],
    ['GET|POST', '/equipment/new', 'h_equipment_form'],
    ['GET|POST', '/equipment/receive', 'h_equipment_receive'],
    ['GET|POST', '/equipment/(\d+)/install', 'h_equipment_install'],
    ['POST', '/equipment/(\d+)/return', 'h_equipment_return'],
    ['GET|POST', '/sites/(\d+)/add-equipment', 'h_site_add_equipment'],

    ['GET|POST', '/consumables/new', 'h_consumable_form'],
    ['GET', '/consumables/(\d+)', 'h_consumable_show'],
    ['GET|POST', '/consumables/(\d+)/edit', 'h_consumable_form'],
    ['POST', '/consumables/(\d+)/move', 'h_consumable_move'],
    ['POST', '/consumables/move', 'h_consumable_move'],
    ['POST', '/consumables/(\d+)/delete', 'h_consumable_delete'],
    ['GET|POST', '/equipment/(\d+)/edit', 'h_equipment_form'],
    ['POST', '/equipment/(\d+)/delete', 'h_equipment_delete'],

    ['GET', '/tickets', 'h_tickets'],
    ['GET|POST', '/tickets/new', 'h_ticket_form'],
    ['GET', '/tickets/(\d+)', 'h_ticket_show'],
    ['GET|POST', '/tickets/(\d+)/edit', 'h_ticket_form'],
    ['POST', '/tickets/(\d+)/update', 'h_ticket_update'],
    ['POST', '/tickets/(\d+)/delete', 'h_ticket_delete'],
    ['POST', '/tickets/(\d+)/charges', 'h_ticket_charge_add'],
    ['POST', '/tickets/(\d+)/charges/time', 'h_ticket_charge_time'],
    ['POST', '/tickets/(\d+)/charges/(\d+)/delete', 'h_ticket_charge_delete'],
    ['POST', '/tickets/(\d+)/billing', 'h_ticket_billing'],
    ['GET', '/tickets/(\d+)/summary', 'h_ticket_summary'],
    ['GET', '/billing', 'h_billing'],
    ['POST', '/billing/mark', 'h_billing_mark'],
    ['GET', '/invoices', 'h_invoices'],
    ['POST', '/invoices/generate', 'h_invoices_generate'],
    ['POST', '/invoices/approve', 'h_invoices_approve'],
    ['GET', '/invoices/(\d+)', 'h_invoice_show'],
    ['GET', '/invoices/(\d+)/pdf', 'h_invoice_pdf'],
    ['POST', '/invoices/(\d+)/edit', 'h_invoice_edit'],
    ['POST', '/invoices/(\d+)/lines', 'h_invoice_line_add'],
    ['POST', '/invoices/(\d+)/lines/(\d+)/delete', 'h_invoice_line_delete'],
    ['POST', '/invoices/(\d+)/approve', 'h_invoice_approve'],
    ['POST', '/invoices/(\d+)/paid', 'h_invoice_paid'],
    ['POST', '/invoices/(\d+)/void', 'h_invoice_void'],

    ['GET', '/quotes', 'h_quotes'],
    ['GET|POST', '/quotes/new', 'h_quote_form'],
    ['GET', '/quotes/(\d+)', 'h_quote_show'],
    ['GET|POST', '/quotes/(\d+)/edit', 'h_quote_form'],
    ['GET', '/quotes/(\d+)/pdf', 'h_quote_pdf'],
    ['POST', '/quotes/(\d+)/status', 'h_quote_status'],
    ['POST', '/quotes/(\d+)/duplicate', 'h_quote_duplicate'],
    ['POST', '/quotes/(\d+)/delete', 'h_quote_delete'],

    ['GET', '/users', 'h_users'],
    ['GET|POST', '/users/new', 'h_user_form'],
    ['GET|POST', '/users/(\d+)/edit', 'h_user_form'],
    ['GET|POST', '/account', 'h_account'],
    ['GET|POST', '/settings', 'h_settings'],

    ['GET', '/options/company/(\d+)', 'h_company_options'],

    ['GET', '/plans', 'h_plans'],
    ['GET', '/plans/templates', 'h_plan_templates'],
    ['GET|POST', '/plans/templates/new', 'h_plan_template_form'],
    ['GET|POST', '/plans/templates/(\d+)/edit', 'h_plan_template_form'],
    ['POST', '/plans/templates/(\d+)/delete', 'h_plan_template_delete'],
    ['GET|POST', '/plans/new', 'h_plan_form'],
    ['GET|POST', '/plans/(\d+)/edit', 'h_plan_form'],
    ['POST', '/plans/(\d+)/run', 'h_plan_run'],
    ['POST', '/plans/(\d+)/delete', 'h_plan_delete'],
    ['GET|POST', '/quotes/(\d+)/email', 'h_quote_email'],
    ['POST', '/quotes/(\d+)/reserve', 'h_quote_reserve'],
    ['POST', '/quotes/(\d+)/release', 'h_quote_release'],
    ['GET', '/catalog', 'h_catalog'],
    ['GET|POST', '/catalog/new', 'h_catalog_form'],
    ['GET|POST', '/catalog/(\d+)/edit', 'h_catalog_form'],
    ['POST', '/catalog/(\d+)/delete', 'h_catalog_delete'],
    ['POST', '/catalog/import', 'h_catalog_import'],

    ['POST', '/files', 'h_file_upload'],
    ['GET', '/files/(\d+)', 'h_file_get'],
    ['GET', '/files/(\d+)/thumb', 'h_file_thumb'],
    ['POST', '/files/(\d+)/delete', 'h_file_delete'],
    ['POST', '/files/(\d+)/share', 'h_file_share'],
    ['POST', '/companies/(\d+)/portal-users', 'h_portal_user_add'],
    ['GET|POST', '/portal-users/(\d+)/edit', 'h_portal_user_edit'],
    ['POST', '/portal-users/(\d+)/invite', 'h_portal_user_invite'],
    ['POST', '/portal-users/(\d+)/delete', 'h_portal_user_delete'],

    ['GET', '/data', 'h_data'],
    ['GET', '/data/export/([a-z]+)', 'h_data_export'],
    ['GET', '/data/template', 'h_data_template'],
    ['POST', '/data/import', 'h_data_import'],
    ['POST', '/data/import/confirm', 'h_data_import_confirm'],
    ['GET', '/data/backup', 'h_data_backup'],
    ['GET', '/data/files', 'h_data_files_zip'],
];

foreach ($routes as $r) {
    [$methods, $pattern, $handler] = $r;
    $public = $r[3] ?? false;
    if (!in_array($method, explode('|', $methods), true)) continue;
    if (!preg_match('#^' . $pattern . '$#', $path, $m)) continue;
    if (empty($public)) require_login();
    if ($method === 'POST') csrf_check();
    array_shift($m);
    $handler(...array_map(fn($x) => ctype_digit($x) ? (int)$x : $x, $m));
    exit;
}
not_found();
