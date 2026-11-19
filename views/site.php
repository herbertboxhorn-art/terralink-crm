<?php $id = (int)$s['id']; $addr = trim(implode(', ', array_filter([$s['address'], $s['town']]))); ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/companies/<?= (int)$s['company_id'] ?>#sites">← <?= h($s['company']) ?></a>
    <h1><?= h($s['name']) ?></h1>
    <div class="sub"><?php if ($s['connectivity']): ?><span class="pill ind"><?= h($s['connectivity']) ?></span><?php endif; ?><span><?= h($addr) ?></span></div>
  </div>
  <div class="actions">
    <a class="btn primary" href="/equipment/new?site_id=<?= $id ?>">+ Equipment</a>
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
  <div class="cardhead"><h2>Installed equipment</h2><a class="btn sm" href="/equipment/new?site_id=<?= $id ?>">+ Add</a></div>
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
        <td class="num"><a href="/equipment/<?= (int)$e['id'] ?>/edit">Edit</a> · <a href="/tickets/new?site_id=<?= $id ?>&amp;equipment_id=<?= (int)$e['id'] ?>">Ticket</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>

<div class="grid side">
  <div class="stack"><?php $etype = 'site'; $eid = $id; require __DIR__ . '/_timeline.php'; ?></div>
  <div class="card"><h2>Tickets at this site</h2>
    <?php if (!$tickets): ?><div class="empty">None.</div><?php else: ?><ul class="list"><?php foreach ($tickets as $t): ?>
      <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span><div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><span class="meta"><?= h($t['number']) ?> · <?= h(ago($t['created_at'])) ?></span></div></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
