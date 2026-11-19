<?php
$open = 0; $openVal = 0;
foreach (['new', 'audit_booked', 'quoted'] as $s) { $open += $pipeline[$s]['n'] ?? 0; $openVal += $pipeline[$s]['v'] ?? 0; }
$wonVal = (float)val("SELECT COALESCE(SUM(value),0) FROM deals WHERE stage = 'won' AND stage_changed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$first = explode(' ', user()['name'])[0];
?>
<div class="pagehead">
  <div><h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= h($first) ?></h1>
  <div class="sub"><?= date('l, F j') ?> · <a href="/today">Open My day →</a></div></div>
  <div class="actions">
    <a class="btn primary" href="/deals/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Deal</a>
    <a class="btn" href="/tickets/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Ticket</a>
    <a class="btn" href="/companies/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Company</a>
  </div>
</div>

<?php if ($travelUnset): ?><div class="notice">Set your <b>travel rate per km</b> in <a href="/settings">Settings</a> so quotes can add Northern BC travel automatically.</div><?php endif; ?>

<div class="stats">
  <a class="stat<?= ($pipeline['new']['n'] ?? 0) ? ' hot' : '' ?>" href="/pipeline"><small>New leads</small><b><?= (int)($pipeline['new']['n'] ?? 0) ?></b><span>waiting for first call</span></a>
  <a class="stat<?= $slaOverdue || $clientWaiting ? ' hot' : '' ?>" href="/tickets?f=<?= $clientWaiting ? 'client' : ($slaOverdue ? 'overdue' : 'open') ?>"><small>Open tickets</small><b><?= (int)val("SELECT COUNT(*) FROM tickets WHERE status <> 'closed'") ?></b><span><?= implode(' · ', array_filter([$clientWaiting ? "$clientWaiting client waiting" : '', $slaOverdue ? "$slaOverdue past response target" : ''])) ?: 'all on time' ?></span></a>
  <a class="stat<?= $overdueCount ? ' hot' : '' ?>" href="/today"><small>My tasks</small><b><?= $myTaskCount ?></b><span><?= $overdueCount ? "$overdueCount overdue" : 'none overdue' ?></span></a>
  <a class="stat" href="/pipeline"><small>Open pipeline</small><b><?= h(money0($openVal)) ?></b><span><?= $open ?> active deal<?= $open === 1 ? '' : 's' ?></span></a>
  <a class="stat" href="/pipeline"><small>Won in <?= date('F') ?></small><b><?= h(money0($wonVal)) ?></b><span>closed deals</span></a>
</div>

<div class="grid g2">
  <div class="card">
    <div class="cardhead"><h2>My follow-ups</h2><a href="/tasks">All tasks →</a></div>
    <?php if (!$myTasks): ?><div class="empty">Nothing on your plate. 👍</div><?php else: ?>
    <ul class="list">
      <?php foreach ($myTasks as $t): $over = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
      <li>
        <form method="post" action="/tasks/<?= (int)$t['id'] ?>/toggle"><?= csrf_field() ?><button class="btn sm" type="submit" aria-label="Mark done">✓</button></form>
        <div class="grow"><b><?= h($t['title']) ?></b>
          <span class="meta"><?php if ($t['company']): ?><a href="/companies/<?= (int)$t['company_id'] ?>"><?= h($t['company']) ?></a> · <?php endif; ?>
          <span<?= $over ? ' style="color:var(--bad);font-weight:600"' : '' ?>><?= $t['due_date'] ? 'Due ' . h(fdate($t['due_date'], 'D M j')) : 'No date' ?></span></span></div>
      </li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </div>

  <div class="card">
    <div class="cardhead"><h2>Open tickets</h2><a href="/tickets">All tickets →</a></div>
    <?php if (!$openTickets): ?><div class="empty">No open tickets.</div><?php else: ?>
    <ul class="list">
      <?php foreach ($openTickets as $t): ?>
      <li><span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span>
        <div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a>
          <span class="meta"><?= h($t['number']) ?> · <?= h($t['company']) ?> · <?= h(ago($t['created_at'])) ?></span></div>
        <?= sla_badge($t) ?: '<span class="pill ' . h($t['status']) . '">' . h(TICKET_STATUS[$t['status']]) . '</span>' ?>
        <?= avatar($t['assignee'], $t['assigned_to']) ?></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </div>

  <div class="card">
    <div class="cardhead"><h2>Newest leads</h2><a href="/pipeline">Pipeline →</a></div>
    <?php if (!$newLeads): ?><div class="empty">No new leads.</div><?php else: ?>
    <ul class="list">
      <?php foreach ($newLeads as $d): ?>
      <li><div class="grow"><a href="/deals/<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a>
        <span class="meta"><?= h($d['service']) ?> · <?= h(SOURCES[$d['source']] ?? $d['source']) ?> · <?= h(ago($d['created_at'])) ?></span></div>
        <?php if ($d['value'] > 0): ?><b><?= h(money0($d['value'])) ?></b><?php endif; ?>
        <?= avatar($d['owner'], $d['owner_id']) ?></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </div>

  <div class="card">
    <div class="cardhead"><h2>Warranties ending (60 days)</h2><a href="/equipment?f=warranty">Inventory →</a></div>
    <?php if (!$warranty): ?><div class="empty">No warranties ending soon.</div><?php else: ?>
    <ul class="list">
      <?php foreach ($warranty as $e): ?>
      <li><div class="grow"><a href="/sites/<?= (int)$e['site_id'] ?>"><?= h(trim($e['make'] . ' ' . $e['model']) ?: $e['category']) ?></a>
        <span class="meta"><?= h($e['company']) ?> · <?= h($e['site']) ?><?= $e['serial'] ? ' · S/N ' . h($e['serial']) : '' ?></span></div>
        <span class="pill high"><?= h(fdate($e['warranty_until'], 'M j')) ?></span></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </div>

  <?php if ($lowStock): ?>
  <div class="card">
    <div class="cardhead"><h2>Low stock: re-order</h2><a href="/equipment?tab=consumables&amp;f=low">Consumables →</a></div>
    <ul class="list">
      <?php foreach ($lowStock as $c): ?>
      <li><div class="grow"><a href="/consumables/<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a><span class="meta">Re-order at <?= h(qty_fmt($c['reorder_level'])) ?> <?= h($c['unit']) ?></span></div>
        <span class="pill <?= (float)$c['qty_on_hand'] <= 0 ? 'urgent' : 'high' ?>"><?= h(qty_fmt($c['qty_on_hand'])) ?> <?= h($c['unit']) ?> left</span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
