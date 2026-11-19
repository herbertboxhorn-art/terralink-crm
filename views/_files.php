<?php
/** Photos & files card. Needs $ftype, $fid; optional $fback (return URL) */
$fl = files_for($ftype, (int)$fid);
$imgs = array_values(array_filter($fl, fn($f) => $f['is_image']));
$docs = array_values(array_filter($fl, fn($f) => !$f['is_image']));
$fback = $fback ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
$canDel = fn($f) => ((int)$f['uploaded_by'] === uid() && !$f['portal_user_id']) || is_role('admin');
$portalTicket = $ftype === 'ticket' && (int)val('SELECT COUNT(*) FROM portal_users pu JOIN tickets t ON t.company_id = pu.company_id WHERE t.id = ? AND pu.active = 1 AND pu.perm_tickets = 1', [(int)$fid]) > 0;
$shareBtn = function (array $f) use ($ftype): string {
    if ($ftype !== 'ticket') return '';
    if ($f['portal_user_id']) return '<span class="pubtag">From client</span>';
    return '<form method="post" action="/files/' . (int)$f['id'] . '/share" style="display:inline">' . csrf_field()
        . '<button type="submit" class="linkbtn" title="' . ($f['is_public'] ? 'Visible in the client portal. Click to hide.' : 'Hidden from the client. Click to show it in the portal.') . '">'
        . ($f['is_public'] ? '<span class="pubtag">Client sees this</span>' : 'share with client') . '</button></form>';
};
?>
<div class="card" id="files">
  <div class="cardhead"><h2>Photos &amp; files<?= $fl ? ' <span class="muted" style="font-weight:500">' . count($fl) . '</span>' : '' ?></h2></div>
  <?php if ($imgs): ?>
  <div class="thumbs">
    <?php foreach ($imgs as $f): ?>
    <figure>
      <a href="/files/<?= (int)$f['id'] ?>" target="_blank" rel="noopener" title="<?= h($f['name']) ?>"><img src="/files/<?= (int)$f['id'] ?>/thumb" alt="<?= h($f['name']) ?>" loading="lazy"></a>
      <?php if ($canDel($f)): ?>
      <form method="post" action="/files/<?= (int)$f['id'] ?>/delete" data-confirm="Delete this photo?"><?= csrf_field() ?><button type="submit" class="x" aria-label="Delete photo">×</button></form>
      <?php endif; ?>
      <figcaption><?= h(strtok((string)($f['uploader'] ?? ''), ' ')) ?> · <?= h(fdate($f['created_at'], 'M j')) ?><?php if ($portalTicket || $f['portal_user_id']): ?><br><?= $shareBtn($f) ?><?php endif; ?></figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($docs): ?>
  <ul class="list files">
    <?php foreach ($docs as $f): $ext = strtoupper(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'FILE'); ?>
    <li><span class="ext"><?= h(substr($ext, 0, 4)) ?></span>
      <div class="grow"><a href="/files/<?= (int)$f['id'] ?>" target="_blank" rel="noopener"><?= h($f['name']) ?></a>
        <span class="meta"><?= h(file_size_fmt((int)$f['size'])) ?> · <?= h($f['uploader'] ?? '') ?> · <?= h(fdate($f['created_at'], 'M j, Y')) ?><?php if ($portalTicket || $f['portal_user_id']): ?> · <?= $shareBtn($f) ?><?php endif; ?></span></div>
      <?php if ($canDel($f)): ?>
      <form method="post" action="/files/<?= (int)$f['id'] ?>/delete" data-confirm="Delete <?= h($f['name']) ?>?"><?= csrf_field() ?><button type="submit" class="btn sm ghost" aria-label="Delete file">Delete</button></form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (!$fl): ?><div class="empty" style="padding:6px 0 12px">No photos or files yet.</div><?php endif; ?>
  <form method="post" action="/files" enctype="multipart/form-data" class="upload" data-upload>
    <?= csrf_field() ?>
    <input type="hidden" name="entity_type" value="<?= h($ftype) ?>">
    <input type="hidden" name="entity_id" value="<?= (int)$fid ?>">
    <input type="hidden" name="back" value="<?= h($fback) ?>">
    <?php if ($portalTicket): ?><label class="muted" style="display:flex;gap:6px;align-items:center;font-size:13.5px"><input type="checkbox" name="public" value="1" style="width:auto"> Client can see (tick before choosing files)</label><?php endif; ?>
    <label class="btn">📷 Add photos or files
      <input type="file" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.kmz,.kml,.msg,.eml,.mp4,.mov,.heic" class="vh">
    </label>
    <button class="btn primary" type="submit" data-upload-btn>Upload</button>
    <span class="muted upmsg" data-upload-msg>Photos, PDFs, Office files · max <?= upload_limit_mb() ?> MB each</span>
  </form>
</div>
