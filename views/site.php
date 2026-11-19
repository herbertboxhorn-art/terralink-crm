<?php $id = (int)$s['id']; $addr = trim(implode(', ', array_filter([$s['address'], $s['town']]))); ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/companies/<?= (int)$s['company_id'] ?>#sites">← <?= h($s['company']) ?></a>
    <h1><?= h($s['name']) ?></h1>
    <div class="sub"><?php if ($s['connectivity']): ?><span class="pill ind"><?= h($s['connectivity']) ?></span><?php endif; ?><span><?= h($addr) ?></span></div>
  </div>
  <div class="actions">
    <a class="btn primary" href="/sites/<?= $id ?>/add-equipment">+ Equipment</a>
    <a class="btn" href="/tickets/new?site_id=<?= $id ?>">+ Ticket</a>
    <a class="btn" href="/sites/<?= $id ?>/edit">Edit</a>
  </div>
</div>
<?php if ($addr || $s['latitude']): ?>
<div class="quick" style="margin-bottom:18px">
  <a href="https://maps.google.com/?q=<?= rawurlencode($s['latitude'] && $s['longitude'] ? $s['latitude'] . ',' . $s['longitude'] : $addr) ?>" target="_blank" rel="noopener"><svg class="i"><use href="/assets/icons.svg#pin"/></svg>Directions<?= $s['latitude'] ? ' (GPS)' : '' ?></a>
</div>
<?php endif; ?>

<?php if ($s['access_notes']): ?><div class="notice"><b>Access:</b> <span style="white-space:pre-wrap"><?= h($s['access_notes']) ?></span></div><?php endif; ?>

<div class="card" id="equipment" style="margin-bottom:18px">
  <div class="cardhead"><h2>Installed equipment</h2><a class="btn sm" href="/sites/<?= $id ?>/add-equipment">+ Add (from stock or new)</a></div>
  <?php if (!$equipment): ?><div class="empty">No equipment recorded yet.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Device</th><th>Serial / MAC</th><th>IP / Number</th><th>Installed</th><th>Warranty</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($equipment as $e): $wexp = $e['warranty_until'] && $e['warranty_until'] < date('Y-m-d'); ?>
      <tr<?= $e['status'] === 'retired' ? ' style="opacity:.55"' : '' ?>>
        <td><b><?= h($e['category']) ?></b><br><span class="muted"><?= h(trim($e['make'] . ' ' . $e['model'])) ?></span><?php if ($e['notes']): ?><br><small><?= h($e['notes']) ?></small><?php endif; ?></td>
        <td class="mono" data-l="S/N"><?= h($e['serial']) ?><?php if ($e['mac']): ?><br><?= h($e['mac']) ?><?php endif; ?></td>
        <td class="mono"><?= h($e['ip_address']) ?><?= $e['phone_number'] ? ($e['ip_address'] ? '<br>' : '') . tel($e['phone_number']) : '' ?></td>
        <td data-l="Installed"><?= h(fdate($e['installed_at'], 'M j, Y')) ?></td>
        <td data-l="Warranty"<?= $wexp ? ' style="color:var(--bad)"' : '' ?>><?= h(fdate($e['warranty_until'], 'M j, Y')) ?></td>
        <td><span class="pill <?= h($e['status']) ?>"><?= h(EQUIP_STATUS[$e['status']]) ?></span></td>
        <td class="num"><a href="/equipment/<?= (int)$e['id'] ?>/edit">Edit</a> · <a href="/tickets/new?site_id=<?= $id ?>&amp;equipment_id=<?= (int)$e['id'] ?>">Ticket</a>
          <form method="post" action="/equipment/<?= (int)$e['id'] ?>/return" style="display:inline" data-confirm="Remove this device from the site and put it back in stock (<?= h(stock_locations()[0]) ?>)?"><?= csrf_field() ?>
            · <button class="linkbtn" type="submit" style="color:var(--info)">To stock</button></form></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>

<div class="card" id="consumables" style="margin-bottom:18px">
  <div class="cardhead"><h2>Consumables used here</h2></div>
  <?php if ($consumables): ?>
  <form method="post" action="/consumables/move" class="inline" style="margin-bottom:14px"><?= csrf_field() ?>
    <input type="hidden" name="reason" value="used"><input type="hidden" name="site_id" value="<?= $id ?>"><input type="hidden" name="back" value="/sites/<?= $id ?>#consumables">
    <div class="row">
      <select name="consumable_id" aria-label="Item" required><?php foreach ($consumables as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?> (<?= h(qty_fmt($c['qty_on_hand'])) ?> <?= h($c['unit']) ?> left)</option><?php endforeach; ?></select>
      <input type="number" step="0.01" min="0.01" inputmode="decimal" name="qty" placeholder="Qty" required style="max-width:110px">
      <button class="btn dark" type="submit">Log use</button>
    </div>
    <input name="note" placeholder="Note (optional), e.g. new run to shop">
  </form>
  <?php else: ?><p class="muted" style="margin:0 0 10px">Add consumables under <a href="/equipment?tab=consumables">Inventory → Consumables</a> to log what gets used here.</p><?php endif; ?>
  <?php if ($used): ?><ul class="list"><?php foreach ($used as $u): ?>
    <li><div class="grow"><b><?= h(qty_fmt(abs((float)$u['qty_change']))) ?> <?= h($u['unit']) ?></b> <?= h($u['item']) ?><span class="meta"><?= h(fdate($u['created_at'], 'M j, Y')) ?> · <?= h($u['who'] ?? '—') ?><?= $u['note'] ? ' · ' . h($u['note']) : '' ?></span></div></li>
  <?php endforeach; ?></ul><?php endif; ?>
</div>

<div class="grid side">
  <div class="stack"><?php $pl = plans_for('site_id', $id); $newUrl = "/plans/new?site_id=$id"; require __DIR__ . '/_plans.php'; ?><?php $ftype = 'site'; $fid = $id; require __DIR__ . '/_files.php'; ?><?php $etype = 'site'; $eid = $id; require __DIR__ . '/_timeline.php'; ?></div>
  <div class="card"><h2>Tickets at this site</h2>
    <?php if (!$tickets): ?><div class="empty">None.</div><?php else: ?><ul class="list"><?php foreach ($tickets as $t): ?>
      <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span><div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><span class="meta"><?= h($t['number']) ?> · <?= h(ago($t['created_at'])) ?></span></div></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
