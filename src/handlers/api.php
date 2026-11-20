<?php
// Machine API for n8n. Auth: header "X-CRM-Key: <CRM_API_KEY>" (set in the server .env).

/**
 * Keys per job (v1.17). Each falls back to CRM_API_KEY, so a site without the new keys keeps working.
 * Set LEADS_API_KEY / CRON_API_KEY / MAIL_WEBHOOK_KEY in crm-config.php to separate them.
 */
function api_key(string $purpose): ?string {
    $name = ['leads' => 'LEADS_API_KEY', 'cron' => 'CRON_API_KEY', 'mail' => 'MAIL_WEBHOOK_KEY'][$purpose] ?? 'CRM_API_KEY';
    return env($name) ?: env('CRM_API_KEY');
}

function api_auth(string $purpose): void {
    $key = api_key($purpose);
    $given = $_SERVER['HTTP_X_CRM_KEY'] ?? '';
    if (!$given && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $given = $m[1];
    if (!$key || strlen($key) < 24 || !is_string($given) || !hash_equals($key, $given)) json_out(['error' => 'unauthorized'], 401);
}

function api_dispatch(string $method, string $path): void {
    header('Cache-Control: no-store');
    if ($method === 'GET' && $path === '/api/health') {
        try { val('SELECT 1'); json_out(['ok' => true, 'version' => APP_VERSION, 'storage' => is_writable(upload_dir())]); }
        catch (Throwable) { json_out(['ok' => false], 503); }
    }
    if ($method === 'POST' && $path === '/api/cron/daily') {
        api_auth('cron');
        @set_time_limit(120);
        try { json_out(cron_daily(($_GET['force'] ?? '') === '1')); }
        catch (Throwable $e) { app_error_record('cron: ' . get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()); json_out(['ok' => false, 'detail' => get_class($e) . ': ' . $e->getMessage()], 500); }
    }
    if ($method === 'POST' && $path === '/api/leads') {
        api_auth('leads');
        // Caller is authenticated (our own n8n), so it may see the real reason for a failure.
        try { api_create_lead(); }
        catch (Throwable $e) {
            app_error_record('lead api: ' . get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            json_out(['error' => 'server error', 'detail' => get_class($e) . ': ' . $e->getMessage()], 500);
        }
    }
    json_out(['error' => 'not found'], 404);
}

/** Guess the service from the website page the form was sent from */
function service_from_page(string $page, string $text): string {
    $s = strtolower($page . ' ' . $text);
    return match (true) {
        str_contains($s, 'starlink') || str_contains($s, 'remote-site') || str_contains($s, 'remote site') => 'Starlink & Remote Sites',
        str_contains($s, 'phone') || str_contains($s, 'voip') => 'Business Phones',
        str_contains($s, 'automation') || str_contains($s, ' ai') || str_contains($s, '/ai') => 'AI Automation',
        str_contains($s, 'network') || str_contains($s, 'hardware') || str_contains($s, 'unifi') || str_contains($s, 'wifi') => 'Hardware & Networks',
        str_contains($s, 'business-it') || str_contains($s, 'it-support') || str_contains($s, 'managed') => 'Business IT',
        default => 'Other',
    };
}

function api_create_lead(): void {
    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $clip = fn($k, $n = 190) => mb_substr(trim((string)($in[$k] ?? '')), 0, $n);

    $name = $clip('name', 160);
    $business = $clip('business');
    $phone = $clip('phone', 40);
    $email = strtolower($clip('email'));
    $message = $clip('message', 5000);
    $page = $clip('page', 255);
    if ($name === '' && $business === '') json_out(['error' => 'name or business required'], 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    if ($phone === '' && $email === '') json_out(['error' => 'phone or email required'], 422);

    // Duplicate guard: same email/phone within 10 minutes → return the existing lead
    $match = []; $p = [];
    if ($email !== '') { $match[] = 'ct.email = ?'; $p[] = $email; }
    if ($phone !== '') { $match[] = 'ct.phone = ?'; $p[] = $phone; }
    $dupe = val("SELECT d.id FROM deals d JOIN contacts ct ON ct.id = d.contact_id
                 WHERE d.source = 'website' AND d.created_at > NOW() - INTERVAL 10 MINUTE
                   AND (" . implode(' OR ', $match) . ") ORDER BY d.id DESC LIMIT 1", $p);
    if ($dupe) json_out(['ok' => true, 'deal_id' => (int)$dupe, 'duplicate' => true]);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // v1.17: a public web form must never write into an existing client's records. Always create a new
        // company + contact, and note a possible match so staff can merge it by hand.
        $match = null;
        if ($email !== '') $match = one('SELECT ct.id, ct.company_id, c.name FROM contacts ct JOIN companies c ON c.id = ct.company_id WHERE ct.email = ? ORDER BY ct.id LIMIT 1', [$email]);
        if (!$match && $phone !== '') $match = one('SELECT ct.id, ct.company_id, c.name FROM contacts ct JOIN companies c ON c.id = ct.company_id WHERE ct.phone = ? OR ct.mobile = ? ORDER BY ct.id LIMIT 1', [$phone, $phone]);
        $companyName = $business !== '' ? $business : $name;
        if (!$match) $match = one('SELECT NULL id, id company_id, name FROM companies WHERE LOWER(name) = LOWER(?) LIMIT 1', [$companyName]);
        q('INSERT INTO companies (name, phone, email, industry, notes) VALUES (?,?,?,?,?)',
          [$companyName, $phone ?: null, $email ?: null, 'Other', 'Created from website form' . ($match ? " — possible duplicate of {$match['name']} (#{$match['company_id']})" : '')]);
        $companyId = (int)$pdo->lastInsertId();
        $parts = preg_split('/\s+/', $name !== '' ? $name : 'Website lead', 2);
        q('INSERT INTO contacts (company_id, first_name, last_name, phone, email, is_primary) VALUES (?,?,?,?,?,1)',
          [$companyId, $parts[0], $parts[1] ?? null, $phone ?: null, $email ?: null]);
        $contactId = (int)$pdo->lastInsertId();

        $service = service_from_page($page, $message);
        $title = 'Website callback — ' . $companyName;
        $owner = val("SELECT id FROM users WHERE active = 1 AND role = 'admin' ORDER BY id LIMIT 1");
        $body = $message . ($page ? "\n\nPage: $page" : '');
        q("INSERT INTO deals (company_id, contact_id, title, service, stage, source, owner_id, message) VALUES (?,?,?,?, 'new', 'website', ?, ?)",
          [$companyId, $contactId, $title, $service, $owner, $body ?: null]);
        $dealId = (int)$pdo->lastInsertId();
        q("INSERT INTO activities (entity_type, entity_id, company_id, type, body) VALUES ('deal', ?, ?, 'system', ?)",
          [$dealId, $companyId, 'Lead received from website form' . ($page ? " ($page)" : '')
            . ($match ? "\nPossible existing client: {$match['name']} (/companies/{$match['company_id']}). Check before merging — web forms aren't verified." : '')]);
        q('INSERT INTO tasks (title, due_date, assigned_to, company_id, deal_id) VALUES (?, CURDATE(), ?, ?, ?)',
          ['Call back ' . ($name ?: $companyName) . ($phone ? " — $phone" : ''), $owner, $companyId, $dealId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $base = rtrim(env('APP_URL', ''), '/');
    json_out(['ok' => true, 'deal_id' => $dealId, 'company_id' => $companyId, 'contact_id' => $contactId, 'possible_match' => $match ? (int)$match['company_id'] : null,
              'service' => $service, 'url' => $base . "/deals/$dealId"], 201);
}
