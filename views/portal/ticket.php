<?php $id = (int)$t['id']; $closed = $t['status'] === 'closed';
$imgs = array_values(array_filter($files, fn($f) => $f['is_image'])); $docs = array_values(array_filter($files, fn($f) => !$f['is_image'])); ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/tickets">← Tickets</a>
    <h1><?= h($t['subject']) ?></h1>
    <div class="sub"><span class="mono"><?= h($t['number']) ?></span>
      <span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span>
      <?php if ($t['priority'] !== 'normal'): ?><span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span><?php endif; ?>
      <?php if ($t['site']): ?><span><?= h($t['site']) ?></span><?php endif; ?></div>
  </div>
  <?php if ($closed): ?><div class="actions"><a class="btn" href="/tickets/<?= $id ?>/summary" target="_blank">Work summary PDF</a></div><?php endif; ?>
</div>

<?php if ($t['status'] === 'waiting'): ?><div class="notice">We're waiting on you for this one. Reply below and we'll pick it right back up.</div><?php endif; ?>

<div class="grid side">
  <div class="stack">
    <div class="card">
      <h2>Conversation</h2>
      <ul class="thread" style="margin-top:12px">
        <li class="msg<?= $t['portal_user_id'] && (int)$t['portal_user_id'] === pu_id() ? ' mine' : '' ?>">
          <div class="who"><b><?= h($t['opener'] ?? (trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: setting('company_name'))) ?></b> · <?= h(fdate($t['created_at'], 'M j, g:i a')) ?></div>
          <div class="txt"><?= $t['description'] ? h($t['description']) : '<span class="muted">' . h($t['subject']) . '</span>' ?></div>
        </li>
        <?php foreach ($thread as $m): if ($m['kind'] === 'status'): ?>
        <li class="st"><?= h($m['body']) ?> · <?= h(fdate($m['at'], 'M j, g:i a')) ?></li>
        <?php else: ?>
        <li class="msg<?= $m['mine'] ? ' mine' : '' ?>">
          <div class="who"><?= !$m['mine'] && $m['uid'] ? avatar($m['who'], $m['uid']) : '' ?><b><?= h($m['who']) ?></b><?= !$m['mine'] ? ' <span class="muted">· ' . h(setting('company_name', 'TerraLink')) . '</span>' : '' ?> · <?= h(fdate($m['at'], 'M j, g:i a')) ?></div>
          <div class="txt"><?= h($m['body']) ?></div>
        </li>
        <?php endif; endforeach; ?>
      </ul>
    </div>

    <div class="card" id="reply">
      <?php if ($closed): ?>
        <h2>This ticket is closed</h2>
        <p class="muted">Still having trouble, or something new? Open a new ticket and mention <?= h($t['number']) ?>.</p>
        <a class="btn primary" href="/tickets/new?subject=<?= rawurlencode('Follow-up to ' . $t['number'] . ': ' . $t['subject']) ?><?= $t['site_id'] ? '&site=' . (int)$t['site_id'] : '' ?>">Open a follow-up ticket</a>
      <?php else: ?>
        <h2>Reply</h2>
        <form method="post" action="/tickets/<?= $id ?>/reply" enctype="multipart/form-data" class="inline"  style="margin-top:10px"><?= csrf_field() ?>
          <textarea name="message" rows="3" placeholder="Add details, answer a question, or let us know it's working again…"></textarea>
          <div class="row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <label class="btn">📷 Add photos<input type="file" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.heic,.mp4,.mov" class="vh" data-count></label>
            <span class="muted upmsg" >max <?= upload_limit_mb() ?> MB each</span>
          </div>
          <button class="btn primary" type="submit" >Send reply</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($files): ?>
    <div class="card" id="files">
      <h2>Photos &amp; files <span class="muted" style="font-weight:500"><?= count($files) ?></span></h2>
      <?php if ($imgs): ?><div class="thumbs"><?php foreach ($imgs as $f): ?>
        <figure><a href="/files/<?= (int)$f['id'] ?>" target="_blank" rel="noopener" title="<?= h($f['name']) ?>"><img src="/files/<?= (int)$f['id'] ?>/thumb" alt="<?= h($f['name']) ?>" loading="lazy"></a>
          <figcaption><?= h(strtok((string)($f['uploader'] ?? ''), ' ')) ?> · <?= h(fdate($f['created_at'], 'M j')) ?></figcaption></figure>
      <?php endforeach; ?></div><?php endif; ?>
      <?php if ($docs): ?><ul class="list files"><?php foreach ($docs as $f): $ext = strtoupper(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'FILE'); ?>
        <li><span class="ext"><?= h(substr($ext, 0, 4)) ?></span><div class="grow"><a href="/files/<?= (int)$f['id'] ?>" target="_blank" rel="noopener"><?= h($f['name']) ?></a>
          <span class="meta"><?= h(file_size_fmt((int)$f['size'])) ?> · <?= h($f['uploader'] ?? '') ?> · <?= h(fdate($f['created_at'], 'M j, Y')) ?></span></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card"><h2>Details</h2>
      <dl class="kv">
        <dt>Opened</dt><dd><?= h(fdate($t['created_at'], 'M j, Y g:i a')) ?><?= $t['opener'] ? '<br><span class="muted">by ' . h($t['opener']) . '</span>' : '' ?></dd>
        <?php if ($t['first_name']): ?><dt>Contact</dt><dd><?= h(trim($t['first_name'] . ' ' . $t['last_name'])) ?></dd><?php endif; ?>
        <?php if ($t['site']): ?><dt>Site</dt><dd><?= h($t['site']) ?></dd><?php endif; ?>
        <?php if ($t['equipment_id']): ?><dt>Device</dt><dd><?= h(trim($t['e_cat'] . ' ' . $t['e_make'] . ' ' . $t['e_model'])) ?><?= $t['e_serial'] ? '<br><span class="mono">S/N ' . h($t['e_serial']) . '</span>' : '' ?></dd><?php endif; ?>
        <?php if ($t['plan_title']): ?><dt>Service plan</dt><dd><?= h($t['plan_title']) ?></dd><?php endif; ?>
        <dt>Technician</dt><dd><?= $t['assignee'] ? h(explode(' ', $t['assignee'])[0]) : 'Being assigned' ?></dd>
        <?php if ($t['closed_at']): ?><dt>Closed</dt><dd><?= h(fdate($t['closed_at'], 'M j, Y')) ?></dd><?php endif; ?>
      </dl>
    </div>
    <?php if ($charges): ?>
    <div class="card"><h2>Charges</h2>
      <ul class="list" style="margin-top:6px"><?php foreach ($charges as $c): ?>
        <li><div class="grow"><?= h($c['description']) ?><span class="meta"><?= h(qty_fmt($c['qty'])) ?> <?= h($c['unit']) ?> × <?= h(money($c['unit_price'])) ?></span></div><b><?= h(money(round((float)$c['qty'] * (float)$c['unit_price'], 2))) ?></b></li>
      <?php endforeach; ?></ul>
      <p style="margin:10px 0 0;text-align:right">Total incl. tax: <b><?= h(money($ctot['total'])) ?></b></p>
      <p class="muted" style="font-size:13px;margin:6px 0 0"><?= $t['invoice_id'] ? 'Billed on your monthly invoice.' : 'Goes on your next monthly invoice.' ?></p>
    </div>
    <?php elseif ($t['billing'] === 'no_charge' && $t['plan_title']): ?>
    <div class="card"><h2>Charges</h2><p class="muted" style="margin:6px 0 0">Covered by your service plan.</p></div>
    <?php endif; ?>
  </div>
</div>
