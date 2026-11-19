<div class="pagehead">
  <div><h1>My day</h1><div class="sub"><?= date('l, F j') ?> · <?= count($tickets) ?> open ticket<?= count($tickets) === 1 ? '' : 's' ?> · <?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?> due</div></div>
  <div class="actions"><a class="btn" href="/tickets/new">+ Ticket</a><a class="btn" href="/tasks">All tasks</a></div>
</div>

<?php if (!$tickets && !$tasks): ?>
<div class="card"><div class="empty" style="padding:30px 0">Nothing assigned to you for today. 🎉<br><a href="/tickets">See all open tickets</a></div></div>
<?php endif; ?>

<?php if ($tickets): ?>
<h2 style="margin:6px 0 12px">My tickets</h2>
<div class="grid g2" style="margin-bottom:22px">
  <?php foreach ($tickets as $t): $addr = trim(implode(', ', array_filter([$t['s_address'], $t['s_town']]))); $tel = $t['ct_mobile'] ?: $t['ct_phone']; ?>
  <div class="card daycard <?= h($t['priority']) ?>">
    <div class="cardhead" style="margin-bottom:6px">
      <h3><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a></h3>
      <span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span>
    </div>
    <div class="muted" style="font-size:14px"><span class="mono"><?= h($t['number']) ?></span> · <?= h($t['company']) ?><?= $t['site'] ? ' · ' . h($t['site']) : '' ?> · <span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span> <?= sla_badge($t) ?></div>
    <?php if ($t['access_notes']): ?><div class="access"><b>Access:</b> <?= h($t['access_notes']) ?></div><?php endif; ?>
    <div class="quick" style="margin-top:10px">
      <?php if ($addr || $t['latitude']): ?><a href="https://maps.google.com/?q=<?= rawurlencode($t['latitude'] && $t['longitude'] ? $t['latitude'] . ',' . $t['longitude'] : $addr) ?>" target="_blank" rel="noopener"><svg class="i"><use href="/assets/icons.svg#pin"/></svg>Directions</a><?php endif; ?>
      <?php if ($tel): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $tel)) ?>"><svg class="i"><use href="/assets/icons.svg#phone"/></svg><?= h(trim($t['first_name'] . ' ' . $t['last_name']) ?: 'Call') ?></a><?php endif; ?>
      <a href="/tickets/<?= (int)$t['id'] ?>"><svg class="i"><use href="/assets/icons.svg#ticket"/></svg>Update</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2>Due today &amp; overdue</h2>
    <?php if (!$tasks): ?><div class="empty">No tasks due.</div><?php else: ?>
    <ul class="list"><?php foreach ($tasks as $t): $over = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
      <li>
        <form method="post" action="/tasks/<?= (int)$t['id'] ?>/toggle"><?= csrf_field() ?><button class="btn sm" type="submit" aria-label="Mark done">✓</button></form>
        <div class="grow"><b><?= h($t['title']) ?></b>
          <span class="meta"><?php if ($t['company']): ?><a href="/companies/<?= (int)$t['company_id'] ?>"><?= h($t['company']) ?></a> · <?php endif; ?>
          <?php if ($t['ticket']): ?><a href="/tickets/<?= (int)$t['ticket_id'] ?>"><?= h($t['ticket']) ?></a> · <?php endif; ?>
          <span<?= $over ? ' style="color:var(--bad);font-weight:600"' : '' ?>><?= $t['due_date'] ? ($over ? 'Overdue since ' : 'Due ') . h(fdate($t['due_date'], 'D M j')) : 'No date' ?></span></span></div>
      </li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
  <div class="card">
    <h2>Coming up (7 days)</h2>
    <?php if (!$upcoming): ?><div class="empty">Nothing scheduled.</div><?php else: ?>
    <ul class="list"><?php foreach ($upcoming as $t): ?>
      <li><div class="grow"><b><?= h($t['title']) ?></b><span class="meta"><?= $t['company'] ? h($t['company']) . ' · ' : '' ?><?= h(fdate($t['due_date'], 'D M j')) ?></span></div></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
