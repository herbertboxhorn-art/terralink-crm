<?php
// LOCAL TESTING ONLY — fills the database with made-up demo records.
// Refuses to run unless APP_ENV=development.
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/handlers/inventory.php';
if (!IS_DEV) { fwrite(STDERR, "Refusing: APP_ENV is not development.\n"); exit(1); }

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['portal_resets', 'portal_users', 'consumable_reservations', 'catalog_items', 'service_plans', 'files', 'mail_log', 'password_resets', 'consumable_moves', 'consumables', 'quote_items', 'quotes', 'tasks', 'tickets', 'equipment', 'sites', 'activities', 'deals', 'contacts', 'companies', 'users', 'login_attempts'] as $t) $pdo->exec("TRUNCATE $t");
$pdo->exec("UPDATE counters SET value = 0");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pw = password_hash('demo-password-123', PASSWORD_DEFAULT);
q('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?),(?,?,?,?),(?,?,?,?)', [
  'Herb Boxhorn', 'herb@terralinksystems.com', $pw, 'admin',
  'Demo Tech', 'tech@example.test', $pw, 'tech',
  'Demo Sales', 'sales@example.test', $pw, 'sales']);
$_SESSION = ['uid' => 1]; // so log_activity() records Herb

$companies = [
  ['Ridgeback Timber Co.', 'Logging & Forestry', 'Houston', '250-555-0101', 'office@ridgeback.example'],
  ['Morice Valley Excavating', 'Construction & Excavation', 'Houston', '250-555-0102', 'info@morice.example'],
  ['Village of Pine Hollow', 'Municipalities', 'Burns Lake', '250-555-0103', 'admin@pinehollow.example'],
  ['Telkwa Creek Law Group', 'Law Firms', 'Smithers', '250-555-0104', 'reception@telkwalaw.example'],
  ['Stellako Nation Band Office', 'First Nations & Band Offices', 'Fraser Lake', '250-555-0105', 'office@stellako.example'],
  ['Northern Lakes Camp & Lodge', 'Camps & Lodges', 'Fort St. James', '250-555-0106', 'bookings@nllodge.example'],
  ['Bulkley Family Clinic', 'Clinics & Health Offices', 'Smithers', '250-555-0107', 'frontdesk@bfclinic.example'],
];
$cids = [];
foreach ($companies as [$n, $i, $t, $p, $e]) {
  q('INSERT INTO companies (name, industry, town, phone, email, owner_id) VALUES (?,?,?,?,?,1)', [$n, $i, $t, $p, $e]);
  $cids[] = (int)$pdo->lastInsertId();
}
$people = [[0,'Dale','Kowalski','Operations Manager'],[1,'Sandra','Reyes','Owner'],[2,'Mark','Lindqvist','CAO'],[3,'Priya','Nair','Office Manager'],[4,'Joseph','Michell','Band Administrator'],[5,'Katie','Brennan','Lodge Manager'],[6,'Dr. Alan','Cho','Physician / Owner']];
$ctids = [];
foreach ($people as [$ci, $f, $l, $ti]) {
  q('INSERT INTO contacts (company_id, first_name, last_name, title, mobile, email, is_primary) VALUES (?,?,?,?,?,?,1)',
    [$cids[$ci], $f, $l, $ti, '250-555-02' . str_pad((string)$ci, 2, '0', STR_PAD_LEFT), strtolower($f[0] . $l) . '@' . explode('@', $companies[$ci][4])[1]]);
  $ctids[] = (int)$pdo->lastInsertId();
}

$deals = [
  [0,'Starlink + mesh Wi-Fi for 3 cutblock camps','Starlink & Remote Sites','audit_booked',18500,'referral'],
  [1,'Yard office network & cameras','Hardware & Networks','quoted',9200,'website'],
  [2,'Council chambers phone system','Business Phones','new',0,'website'],
  [3,'Managed IT + backups','Business IT','won',14400,'referral'],
  [4,'AI intake assistant for band office','AI Automation','new',6000,'event'],
  [5,'Guest Wi-Fi + Starlink failover','Starlink & Remote Sites','quoted',11800,'cold'],
  [6,'Clinic workstation refresh','Hardware & Networks','lost',7600,'cold'],
];
foreach ($deals as [$ci, $t, $s, $st, $v, $src]) {
  q('INSERT INTO deals (company_id, contact_id, title, service, stage, value, source, owner_id) VALUES (?,?,?,?,?,?,?,1)', [$cids[$ci], $ctids[$ci], $t, $s, $st, $v, $src]);
  log_activity('system', 'deal', (int)$pdo->lastInsertId(), "Deal created: $t", $cids[$ci]);
}

// Sites & equipment
q("INSERT INTO sites (company_id, name, address, town, latitude, longitude, connectivity, access_notes) VALUES (?,?,?,?,?,?,?,?)",
  [$cids[0], 'Main office', '1200 Example Rd', 'Houston', 54.398, -126.648, 'Fibre', null]);
$s1 = (int)$pdo->lastInsertId();
q("INSERT INTO sites (company_id, name, address, town, latitude, longitude, connectivity, access_notes) VALUES (?,?,?,?,?,?,?,?)",
  [$cids[0], 'Camp 3 — Nadina FSR km 42', 'Nadina FSR km 42', 'Houston', 54.012, -127.101, 'Starlink', "Radio-assisted road, call ch. RR3.\nGate code with Dale."]);
$s2 = (int)$pdo->lastInsertId();
q("INSERT INTO sites (company_id, name, town, connectivity) VALUES (?,?,?,?)", [$cids[5], 'Main lodge', 'Fort St. James', 'Starlink']);
$s3 = (int)$pdo->lastInsertId();
$eq = [
  [$s1,'UniFi','Ubiquiti','Dream Machine Pro','UDMP-DEMO-0001','74:AC:B9:00:00:01','192.168.1.1',null,'-400 days','+330 days'],
  [$s1,'UniFi','Ubiquiti','U6 Pro AP','U6P-DEMO-0002','74:AC:B9:00:00:02','192.168.1.20',null,'-400 days','+40 days'],
  [$s1,'Phone line','Telus','Business line',null,null,null,'250-555-0101','-900 days',null],
  [$s2,'Starlink kit','Starlink','Standard Actuated','SL-DEMO-0003',null,null,null,'-120 days','+245 days'],
  [$s2,'UniFi','Ubiquiti','UniFi Express','UX-DEMO-0004','74:AC:B9:00:00:04','192.168.10.1',null,'-120 days','+25 days'],
  [$s3,'Starlink kit','Starlink','Flat High Performance','SL-DEMO-0005',null,null,null,'-60 days','+305 days'],
];
foreach ($eq as [$s, $c, $mk, $md, $sn, $mac, $ip, $ph, $ins, $war]) {
  q('INSERT INTO equipment (site_id, category, make, model, serial, mac, ip_address, phone_number, installed_at, warranty_until) VALUES (?,?,?,?,?,?,?,?,?,?)',
    [$s, $c, $mk, $md, $sn, $mac, $ip, $ph, date('Y-m-d', strtotime($ins)), $war ? date('Y-m-d', strtotime($war)) : null]);
}

// Tickets
foreach ([[0,$s2,'Starlink obstructed after snowfall — camp offline','urgent','open',0],
          [3,null,'New hire laptop setup','normal','in_progress',45],
          [5,$s3,'Guest Wi-Fi slow in east cabins','high','waiting',90]] as [$ci, $site, $sub, $pr, $st, $min]) {
  $num = next_number('ticket');
  q('INSERT INTO tickets (number, company_id, site_id, contact_id, subject, priority, status, assigned_to, minutes_spent, created_by) VALUES (?,?,?,?,?,?,?,2,?,1)',
    [$num, $cids[$ci], $site, $ctids[$ci], $sub, $pr, $st, $min]);
  log_activity('system', 'ticket', (int)$pdo->lastInsertId(), "Ticket $num opened: $sub", $cids[$ci]);
}

// Tasks
q('INSERT INTO tasks (title, due_date, assigned_to, company_id, deal_id) VALUES (?,?,1,?,?),(?,?,1,?,?),(?,?,2,?,NULL)', [
  'Call Mark re: phone system needs', date('Y-m-d'), $cids[2], 3,
  'Follow up on yard office quote', date('Y-m-d', strtotime('-1 day')), $cids[1], 2,
  'Book camp 3 dish re-aim trip', date('Y-m-d', strtotime('+2 days')), $cids[0]]);

// One quote with items (demo prices only)
$qn = next_number('quote');
q("INSERT INTO quotes (number, company_id, contact_id, deal_id, title, status, issued_on, valid_until, created_by) VALUES (?,?,?,?,?,'sent',CURDATE(),CURDATE() + INTERVAL 30 DAY,1)",
  [$qn, $cids[1], $ctids[1], 2, 'Yard office network & cameras']);
$qid = (int)$pdo->lastInsertId();
foreach ([['hardware','UniFi Cloud Gateway (demo)',1,'ea',100,1],['hardware','PoE camera (demo)',4,'ea',100,1],['labour','Install & configure',8,'h',100,0],['travel','Travel — 60 km round trip',60,'km',1,0]] as $p => [$k, $d, $qty, $u, $pr, $pst]) {
  q('INSERT INTO quote_items (quote_id, position, kind, description, qty, unit, unit_price, pst_applies) VALUES (?,?,?,?,?,?,?,?)', [$qid, $p, $k, $d, $qty, $u, $pr, $pst]);
}
// Stock + consumables (demo)
q("UPDATE settings SET v = ? WHERE k = 'stock_locations'", ["Shop\nTruck 1\nTruck 2"]);
foreach ([['UniFi','Ubiquiti','U6 Pro AP','U6P-STK-01',189],['UniFi','Ubiquiti','U6 Pro AP','U6P-STK-02',189],['UniFi','Ubiquiti','UniFi Express','UX-STK-01',159],['Starlink kit','Starlink','Standard Kit','SL-STK-01',499],['VoIP handset','Yealink','T54W','YL-STK-01',229]] as [$cat, $mk, $md, $sn, $cost]) {
  q("INSERT INTO equipment (site_id, category, make, model, serial, status, stock_location, supplier, purchased_at, cost) VALUES (NULL,?,?,?,?, 'in_stock', 'Shop', 'Demo Supplier', CURDATE(), ?)", [$cat, $mk, $md, $sn, $cost]);
}
foreach ([['Cat6 outdoor cable (demo)','Cable','ft',1000,250,0.45],['RJ45 shielded connectors (demo)','Connectors','ea',40,50,0.6],['J-mount pole brackets (demo)','Mounting','ea',3,2,24]] as [$n, $cat, $u, $qty, $re, $cost]) {
  q('INSERT INTO consumables (name, category, unit, reorder_level, unit_cost, location) VALUES (?,?,?,?,?,?)', [$n, $cat, $u, $re, $cost, 'Shop']);
  consumable_move((int)$pdo->lastInsertId(), $qty, 'received', null, null, 'Opening stock');
}
// Price list (demo)
foreach ([
  ['UNI-U6PRO', 'UniFi U6 Pro access point', 'hardware', 'ea', 289, 215, 1, 'equipment', 'UniFi', 'Ubiquiti', 'U6 Pro AP', null],
  ['STL-STD', 'Starlink Standard Kit', 'hardware', 'ea', 499, 449, 1, 'equipment', 'Starlink kit', 'Starlink', 'Standard Kit', null],
  ['CAT6-OUT', 'Cat6 outdoor cable', 'hardware', 'ft', 1.10, 0.45, 1, 'consumable', null, null, null, 'Cat6 outdoor cable (demo)'],
  ['LAB-STD', 'Labour — technician', 'labour', 'h', 115, null, 0, 'none', null, null, null, null],
  ['SUP-MON', 'Managed support — monthly', 'other', 'mo', 149, null, 0, 'none', null, null, null, null],
] as [$sku, $n, $k, $u, $p, $c, $pst, $st, $cat, $mk, $md, $cons]) {
  q('INSERT INTO catalog_items (sku, name, kind, unit, price, cost, pst_applies, stock_type, eq_category, eq_make, eq_model, consumable_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
    [$sku, $n, $k, $u, $p, $c, $pst, $st, $cat, $mk, $md, $cons ? val('SELECT id FROM consumables WHERE name = ?', [$cons]) : null]);
}
// Client portal logins (demo): Ridgeback admin + crew lead (own tickets only), Morice owner (full)
foreach ([[0, 'Dale Kowalski', 'dkowalski@ridgeback.example', $ctids[0], [1,1,1,1,1,1,1]],
          [0, 'Tom Crewlead', 'tom@ridgeback.example', null, [1,0,0,0,0,0,0]],
          [1, 'Sandra Reyes', 'sreyes@morice.example', $ctids[1], [1,1,1,1,1,1,1]]] as [$ci, $n, $e, $ct, $pm]) {
  q('INSERT INTO portal_users (company_id, contact_id, name, email, password_hash, perm_tickets, perm_tickets_all, perm_sites, perm_plans, perm_quotes, perm_invoices, is_admin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
    [$cids[$ci], $ct, $n, $e, $pw, ...$pm]);
}
echo "Demo data loaded. Sign in as herb@terralinksystems.com / demo-password-123 (portal: dkowalski@ridgeback.example, tom@ridgeback.example, sreyes@morice.example, same password)\n";
