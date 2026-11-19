<?php
// Staff side of the client portal: logins per company (Portal tab on a company page).

function portal_admin_guard(): void { require_role('admin', 'sales'); }

function h_portal_user_add(int $companyId): void {
    portal_admin_guard();
    if (!one('SELECT id FROM companies WHERE id = ?', [$companyId])) not_found();
    $in = $_POST;
    if ($p = PORTAL_PRESETS[(string)post('preset', '')] ?? null) foreach (PORTAL_ALL_PERMS as $k) $in[$k] = in_array($k, $p['perms'], true) ? 1 : 0;
    $in['active'] = 1;
    [$id, $errors] = portal_save_user($companyId, $in, null, null, (bool)post('invite'), user()['name']);
    if ($errors) {
        flash('Portal login not added: ' . implode('; ', array_map(fn($k, $v) => "$k — $v", array_keys($errors), $errors)), 'bad');
    } else {
        $pu = one('SELECT * FROM portal_users WHERE id = ?', [$id]);
        flash(post('invite') ? "Portal login added and invite emailed to {$pu['email']}." : "Portal login added for {$pu['email']}. Send the invite when you're ready.");
    }
    redirect("/companies/$companyId#portal");
}

function h_portal_user_edit(int $id): void {
    portal_admin_guard();
    $pu = one('SELECT pu.*, c.name company FROM portal_users pu JOIN companies c ON c.id = pu.company_id WHERE pu.id = ?', [$id]);
    if (!$pu) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [, $errors] = portal_save_user((int)$pu['company_id'], $_POST, $pu, null, false, user()['name']);
        if (!$errors) {
            $new = one('SELECT * FROM portal_users WHERE id = ?', [$id]);
            log_activity('system', 'company', (int)$pu['company_id'], "Portal access for {$new['name']}: " . ($new['active'] ? portal_perm_summary($new) : 'disabled'), (int)$pu['company_id']);
            flash('Portal login saved.');
            redirect("/companies/{$pu['company_id']}#portal");
        }
        $pu = array_merge($pu, array_intersect_key($_POST, array_flip(['name', 'email', 'contact_id'])));
        foreach (PORTAL_ALL_PERMS as $p) $pu[$p] = !empty($_POST[$p]) ? 1 : 0;
        $pu['active'] = !empty($_POST['active']) ? 1 : 0;
    }
    view('portal_user_form', ['title' => 'Portal login — ' . $pu['name'], 'pu' => $pu, 'errors' => $errors,
        'contacts' => contact_opts((int)$pu['company_id'])]);
}

function h_portal_user_invite(int $id): void {
    portal_admin_guard();
    $pu = one('SELECT * FROM portal_users WHERE id = ? AND active = 1', [$id]);
    if (!$pu) not_found();
    flash(portal_send_invite($pu, user()['name']) ? "Invite emailed to {$pu['email']}." : 'The invite could not be sent. Check Settings → Recent emails.', 'warn');
    redirect("/companies/{$pu['company_id']}#portal");
}

function h_portal_user_delete(int $id): void {
    portal_admin_guard();
    $pu = one('SELECT * FROM portal_users WHERE id = ?', [$id]);
    if (!$pu) not_found();
    q('DELETE FROM portal_users WHERE id = ?', [$id]);
    log_activity('system', 'company', (int)$pu['company_id'], "Portal login removed: {$pu['name']} ({$pu['email']})", (int)$pu['company_id']);
    flash("Portal login for {$pu['name']} removed. Their tickets and messages stay on record.");
    redirect("/companies/{$pu['company_id']}#portal");
}
