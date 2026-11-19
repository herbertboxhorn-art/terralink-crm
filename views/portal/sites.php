<?php $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+60 days')); $canTicket = portal_can('perm_tickets'); ?>
<div class="pagehead"><div><h1>Sites &amp; equipment</h1><div class="sub">What we've installed for <?= h(portal_user()['company']) ?>, with serial numbers and warranties.</div></div></div>
<?php if (!$sites): ?><div class="card"><div class="empty">No sites on record yet.</div></div><?php endif; ?>
<div class="stack">
<?php foreach ($sites as $s): $eq = $by[(int)$s['id']] ?? []; $addr = trim(implode(', ', array_filter([$s['address'], $s['town']]))); ?>
  <div class="card">
    <div class="cardhead"><h2><?= h($s['name']) ?></h2><span class="muted"><?= h(implode(' · ', array_filter([$addr, $s['connectivity'] ? 'Internet: ' . $s['connectivity'] : null]))) ?></span></div>
    <?php if (!$eq): ?><div class="empty">No equipment recorded at this site.</div><?php else: ?>
    <div class="tablewrap"><table class="t cards">
      <thead><tr><th>Device</th><th>Serial</th><th>Installed</th><th>Warranty</th><th>Status</th><?php if ($canTicket): ?><th></th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($eq as $e): $w = $e['warranty_until']; ?>
        <tr>
          <td><b><?= h(trim($e['make'] . ' ' . $e['model']) ?: $e['category']) ?></b><br><span class="muted"><?= h($e['category']) ?><?= $e['phone_number'] ? ' · ' . h($e['phone_number']) : '' ?></span></td>
          <td class="mono" data-l="Serial"><?= h($e['serial'] ?: '—') ?></td>
          <td data-l="Installed"><?= h($e['installed_at'] ? fdate($e['installed_at']) : '—') ?></td>
          <td data-l="Warranty"><?php if (!$w): ?>—<?php elseif ($w < $today): ?><span class="warr-out">Ended <?= h(fdate($w)) ?></span><?php elseif ($w <= $soon): ?><span class="warr-soon">Ends <?= h(fdate($w)) ?></span><?php else: ?><span class="warr-ok">Until <?= h(fdate($w)) ?></span><?php endif; ?></td>
          <td class="pillcell"><span class="pill <?= $e['status'] === 'active' ? 'active' : h($e['status']) ?>"><?= h(['active' => 'In service', 'spare' => 'Spare', 'faulty' => 'Faulty'][$e['status']] ?? ucfirst($e['status'])) ?></span></td>
          <?php if ($canTicket): ?><td><a class="btn sm" href="/tickets/new?site=<?= (int)$s['id'] ?>&amp;equipment=<?= (int)$e['id'] ?>&amp;subject=<?= rawurlencode('Problem with ' . trim($e['make'] . ' ' . $e['model'])) ?>">Report a problem</a></td><?php endif; ?>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
