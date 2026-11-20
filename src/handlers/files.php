<?php
declare(strict_types=1);

/** Company id behind a record (for the activity log), null if the record is missing */
function file_entity_company(string $type, int $id): ?array {
    $table = FILE_ENTITIES[$type] ?? null;
    if (!$table) return null;
    $col = $type === 'company' ? 'id' : 'company_id';
    if ($type === 'equipment') {
        $r = one('SELECT e.id, s.company_id FROM equipment e LEFT JOIN sites s ON s.id = e.site_id WHERE e.id = ?', [$id]);
    } else {
        $r = one("SELECT id, `$col` AS company_id FROM `$table` WHERE id = ?", [$id]);
    }
    return $r ?: null;
}

function h_file_upload(): void {
    $type = (string)post('entity_type'); $id = (int)post('entity_id');
    $back = (string)post('back', '/');
    if (!str_starts_with($back, '/') || str_starts_with($back, '//')) $back = '/';
    $rec = file_entity_company($type, $id);
    if (!$rec) not_found();
    $ok = 0; $errs = [];
    foreach (uploaded_list() as $u) {
        if ($u['error'] === UPLOAD_ERR_NO_FILE) continue;
        [$fid, $err] = store_upload($u, $type, $id);
        $fid ? $ok++ : $errs[] = $err;
        if ($fid && $type === 'ticket' && post('public')) q('UPDATE files SET is_public = 1 WHERE id = ?', [$fid]);
    }
    if ($ok && $type !== 'equipment') log_activity('system', $type, $id, "Added $ok file" . ($ok === 1 ? '' : 's'), $rec['company_id'] ? (int)$rec['company_id'] : null);
    if ($errs) flash(($ok ? "$ok uploaded. " : '') . 'Not uploaded: ' . implode('; ', $errs), 'bad');
    elseif ($ok) flash("$ok file" . ($ok === 1 ? '' : 's') . ' uploaded.');
    else flash('Choose at least one file.', 'bad');
    redirect($back . '#files');
}

function h_file_get(int $id, bool $thumb = false): void {
    $f = one('SELECT * FROM files WHERE id = ?', [$id]);
    if (!$f) not_found();
    if ($f['entity_type'] === 'quote' && is_role('tech')) forbidden();   // v1.17: techs don't see quotes, so not their files either
    file_send($f, $thumb);
}

/** Streams a stored file (or its thumbnail) to the browser */
function file_send(array $f, bool $thumb = false): never {
    $path = file_path($f, $thumb && $f['is_image']);
    if (!is_file($path)) $path = file_path($f);
    if (!is_file($path)) { http_response_code(404); exit('File missing'); }
    $inline = $f['is_image'] || $f['mime'] === 'application/pdf';
    $mime = ($thumb && $f['is_image']) ? 'image/jpeg' : ($inline ? $f['mime'] : 'application/octet-stream');
    $fn = str_replace(['"', "\r", "\n"], '', $f['name']);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^\x20-\x7e]/', '_', $fn) . '"; filename*=UTF-8\'\'' . rawurlencode($fn));
    header('Cache-Control: private, max-age=86400');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    readfile($path);
    exit;
}

function h_file_thumb(int $id): void { h_file_get($id, true); }

/** Ticket files only: show or hide a staff file in the client portal */
function h_file_share(int $id): void {
    $f = one("SELECT * FROM files WHERE id = ? AND entity_type = 'ticket'", [$id]);
    if (!$f) not_found();
    if ($f['portal_user_id']) { flash('Files the client uploaded are always visible to them.', 'warn'); redirect(back('/') . '#files'); }
    $pub = $f['is_public'] ? 0 : 1;
    q('UPDATE files SET is_public = ? WHERE id = ?', [$pub, $id]);
    flash($pub ? $f['name'] . ' is now visible in the client portal.' : $f['name'] . ' is hidden from the client portal.');
    redirect(back('/') . '#files');
}

function h_file_delete(int $id): void {
    $f = one('SELECT * FROM files WHERE id = ?', [$id]);
    if (!$f) not_found();
    if ((int)$f['uploaded_by'] !== uid() && !is_role('admin')) forbidden();
    file_delete_row($f);
    flash('Deleted ' . $f['name'] . '.');
    redirect(back('/') . '#files');
}
