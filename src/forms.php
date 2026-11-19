<?php
// Declarative forms: one definition per entity drives both the HTML form and the save.

function user_opts(): array {
    $o = ['' => '— Unassigned —'];
    foreach (all('SELECT id, name FROM users WHERE active = 1 ORDER BY name') as $u) $o[$u['id']] = $u['name'];
    return $o;
}
function company_opts(): array {
    $o = ['' => '— Select company —'];
    foreach (all('SELECT id, name FROM companies ORDER BY name') as $c) $o[$c['id']] = $c['name'];
    return $o;
}
function contact_opts(?int $companyId): array {
    $o = ['' => '— None —'];
    $rows = $companyId
        ? all('SELECT id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY is_primary DESC, first_name', [$companyId])
        : all('SELECT c.id, c.first_name, c.last_name FROM contacts c ORDER BY c.first_name LIMIT 500');
    foreach ($rows as $c) $o[$c['id']] = contact_name($c);
    return $o;
}
function site_opts(?int $companyId): array {
    $o = ['' => '— None —'];
    if ($companyId) foreach (all('SELECT id, name FROM sites WHERE company_id = ? ORDER BY name', [$companyId]) as $s) $o[$s['id']] = $s['name'];
    return $o;
}
function deal_opts(?int $companyId): array {
    $o = ['' => '— None —'];
    if ($companyId) foreach (all('SELECT id, title FROM deals WHERE company_id = ? ORDER BY created_at DESC', [$companyId]) as $d) $o[$d['id']] = $d['title'];
    return $o;
}

/** @return array{table:string,label:string,fields:array} */
function entity_def(string $name, array $ctx = []): array {
    $def = entity_def_raw($name, $ctx);
    $stock = $ctx['in_stock'] ?? null;
    if ($stock !== null) $def['fields'] = array_filter($def['fields'], fn($f) => !(($f['stock_only'] ?? false) && !$stock) && !(($f['site_only'] ?? false) && $stock));
    return $def;
}

function entity_def_raw(string $name, array $ctx = []): array {
    $cid = $ctx['company_id'] ?? null;
    return match ($name) {
        'company' => ['table' => 'companies', 'label' => 'Company', 'fields' => [
            'name'     => ['Name', 'text', 'required' => true],
            'industry' => ['Industry', 'select', 'options' => opts(INDUSTRIES)],
            'phone'    => ['Phone', 'tel'],
            'email'    => ['Email', 'email'],
            'website'  => ['Website', 'text'],
            'address'  => ['Street address', 'text'],
            'town'     => ['Town', 'text'],
            'province' => ['Province', 'text', 'default' => 'BC'],
            'owner_id' => ['Account owner', 'select', 'options' => user_opts(), 'int' => true],
            'notes'    => ['Notes', 'textarea'],
        ]],
        'contact' => ['table' => 'contacts', 'label' => 'Contact', 'fields' => [
            'company_id' => ['Company', 'select', 'options' => company_opts(), 'int' => true],
            'first_name' => ['First name', 'text', 'required' => true],
            'last_name'  => ['Last name', 'text'],
            'title'      => ['Job title', 'text'],
            'phone'      => ['Office phone', 'tel'],
            'mobile'     => ['Mobile', 'tel'],
            'email'      => ['Email', 'email'],
            'is_primary' => ['Primary contact for this company', 'checkbox'],
            'notes'      => ['Notes', 'textarea'],
        ]],
        'deal' => ['table' => 'deals', 'label' => 'Deal', 'fields' => [
            'title'          => ['Deal title', 'text', 'required' => true],
            'company_id'     => ['Company', 'select', 'options' => company_opts(), 'int' => true],
            'contact_id'     => ['Contact', 'select', 'options' => contact_opts($cid), 'int' => true],
            'service'        => ['Service', 'select', 'options' => ['' => '— Choose —'] + opts(SERVICES), 'required' => true],
            'stage'          => ['Stage', 'select', 'options' => STAGES],
            'value'          => ['Estimated value ($)', 'money'],
            'source'         => ['Source', 'select', 'options' => ['' => '— Choose —'] + SOURCES, 'required' => true],
            'owner_id'       => ['Owner', 'select', 'options' => user_opts(), 'int' => true],
            'expected_close' => ['Expected close', 'date'],
            'lost_reason'    => ['Lost reason (if lost)', 'text'],
            'message'        => ['Details / original request', 'textarea'],
        ]],
        'site' => ['table' => 'sites', 'label' => 'Site', 'fields' => [
            'company_id'   => ['Company', 'select', 'options' => company_opts(), 'int' => true, 'required' => true],
            'name'         => ['Site name', 'text', 'required' => true, 'placeholder' => 'e.g. Main office, Camp 3, Yard'],
            'address'      => ['Address / directions', 'text'],
            'town'         => ['Town', 'text'],
            'latitude'     => ['Latitude', 'number', 'step' => '0.000001'],
            'longitude'    => ['Longitude', 'number', 'step' => '0.000001'],
            'connectivity' => ['Internet', 'select', 'options' => ['' => '— Unknown —'] + opts(CONNECTIVITY)],
            'access_notes' => ['Access notes (gate codes, road, contacts on site)', 'textarea'],
        ]],
        'equipment' => ['table' => 'equipment', 'label' => 'Equipment', 'fields' => [
            'category'       => ['Category', 'select', 'options' => opts(EQUIP_CATEGORIES)],
            'make'           => ['Make', 'text', 'placeholder' => 'Ubiquiti, Starlink, Yealink…'],
            'model'          => ['Model', 'text'],
            'serial'         => ['Serial #', 'text'],
            'mac'            => ['MAC', 'text'],
            'ip_address'     => ['IP address', 'text'],
            'phone_number'   => ['Phone number (lines)', 'tel'],
            'status'         => ['Status', 'select', 'options' => ($ctx['in_stock'] ?? false) ? STOCK_STATUS : SITE_STATUS],
            'stock_location' => ['Stock location', 'select', 'options' => ['' => '—'] + opts(stock_locations()), 'stock_only' => true],
            'installed_at'   => ['Installed', 'date', 'site_only' => true],
            'warranty_until' => ['Warranty until', 'date'],
            'supplier'       => ['Supplier', 'text'],
            'purchased_at'   => ['Purchased', 'date'],
            'cost'           => ['Cost ($)', 'money'],
            'notes'          => ['Notes', 'textarea'],
        ]],
        'consumable' => ['table' => 'consumables', 'label' => 'Consumable', 'fields' => [
            'name'          => ['Item name', 'text', 'required' => true, 'placeholder' => 'e.g. Cat6 outdoor cable, RJ45 shielded'],
            'category'      => ['Category', 'select', 'options' => opts(CONSUMABLE_CATEGORIES)],
            'sku'           => ['SKU / part #', 'text'],
            'unit'          => ['Unit', 'select', 'options' => CONSUMABLE_UNITS],
            'reorder_level' => ['Re-order when at or below', 'number', 'step' => '0.01'],
            'unit_cost'     => ['Cost per unit ($)', 'money'],
            'location'      => ['Location', 'select', 'options' => ['' => '—'] + opts(stock_locations())],
            'supplier'      => ['Supplier', 'text'],
            'notes'         => ['Notes', 'textarea'],
        ]],
        'ticket' => ['table' => 'tickets', 'label' => 'Ticket', 'fields' => [
            'subject'     => ['Subject', 'text', 'required' => true],
            'company_id'  => ['Company', 'select', 'options' => company_opts(), 'int' => true, 'required' => true],
            'site_id'     => ['Site', 'select', 'options' => site_opts($cid), 'int' => true],
            'contact_id'  => ['Reported by', 'select', 'options' => contact_opts($cid), 'int' => true],
            'priority'    => ['Priority', 'select', 'options' => TICKET_PRIORITY, 'default' => 'normal'],
            'status'      => ['Status', 'select', 'options' => TICKET_STATUS],
            'assigned_to' => ['Assigned to', 'select', 'options' => user_opts(), 'int' => true],
            'description' => ['Description', 'textarea'],
        ]],
        'plan' => ['table' => 'service_plans', 'label' => 'Service plan', 'fields' => [
            'title'        => ['What gets done', 'text', 'required' => true, 'placeholder' => 'e.g. Quarterly network & Starlink check'],
            'company_id'   => ['Company', 'select', 'options' => company_opts(), 'int' => true, 'required' => true],
            'site_id'      => ['Site', 'select', 'options' => site_opts($cid), 'int' => true],
            'every_months' => ['How often', 'select', 'options' => PLAN_EVERY, 'int' => true, 'default' => 3],
            'next_due'     => ['Next visit due', 'date', 'required' => true],
            'lead_days'    => ['Open the ticket this many days before', 'number', 'int' => true, 'default' => 7],
            'assigned_to'  => ['Assigned to', 'select', 'options' => user_opts(), 'int' => true],
            'priority'     => ['Ticket priority', 'select', 'options' => TICKET_PRIORITY, 'default' => 'normal'],
            'fee'          => ['Fee per visit (optional, for your overview)', 'money'],
            'active'       => ['Active — create tickets automatically', 'checkbox', 'default' => 1],
            'description'  => ['Checklist / notes for the tech', 'textarea', 'placeholder' => "e.g.\n- Check Starlink obstruction & firmware\n- Reboot UniFi gateway, update\n- Test backups"],
        ]],
        'quote' => ['table' => 'quotes', 'label' => 'Quote', 'fields' => [
            'title'       => ['Quote title', 'text', 'required' => true, 'placeholder' => 'e.g. Starlink + UniFi install — Camp 3'],
            'company_id'  => ['Company', 'select', 'options' => company_opts(), 'int' => true, 'required' => true],
            'contact_id'  => ['Attention', 'select', 'options' => contact_opts($cid), 'int' => true],
            'deal_id'     => ['Linked deal', 'select', 'options' => deal_opts($cid), 'int' => true],
            'issued_on'   => ['Issued', 'date', 'required' => true],
            'valid_until' => ['Valid until', 'date'],
            'gst_rate'    => ['GST %', 'number', 'step' => '0.001', 'default' => '5'],
            'pst_rate'    => ['PST %', 'number', 'step' => '0.001', 'default' => '7'],
            'notes'       => ['Notes shown on quote', 'textarea'],
        ]],
        default => throw new InvalidArgumentException("Unknown entity $name"),
    };
}

function render_fields(array $def, array $values = [], array $errors = []): string {
    $out = '';
    foreach ($def['fields'] as $name => $f) {
        [$label, $type] = $f;
        $v = $values[$name] ?? ($f['default'] ?? '');
        $req = !empty($f['required']) ? ' required' : '';
        $id = 'f_' . $name;
        $err = isset($errors[$name]) ? '<div class="err">' . h($errors[$name]) . '</div>' : '';
        $ph = isset($f['placeholder']) ? ' placeholder="' . h($f['placeholder']) . '"' : '';
        $wide = in_array($type, ['textarea'], true) ? ' wide' : '';
        if ($type === 'checkbox') {
            $out .= "<div class=\"field check wide\"><label><input type=\"checkbox\" name=\"$name\" value=\"1\"" . ($v ? ' checked' : '') . "> " . h($label) . "</label></div>";
            continue;
        }
        $out .= "<div class=\"field$wide\"><label for=\"$id\">" . h($label) . (!empty($f['required']) ? ' <b>*</b>' : '') . "</label>";
        switch ($type) {
            case 'textarea':
                $out .= "<textarea id=\"$id\" name=\"$name\" rows=\"4\"$req$ph>" . h($v) . "</textarea>"; break;
            case 'select':
                $out .= "<select id=\"$id\" name=\"$name\"$req>";
                foreach ($f['options'] as $ov => $ol) {
                    $sel = ((string)$ov === (string)$v) ? ' selected' : '';
                    $out .= '<option value="' . h($ov) . "\"$sel>" . h($ol) . '</option>';
                }
                $out .= '</select>'; break;
            case 'money':
                $out .= "<input id=\"$id\" type=\"number\" inputmode=\"decimal\" step=\"0.01\" min=\"0\" name=\"$name\" value=\"" . h($v) . "\"$req>"; break;
            case 'number':
                $step = $f['step'] ?? '1';
                $out .= "<input id=\"$id\" type=\"number\" inputmode=\"decimal\" step=\"$step\" name=\"$name\" value=\"" . h($v) . "\"$req>"; break;
            default:
                $out .= "<input id=\"$id\" type=\"$type\" name=\"$name\" value=\"" . h($v) . "\"$req$ph>";
        }
        $out .= "$err</div>";
    }
    return $out;
}

/** Validate + collect POSTed values for an entity. @return array{0:array,1:array} [values, errors] */
function collect_fields(array $def): array {
    $vals = []; $errs = [];
    foreach ($def['fields'] as $name => $f) {
        $type = $f[1];
        $raw = $_POST[$name] ?? null;
        if ($type === 'checkbox') { $vals[$name] = $raw ? 1 : 0; continue; }
        $raw = is_string($raw) ? trim($raw) : $raw;
        if (!empty($f['required']) && ($raw === null || $raw === '')) { $errs[$name] = 'Required'; }
        if ($type === 'select' && $raw !== null && $raw !== '' && !array_key_exists($raw, $f['options'])) { $errs[$name] = 'Invalid choice'; }
        if ($type === 'email' && $raw && !filter_var($raw, FILTER_VALIDATE_EMAIL)) { $errs[$name] = 'Not a valid email'; }
        if ($type === 'date' && $raw && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) { $errs[$name] = 'Use YYYY-MM-DD'; }
        if (in_array($type, ['money', 'number'], true) && $raw !== null && $raw !== '' && !is_numeric($raw)) { $errs[$name] = 'Must be a number'; }
        if (!empty($f['int'])) $vals[$name] = int_or_null($raw);
        elseif ($type === 'money') $vals[$name] = ($raw === null || $raw === '') ? 0 : (float)$raw;
        else $vals[$name] = str_or_null($raw);
        if ($vals[$name] === null && isset($f['default']) && !in_array($type, ['date'], true) && empty($f['required'])) $vals[$name] = $f['default'];
    }
    return [$vals, $errs];
}

function save_entity(array $def, array $vals, ?int $id = null): int {
    $cols = array_keys($vals);
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        q("UPDATE {$def['table']} SET $set WHERE id = ?", [...array_values($vals), $id]);
        return $id;
    }
    $ph = implode(',', array_fill(0, count($cols), '?'));
    q("INSERT INTO {$def['table']} (`" . implode('`,`', $cols) . "`) VALUES ($ph)", array_values($vals));
    return (int)db()->lastInsertId();
}
