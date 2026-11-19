<?php
declare(strict_types=1);
/** Import & export: CSV exports, companies/contacts import, database + files backup (admins only) */

/** name => [label, SQL] for each CSV export */
function export_defs(): array {
    return [
        'companies' => ['Companies', "SELECT c.id AS ID, c.name AS Company, c.industry AS Industry, c.phone AS Phone, c.email AS Email, c.website AS Website,
            c.address AS Address, c.town AS Town, c.province AS Province, u.name AS Owner, c.notes AS Notes, c.created_at AS Created
            FROM companies c LEFT JOIN users u ON u.id = c.owner_id ORDER BY c.name"],
        'contacts' => ['Contacts', "SELECT ct.id AS ID, c.name AS Company, ct.first_name AS `First name`, ct.last_name AS `Last name`, ct.title AS `Job title`,
            ct.phone AS Phone, ct.mobile AS Mobile, ct.email AS Email, IF(ct.is_primary, 'yes', '') AS `Primary`, ct.notes AS Notes, ct.created_at AS Created
            FROM contacts ct LEFT JOIN companies c ON c.id = ct.company_id ORDER BY c.name, ct.last_name, ct.first_name"],
        'deals' => ['Deals', "SELECT d.id AS ID, d.title AS Deal, c.name AS Company, TRIM(CONCAT(IFNULL(ct.first_name,''), ' ', IFNULL(ct.last_name,''))) AS Contact,
            d.service AS Service, d.stage AS Stage, d.value AS Value, d.source AS Source, u.name AS Owner, d.expected_close AS `Expected close`,
            d.lost_reason AS `Lost reason`, d.created_at AS Created
            FROM deals d LEFT JOIN companies c ON c.id = d.company_id LEFT JOIN contacts ct ON ct.id = d.contact_id LEFT JOIN users u ON u.id = d.owner_id ORDER BY d.id"],
        'sites' => ['Sites', "SELECT s.id AS ID, c.name AS Company, s.name AS Site, s.address AS Address, s.town AS Town, s.latitude AS Latitude, s.longitude AS Longitude,
            s.connectivity AS Connectivity, s.access_notes AS `Access notes` FROM sites s JOIN companies c ON c.id = s.company_id ORDER BY c.name, s.name"],
        'equipment' => ['Equipment (installed & stock)', "SELECT e.id AS ID, e.status AS Status, c.name AS Company, s.name AS Site, e.category AS Category, e.make AS Make,
            e.model AS Model, e.serial AS Serial, e.mac AS MAC, e.ip_address AS IP, e.phone_number AS `Phone number`, e.installed_at AS Installed,
            e.warranty_until AS `Warranty until`, e.stock_location AS `Stock location`, e.supplier AS Supplier, e.purchased_at AS Purchased, e.cost AS Cost, e.notes AS Notes
            FROM equipment e LEFT JOIN sites s ON s.id = e.site_id LEFT JOIN companies c ON c.id = s.company_id ORDER BY e.status, c.name, e.category"],
        'consumables' => ['Consumables', "SELECT id AS ID, name AS Item, category AS Category, sku AS SKU, unit AS Unit, qty_on_hand AS `On hand`,
            reorder_level AS `Re-order at`, unit_cost AS `Unit cost`, location AS Location, supplier AS Supplier, IF(active, 'yes', 'archived') AS Active
            FROM consumables ORDER BY category, name"],
        'catalog' => ['Price list', "SELECT sku AS SKU, name AS Item, description AS Description, kind AS Type, unit AS Unit, price AS Price, cost AS Cost, IF(pst_applies,'yes','') AS PST,
            stock_type AS `Stock link`, CONCAT_WS(' ', eq_make, eq_model) AS `Equipment model`, IF(active,'yes','hidden') AS Active FROM catalog_items ORDER BY kind, name"],
        'invoices' => ['Invoices', "SELECT IFNULL(i.number, CONCAT('DRAFT-', i.id)) AS Invoice, c.name AS Customer, i.status AS Status, i.issued_on AS `Invoice date`, i.due_on AS `Due date`,
            i.terms_days AS `Terms (days)`, i.subtotal AS Subtotal, i.gst AS GST, i.pst AS PST, i.total AS Total, i.paid_on AS `Paid on`, i.paid_method AS `Paid by`, i.bill_to_email AS `Billed to`
            FROM invoices i JOIN companies c ON c.id = i.company_id ORDER BY i.id"],
        'invoicelines' => ['Invoice lines', "SELECT IFNULL(i.number, CONCAT('DRAFT-', i.id)) AS Invoice, c.name AS Customer, i.issued_on AS `Invoice date`, ii.description AS Description,
            ii.kind AS Type, ii.qty AS Qty, ii.unit AS Unit, ii.unit_price AS `Unit price`, ROUND(ii.qty * ii.unit_price, 2) AS Amount, IF(ii.pst_applies, 'yes', '') AS PST, t.number AS Ticket
            FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id JOIN companies c ON c.id = i.company_id LEFT JOIN tickets t ON t.id = ii.ticket_id ORDER BY i.id, ii.position"],
        'tickets' => ['Tickets', "SELECT t.number AS Ticket, t.subject AS Subject, c.name AS Company, s.name AS Site, t.priority AS Priority, t.status AS Status,
            u.name AS Assigned, t.minutes_spent AS Minutes, t.created_at AS Opened, t.first_response_at AS `First response`, t.closed_at AS Closed
            FROM tickets t JOIN companies c ON c.id = t.company_id LEFT JOIN sites s ON s.id = t.site_id LEFT JOIN users u ON u.id = t.assigned_to ORDER BY t.id"],
        'quotes' => ['Quotes', "SELECT q.number AS Quote, q.title AS Title, c.name AS Company, q.status AS Status, q.issued_on AS Issued, q.valid_until AS `Valid until`,
            (SELECT ROUND(SUM(i.qty * i.unit_price), 2) FROM quote_items i WHERE i.quote_id = q.id) AS `Subtotal`, u.name AS `Prepared by`
            FROM quotes q JOIN companies c ON c.id = q.company_id LEFT JOIN users u ON u.id = q.created_by ORDER BY q.id"],
    ];
}

/** Stops spreadsheet formula injection (=, +, @ … at the start of a text cell) */
function csv_cell(mixed $v): string {
    $v = (string)($v ?? '');
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) $v = "'" . $v;
    return $v;
}

function csv_download(string $filename, array $header, iterable $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                         // BOM so Excel opens UTF-8 correctly
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $r) fputcsv($out, array_map('csv_cell', array_values($r)), ',', '"', '');
    fclose($out);
    exit;
}

function h_data(): void {
    require_role('admin');
    $counts = [];
    $tables = ['companies' => 'companies', 'contacts' => 'contacts', 'deals' => 'deals', 'sites' => 'sites', 'equipment' => 'equipment',
               'consumables' => 'consumables', 'catalog' => 'catalog_items', 'tickets' => 'tickets', 'quotes' => 'quotes', 'invoices' => 'invoices', 'invoicelines' => 'invoice_items'];
    foreach ($tables as $k => $t) $counts[$k] = (int)val("SELECT COUNT(*) FROM `$t`");
    view('data', ['title' => 'Import & export', 'defs' => export_defs(), 'counts' => $counts,
        'files' => one('SELECT COUNT(*) AS n, COALESCE(SUM(size), 0) AS bytes FROM files'),
        'lastBackup' => setting('last_backup_at'), 'preview' => null]);
}

function h_data_export(string $what): void {
    require_role('admin');
    $d = export_defs()[$what] ?? null;
    if (!$d) not_found();
    $st = db()->query($d[1]);
    $cols = [];
    for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = $st->getColumnMeta($i)['name'];
    csv_download("terralink-$what-" . date('Y-m-d') . '.csv', $cols, (function () use ($st) { while ($r = $st->fetch(PDO::FETCH_NUM)) yield $r; })());
}

// ---------- import ----------
const IMPORT_COLS = [
    'company'       => ['Company', ['company', 'company name', 'business', 'organization', 'organisation', 'account']],
    'industry'      => ['Industry', ['industry', 'sector', 'type']],
    'c_phone'       => ['Company phone', ['company phone', 'main phone', 'office phone', 'business phone']],
    'c_email'       => ['Company email', ['company email', 'general email', 'office email']],
    'website'       => ['Website', ['website', 'web', 'url', 'site']],
    'address'       => ['Address', ['address', 'street', 'street address', 'address 1']],
    'town'          => ['Town', ['town', 'city', 'community']],
    'province'      => ['Province', ['province', 'prov', 'state', 'region']],
    'first_name'    => ['First name', ['first name', 'firstname', 'first', 'given name']],
    'last_name'     => ['Last name', ['last name', 'lastname', 'last', 'surname', 'family name']],
    'name'          => ['Contact name', ['name', 'contact', 'contact name', 'full name']],
    'title'         => ['Job title', ['job title', 'title', 'position', 'role']],
    'phone'         => ['Phone', ['phone', 'phone number', 'direct phone', 'work phone', 'telephone']],
    'mobile'        => ['Mobile', ['mobile', 'cell', 'cell phone', 'mobile phone']],
    'email'         => ['Email', ['email', 'e-mail', 'email address', 'contact email']],
    'notes'         => ['Notes', ['notes', 'note', 'comments', 'description']],
];

function h_data_template(): void {
    require_role('admin');
    $h = ['Company', 'Industry', 'Company phone', 'Company email', 'Website', 'Address', 'Town', 'Province', 'First name', 'Last name', 'Job title', 'Phone', 'Mobile', 'Email', 'Notes'];
    csv_download('terralink-import-template.csv', $h, [
        ['Example Logging Ltd', 'Logging & Forestry', '250-555-0100', 'office@example.com', 'example.com', '123 Main St', 'Houston', 'BC', 'Pat', 'Example', 'Owner', '', '250-555-0101', 'pat@example.com', 'Met at trade show'],
    ]);
}

/** Maps a CSV header row to our field keys */
function import_map(array $header): array {
    $map = [];
    foreach ($header as $i => $h) {
        $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h)));
        $h = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $h));
        foreach (IMPORT_COLS as $key => [, $aliases]) {
            if (in_array($h, array_map(fn($a) => str_replace('-', ' ', $a), $aliases), true) && !in_array($key, $map, true)) { $map[$i] = $key; break; }
        }
    }
    return $map;
}

function import_industry(string $v): string {
    foreach (INDUSTRIES as $i) if (strcasecmp(trim($v), $i) === 0) return $i;
    foreach (INDUSTRIES as $i) if ($v !== '' && stripos($i, trim($v)) !== false) return $i;
    return 'Other';
}

/** Works out what each row will do. Returns rows with 'action' new|add|skip|err and a 'why' */
function import_plan(array $rows): array {
    $companies = [];
    foreach (all('SELECT id, name FROM companies') as $c) $companies[mb_strtolower(trim($c['name']))] = (int)$c['id'];
    $emails = [];
    foreach (all("SELECT LOWER(email) AS e FROM contacts WHERE email IS NOT NULL AND email <> ''") as $c) $emails[$c['e']] = true;
    $seenEmail = []; $newCo = [];
    foreach ($rows as &$r) {
        if ($r['name'] !== '' && $r['first_name'] === '' && $r['last_name'] === '') {
            $parts = preg_split('/\s+/', $r['name'], 2);
            $r['first_name'] = $parts[0]; $r['last_name'] = $parts[1] ?? '';
        }
        $key = mb_strtolower($r['company']);
        $em = strtolower($r['email']);
        $r['company_id'] = $companies[$key] ?? null;
        if ($r['company'] === '' && $r['first_name'] === '') { $r['action'] = 'err'; $r['why'] = 'No company or contact name'; continue; }
        if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) { $r['action'] = 'err'; $r['why'] = 'Email looks wrong'; continue; }
        if ($em !== '' && (isset($emails[$em]) || isset($seenEmail[$em]))) { $r['action'] = 'skip'; $r['why'] = 'Contact with this email already exists'; continue; }
        if ($em !== '') $seenEmail[$em] = true;
        if ($r['company'] !== '' && !$r['company_id'] && !isset($newCo[$key])) {
            $newCo[$key] = true;
            $r['action'] = 'new'; $r['why'] = 'New company' . ($r['first_name'] !== '' ? ' + contact' : '');
        } elseif ($r['first_name'] !== '') {
            $r['action'] = 'add'; $r['why'] = $r['company'] !== '' ? 'Add contact to ' . ($r['company_id'] ? 'existing' : 'new') . ' company' : 'Contact without company';
        } else {
            $r['action'] = 'skip'; $r['why'] = 'Company already exists';
        }
    }
    return $rows;
}

function import_tmp(string $token): string {
    $d = upload_dir() . '/tmp';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    foreach (glob("$d/import-*.json") ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);
    return "$d/import-$token.json";
}

function h_data_import(): void {
    require_role('admin');
    $u = $_FILES['csv'] ?? null;
    if (!$u || $u['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($u['tmp_name'])) { flash('Choose a CSV file to import.', 'bad'); redirect('/data#import'); }
    $raw = (string)file_get_contents($u['tmp_name']);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $first = strtok($raw, "\n");
    $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
    $fh = fopen('php://memory', 'r+'); fwrite($fh, $raw); rewind($fh);
    $header = fgetcsv($fh, null, $delim, '"', '') ?: [];
    $map = import_map($header);
    if (!in_array('company', $map, true) && !array_intersect(['first_name', 'name'], $map)) {
        flash('No "Company" or "First name" column found. Use the template so the columns are recognized.', 'bad'); redirect('/data#import');
    }
    $rows = []; $line = 1;
    while (($cells = fgetcsv($fh, null, $delim, '"', '')) !== false) {
        $line++;
        if (count(array_filter($cells, fn($c) => trim((string)$c) !== '')) === 0) continue;
        $r = array_fill_keys(array_keys(IMPORT_COLS), '');
        foreach ($map as $i => $key) $r[$key] = trim((string)($cells[$i] ?? ''));
        $r['line'] = $line;
        $rows[] = $r;
        if (count($rows) > 3000) { flash('That file has more than 3,000 rows. Split it into smaller files.', 'bad'); redirect('/data#import'); }
    }
    if (!$rows) { flash('The file has no data rows.', 'bad'); redirect('/data#import'); }
    $rows = import_plan($rows);
    $token = bin2hex(random_bytes(12));
    file_put_contents(import_tmp($token), json_encode(['rows' => $rows, 'name' => $u['name']]));
    $_SESSION['import_token'] = $token;
    $found = array_map(fn($k) => IMPORT_COLS[$k][0], array_values($map));
    view('data', ['title' => 'Import preview', 'preview' => $rows, 'fileName' => $u['name'], 'found' => $found,
        'unused' => array_values(array_diff_key($header, $map)), 'token' => $token]);
}

function h_data_import_confirm(): void {
    require_role('admin');
    $token = (string)post('token');
    if (!$token || $token !== ($_SESSION['import_token'] ?? '') || !preg_match('/^[a-f0-9]{24}$/', $token) || !is_file(import_tmp($token))) {
        flash('That import expired. Upload the file again.', 'bad'); redirect('/data#import');
    }
    $data = json_decode((string)file_get_contents(import_tmp($token)), true);
    $rows = import_plan($data['rows'] ?? []);              // re-check against the database as it is now
    $cos = 0; $cts = 0; $ids = [];
    db()->beginTransaction();
    try {
        foreach ($rows as $r) {
            if (!in_array($r['action'], ['new', 'add'], true)) continue;
            $key = mb_strtolower($r['company']);
            $cid = $r['company_id'] ?: ($ids[$key] ?? null);
            if (!$cid && $r['company'] !== '') {
                q('INSERT INTO companies (name, industry, phone, email, website, address, town, province, notes, owner_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                    mb_substr($r['company'], 0, 190), import_industry($r['industry']), str_or_null($r['c_phone']) ?? ($r['first_name'] === '' ? str_or_null($r['phone']) : null),
                    str_or_null($r['c_email']) ?? ($r['first_name'] === '' ? str_or_null($r['email']) : null), str_or_null($r['website']), str_or_null($r['address']),
                    str_or_null($r['town']), $r['province'] !== '' ? mb_substr($r['province'], 0, 40) : 'BC', $r['first_name'] === '' ? str_or_null($r['notes']) : null, uid()]);
                $cid = $ids[$key] = (int)db()->lastInsertId();
                log_activity('system', 'company', $cid, 'Imported from CSV (' . ($data['name'] ?? 'file') . ')', $cid);
                $cos++;
            }
            if ($r['first_name'] !== '') {
                $primary = $cid ? (int)!val('SELECT COUNT(*) FROM contacts WHERE company_id = ?', [$cid]) : 0;
                q('INSERT INTO contacts (company_id, first_name, last_name, title, phone, mobile, email, is_primary, notes) VALUES (?,?,?,?,?,?,?,?,?)', [
                    $cid, mb_substr($r['first_name'], 0, 80), str_or_null(mb_substr($r['last_name'], 0, 80)), str_or_null($r['title']),
                    str_or_null($r['phone']), str_or_null($r['mobile']), str_or_null(strtolower($r['email'])), $primary, str_or_null($r['notes'])]);
                $cts++;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash('Import stopped, nothing was saved: ' . $e->getMessage(), 'bad'); redirect('/data#import');
    }
    @unlink(import_tmp($token)); unset($_SESSION['import_token']);
    flash("Imported $cos compan" . ($cos === 1 ? 'y' : 'ies') . " and $cts contact" . ($cts === 1 ? '' : 's') . '.');
    redirect('/companies');
}

// ---------- backup ----------
function sql_val(PDO $pdo, mixed $v): string {
    return $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string)$v : $pdo->quote((string)$v));
}

/** Streams a gzipped SQL dump of the whole database (restore with phpMyAdmin → Import) */
function h_data_backup(): void {
    require_role('admin');
    @set_time_limit(300);
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', ['last_backup_at', date('Y-m-d H:i:s')]);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $pdo = db();
    $z = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="terralink-crm-' . date('Y-m-d-Hi') . '.sql.gz"');
    header('Cache-Control: no-store');
    while (ob_get_level()) ob_end_clean();
    $out = function (string $s) use ($z) { echo deflate_add($z, $s, ZLIB_NO_FLUSH); };
    $out("-- TerraLink CRM backup " . date('Y-m-d H:i:s T') . " · app " . APP_VERSION . "\n-- Restore: phpMyAdmin → select the database → Import this file.\n\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as [$t]) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $out("DROP TABLE IF EXISTS `$t`;\n$create;\n\n");
        if ($t === 'password_resets') continue;              // one-time links: never worth restoring
        $st = $pdo->query("SELECT * FROM `$t`");
        $buf = []; $cols = null;
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $cols ??= '`' . implode('`, `', array_keys($r)) . '`';
            $buf[] = '(' . implode(', ', array_map(fn($v) => sql_val($pdo, $v), $r)) . ')';
            if (count($buf) >= 200) { $out("INSERT INTO `$t` ($cols) VALUES\n" . implode(",\n", $buf) . ";\n"); $buf = []; }
        }
        if ($buf) $out("INSERT INTO `$t` ($cols) VALUES\n" . implode(",\n", $buf) . ";\n");
        $out("\n");
    }
    $out("SET FOREIGN_KEY_CHECKS = 1;\n");
    echo deflate_add($z, '', ZLIB_FINISH);
    exit;
}

/** All uploaded photos & files as one zip, in folders by record */
function h_data_files_zip(): void {
    require_role('admin');
    if (!class_exists('ZipArchive')) { flash('Zip isn\'t available on this server.', 'bad'); redirect('/data'); }
    @set_time_limit(600);
    $tmp = tempnam(sys_get_temp_dir(), 'crmzip');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $n = 0;
    foreach (all('SELECT * FROM files ORDER BY entity_type, entity_id, id') as $f) {
        $p = file_path($f);
        if (!is_file($p)) continue;
        $safe = preg_replace('/[^\w.\- ]+/u', '_', $f['name']);
        $zip->addFile($p, "{$f['entity_type']}/{$f['entity_id']}/{$f['id']}-$safe");
        $zip->setCompressionName("{$f['entity_type']}/{$f['entity_id']}/{$f['id']}-$safe", $f['is_image'] ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
        $n++;
    }
    if (!$n) { $zip->close(); @unlink($tmp); flash('There are no files to download yet.', 'bad'); redirect('/data'); }
    $zip->close();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="terralink-crm-files-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    while (ob_get_level()) ob_end_clean();
    readfile($tmp);
    @unlink($tmp);
    exit;
}
