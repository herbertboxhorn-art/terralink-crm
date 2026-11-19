<div class="pagehead">
  <div><h1>Service plans</h1><div class="sub">Recurring visits. Tickets open automatically before each one is due.</div></div>
  <div class="actions"><a class="btn primary" href="/plans/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New plan</a></div>
</div>
<div class="stats">
  <div class="stat"><small>Active plans</small><b><?= $activeCount ?></b><span>creating tickets</span></div>
  <div class="stat"><small>Visits next 30 days</small><b><?= $soon ?></b><span>due by <?= h(date('M j', strtotime('+30 days'))) ?></span></div>
  <div class="stat"><small>Recurring revenue</small><b><?= h(money0($mrr)) ?></b><span>per month, from plan fees</span></div>
</div>
<div class="filters"><div class="seg">
  <?php foreach (['active' => 'Active', 'paused' => 'Paused', 'all' => 'All'] as $k => $l): ?><a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a><?php endforeach; ?>
</div></div>
<div class="card">
  <?php if (!$plans): ?><div class="empty">No plans yet. Add one for each client you visit regularly, for example a quarterly check of a camp's Starlink and Wi-Fi.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Plan</th><th>Company · site</th><th>How often</th><th>Next visit</th><th>Assigned</th><th class="num">Fee</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p): $days = (int)floor((strtotime($p['next_due']) - strtotime('today')) / 86400); ?>
      <tr<?= $p['active'] ? '' : ' class="muted"' ?>>
        <td><a class="row" href="/plans/<?= (int)$p['id'] ?>/edit"><?= h($p['title']) ?></a><?= $p['active'] ? '' : ' <span class="pill">Paused</span>' ?>
          <?php if ($p['last_number']): ?><br><small class="muted">Last: <a href="/tickets/<?= (int)$p['last_ticket_id'] ?>"><?= h($p['last_number']) ?></a> · <?= h(TICKET_STATUS[$p['last_status']] ?? '') ?></small><?php endif; ?></td>
        <td><a href="/companies/<?= (int)$p['company_id'] ?>"><?= h($p['company']) ?></a><?= $p['site'] ? '<br><span class="muted">' . h($p['site']) . '</span>' : '' ?></td>
        <td data-l="Every"><?= h(PLAN_EVERY[(int)$p['every_months']] ?? $p['every_months'] . ' months') ?></td>
        <td data-l="Next"><b><?= h(fdate($p['next_due'], 'M j, Y')) ?></b>
          <?php if ($p['active']): ?><br><span class="sla <?= $days <= (int)$p['lead_days'] ? 'sla-soon' : 'sla-ok' ?>"><?= $days < 0 ? 'overdue' : ($days === 0 ? 'today' : "in $days d") ?></span><?php endif; ?></td>
        <td data-l="Assigned"><?= $p['assignee'] ? avatar($p['assignee'], $p['assigned_to']) . ' ' . h($p['assignee']) : '—' ?></td>
        <td class="num" data-l="Fee"><?= (float)$p['fee'] ? h(money($p['fee'])) : '—' ?></td>
        <td><?php if ($p['active']): ?><form method="post" action="/plans/<?= (int)$p['id'] ?>/run" data-confirm="Open the ticket for this visit now and move the next visit on?"><?= csrf_field() ?><button class="btn sm" type="submit">Open ticket now</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
