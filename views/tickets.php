<div class="pagehead">
  <div><h1>Tickets</h1><div class="sub"><?= count($rows) ?> shown</div></div>
  <div class="actions"><a class="btn primary" href="/tickets/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New ticket</a></div>
</div>
<div class="filters"><div class="seg">
  <?php foreach (['open' => 'Open', 'mine' => 'Mine', 'client' => 'Client waiting' . (!empty($clientWaiting) ? " ($clientWaiting)" : ''), 'overdue' => 'Overdue', 'closed' => 'Closed', 'all' => 'All'] as $k => $l): ?>
  <a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a>
  <?php endforeach; ?>
</div></div>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No tickets here.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>#</th><th>Subject</th><th>Client / site</th><th>Priority</th><th>Status</th><th>Assigned</th><th class="num">Time</th><th>Opened</th></tr></thead>
    <tbody><?php foreach ($rows as $t): ?>
      <tr>
        <td class="mono"><?= h($t['number']) ?></td>
        <td><a class="row" href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><?= sla_state($t) ? '<br>' . sla_badge($t) : '' ?><?= client_waiting_badge($t) ? '<br>' . client_waiting_badge($t) : '' ?></td>
        <td><?= h($t['company']) ?><?= $t['site'] ? '<br><span class="muted">' . h($t['site']) . '</span>' : '' ?></td>
        <td class="pillcell"><span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span></td>
        <td class="pillcell"><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span></td>
        <td data-l="Assigned"><?= $t['assignee'] ? avatar($t['assignee'], $t['assigned_to']) . ' ' . h($t['assignee']) : '—' ?></td>
        <td class="num" data-l="Time"><?= h(dur((int)$t['minutes_spent'])) ?></td>
        <td data-l="Opened"><?= h(ago($t['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
