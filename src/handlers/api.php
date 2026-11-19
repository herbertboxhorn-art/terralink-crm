<?php
// Machine API for n8n. Auth: header "X-CRM-Key: <CRM_API_KEY>" (set in the server .env).

function api_auth(): void {
    $key = env('CRM_API_KEY');
    $given = $_SERVER['HTTP_X_CRM_KEY'] ?? '';
    if (!$given && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $given = $m[1];
    if (!$key || strlen($key) < 24 || !is_string($given) || !hash_equals($key, $given)) json_out(['error' => 'unauthorized'], 401);
}

function api_dispatch(string $method, string $path): void {
    header('Cache-Control: no-store');
    if ($method === 'GET' && $path === '/api/health') {
        try { val('SELECT 1'); json_out(['ok' => true, 'version' => APP_VERSION]); }
        catch (Throwable) { json_out(['ok' => false], 503); }
    }
    if ($method === 'POST' && $path === '/api/leads') {
        api_auth();
        // Caller is authenticated (our own n8n), so it may see the real reason for a failure.
        try { api_create_lead(); }
        catch (Throwable $e) {
            error_log('lead api: ' . $e);
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
        // Contact first (match by email, then phone)
        $contact = null;
        if ($email !== '') $contact = one('SELECT * FROM contacts WHERE email = ? ORDER BY id LIMIT 1', [$email]);
        if (!$contact && $phone !== '') $contact = one('SELECT * FROM contacts WHERE phone = ? OR mobile = ? ORDER BY id LIMIT 1', [$phone, $phone]);

        // Company: existing contact's company, else by name, else new
        $companyId = $contact['company_id'] ?? null;
        $companyName = $business !== '' ? $business : $name;
        if (!$companyId) $companyId = val('SELECT id FROM companies WHERE LOWER(name) = LOWER(?) LIMIT 1', [$companyName]);
        $newCompany = false;
        if (!$companyId) {
            q('INSERT INTO companies (name, phone, email, industry, notes) VALUES (?,?,?,?,?)',
              [$companyName, $phone ?: null, $email ?: null, 'Other', 'Created from website form']);
            $companyId = (int)$pdo->lastInsertId();
            $newCompany = true;
        }
        if (!$contact) {
            $parts = preg_split('/\s+/', $name !== '' ? $name : 'Website lead', 2);
            q('INSERT INTO contacts (company_id, first_name, last_name, phone, email, is_primary) VALUES (?,?,?,?,?,?)',
              [$companyId, $parts[0], $parts[1] ?? null, $phone ?: null, $email ?: null, $newCompany ? 1 : 0]);
            $contactId = (int)$pdo->lastInsertId();
        } else {
            $contactId = (int)$contact['id'];
        }

        $service = service_from_page($page, $message);
        $title = 'Website callback — ' . $companyName;
        $owner = val("SELECT id FROM users WHERE active = 1 AND role = 'admin' ORDER BY id LIMIT 1");
        $body = $message . ($page ? "\n\nPage: $page" : '');
        q("INSERT INTO deals (company_id, contact_id, title, service, stage, source, owner_id, message) VALUES (?,?,?,?, 'new', 'website', ?, ?)",
          [$companyId, $contactId, $title, $service, $owner, $body ?: null]);
        $dealId = (int)$pdo->lastInsertId();
        q("INSERT INTO activities (entity_type, entity_id, company_id, type, body) VALUES ('deal', ?, ?, 'system', ?)",
          [$dealId, $companyId, 'Lead received from website form' . ($page ? " ($page)" : '')]);
        q('INSERT INTO tasks (title, due_date, assigned_to, company_id, deal_id) VALUES (?, CURDATE(), ?, ?, ?)',
          ['Call back ' . ($name ?: $companyName) . ($phone ? " — $phone" : ''), $owner, $companyId, $dealId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $base = rtrim(env('APP_URL', ''), '/');
    json_out(['ok' => true, 'deal_id' => $dealId, 'company_id' => $companyId, 'contact_id' => $contactId,
              'service' => $service, 'url' => $base . "/deals/$dealId"], 201);
}
