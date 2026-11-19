<?php
declare(strict_types=1);
/**
 * Photos & files attached to records. Stored OUTSIDE the web root:
 *   UPLOAD_DIR env, else <folder of crm-config.php>/crm-uploads, else app/storage/uploads.
 * Served only through /files/{id} after login.
 */

const FILE_ENTITIES = ['company' => 'companies', 'site' => 'sites', 'ticket' => 'tickets', 'deal' => 'deals', 'quote' => 'quotes', 'equipment' => 'equipment'];
const FILE_MAX_BYTES = 25 * 1024 * 1024;
const FILE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'kmz', 'kml', 'msg', 'eml', 'mp4', 'mov'];
const IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function upload_dir(): string {
    $d = env('UPLOAD_DIR') ?: (defined('CONFIG_DIR') ? CONFIG_DIR . '/crm-uploads' : APP_ROOT . '/storage/uploads');
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return rtrim($d, '/');
}

function file_path(array $f, bool $thumb = false): string {
    return upload_dir() . '/' . ($thumb ? preg_replace('/\.[a-z0-9]+$/', '_t.jpg', $f['path']) : $f['path']);
}

/** Real per-file limit in MB: the lower of our 25 MB and the server's PHP limits */
function upload_limit_mb(): int {
    $b = function (string $v): int { $n = (int)$v; return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n }; };
    $lim = min(FILE_MAX_BYTES, $b((string)ini_get('upload_max_filesize')) ?: FILE_MAX_BYTES, $b((string)ini_get('post_max_size')) ?: FILE_MAX_BYTES);
    return max(1, intdiv($lim, 1048576));
}

function file_size_fmt(int $b): string {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}

/** Files for one record, images first then newest */
function files_for(string $type, int $id): array {
    return all('SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                WHERE f.entity_type = ? AND f.entity_id = ? ORDER BY f.is_image DESC, f.id DESC', [$type, $id]);
}

/** Normalises $_FILES['files'] (multiple) into a flat list */
function uploaded_list(string $field = 'files'): array {
    $u = $_FILES[$field] ?? null;
    if (!$u) return [];
    if (!is_array($u['name'])) return [$u];
    $out = [];
    foreach ($u['name'] as $i => $n) $out[] = ['name' => $n, 'type' => $u['type'][$i], 'tmp_name' => $u['tmp_name'][$i], 'error' => $u['error'][$i], 'size' => $u['size'][$i]];
    return $out;
}

/**
 * Stores one upload for a record. Returns [file id, null] or [null, error message].
 * Photos are turned upright, shrunk to max 2560 px and get a 480 px thumbnail.
 */
function store_upload(array $u, string $type, int $id): array {
    $name = trim(basename(str_replace('\\', '/', (string)$u['name']))) ?: 'file';
    if ($u['error'] === UPLOAD_ERR_INI_SIZE || $u['error'] === UPLOAD_ERR_FORM_SIZE) return [null, "$name: too big (server limit " . ini_get('upload_max_filesize') . ')'];
    if ($u['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($u['tmp_name'])) return [null, "$name: upload failed"];
    if ($u['size'] > FILE_MAX_BYTES) return [null, "$name: bigger than 25 MB"];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, FILE_EXT, true)) return [null, "$name: this file type isn't allowed"];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($u['tmp_name']) ?: 'application/octet-stream';
    $isImage = in_array($mime, IMAGE_MIME, true);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && !$isImage) return [null, "$name: not a valid image"];

    $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $dest = upload_dir() . '/' . $rel;
    if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0750, true)) return [null, "$name: storage folder not writable"];
    $w = $h = null;

    if ($isImage && $mime !== 'image/gif' && extension_loaded('gd')) {
        @ini_set('memory_limit', '512M');
        [$w, $h] = image_store($u['tmp_name'], $mime, $dest);
        if (!$w) $isImage = false;          // GD couldn't read it: keep as a plain file
    }
    if (!is_file($dest) && !move_uploaded_file($u['tmp_name'], $dest)) return [null, "$name: could not save"];
    if ($isImage && !$w) { $sz = @getimagesize($dest); [$w, $h] = $sz ? [$sz[0], $sz[1]] : [null, null]; }
    if ($isImage) image_thumb($dest, preg_replace('/\.[a-z0-9]+$/', '_t.jpg', $dest));

    q('INSERT INTO files (entity_type, entity_id, name, path, mime, size, is_image, width, height, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$type, $id, mb_substr($name, 0, 255), $rel, $mime, (int)filesize($dest), $isImage ? 1 : 0, $w, $h, uid()]);
    return [(int)db()->lastInsertId(), null];
}

function image_load(string $src, string $mime): ?GdImage {
    $im = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        'image/gif'  => @imagecreatefromgif($src),
        default      => false,
    };
    return $im ?: null;
}

/** Upright + max 2560 px. Writes $dest; returns [w, h] or [null, null] */
function image_store(string $src, string $mime, string $dest): array {
    $im = image_load($src, $mime);
    if (!$im) return [null, null];
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($src)['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) $im = $r; }
    }
    $w = imagesx($im); $h = imagesy($im); $max = 2560;
    if (max($w, $h) > $max) {
        $s = $max / max($w, $h);
        $r = imagescale($im, (int)round($w * $s), (int)round($h * $s), IMG_BICUBIC);
        if ($r) { $im = $r; $w = imagesx($im); $h = imagesy($im); }
    }
    $ok = match ($mime) {
        'image/png'  => (imagesavealpha($im, true) && imagepng($im, $dest, 6)),
        'image/webp' => function_exists('imagewebp') && imagewebp($im, $dest, 85),
        default      => imagejpeg($im, $dest, 85),
    };
    return $ok ? [$w, $h] : [null, null];
}

function image_thumb(string $src, string $dest, int $size = 480): void {
    $sz = @getimagesize($src);
    if (!$sz || !extension_loaded('gd')) return;
    $im = image_load($src, $sz['mime']);
    if (!$im) return;
    $w = imagesx($im); $h = imagesy($im); $s = min(1, $size / max($w, $h));
    $t = imagecreatetruecolor(max(1, (int)round($w * $s)), max(1, (int)round($h * $s)));
    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));          // flatten transparency on white
    imagecopyresampled($t, $im, 0, 0, 0, 0, imagesx($t), imagesy($t), $w, $h);
    imagejpeg($t, $dest, 80);
}

function file_delete_row(array $f): void {
    @unlink(file_path($f));
    @unlink(file_path($f, true));
    q('DELETE FROM files WHERE id = ?', [$f['id']]);
}

/** Removes files whose record no longer exists (after deletes / cascades) */
function files_purge_orphans(): void {
    foreach (FILE_ENTITIES as $type => $table) {
        foreach (all("SELECT f.* FROM files f LEFT JOIN `$table` x ON x.id = f.entity_id WHERE f.entity_type = ? AND x.id IS NULL", [$type]) as $f) file_delete_row($f);
    }
}
