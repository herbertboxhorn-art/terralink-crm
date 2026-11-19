<?php $all = portal_can('perm_tickets_all'); ?>
<div class="pagehead">
  <div><h1>Tickets</h1><div class="sub"><?= $all ? 'All tickets for ' . h(portal_user()['company']) : 'Tickets you opened or are the contact on' ?></div></div>
  <div class="actions"><a class="btn primary" href="/tickets/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New ticket</a></div>
</div>
<div class="filters"><div class="seg">
  <?php foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $k => $l): ?><a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a><?php endforeach; ?>
</div></div>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><?= $f === 'open' ? 'No open tickets. 👍' : 'No tickets here.' ?></div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>#</th><th>Subject</th><th>Site</th><?php if ($all): ?><th>Opened by</th><?php endif; ?><th>Status</th><th>Updated</th></tr></thead>
    <tbody><?php foreach ($rows as $t): ?>
      <tr>
        <td class="mono"><?= h($t['number']) ?></td>
        <td><a class="row" href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><?= $t['priority'] === 'urgent' || $t['priority'] === 'high' ? ' <span class="pill ' . h($t['priority']) . '">' . h(TICKET_PRIORITY[$t['priority']]) . '</span>' : '' ?></td>
        <td data-l="Site"><?= h($t['site'] ?? '—') ?></td>
        <?php if ($all): ?><td data-l="Opened by"><?= h($t['opener'] ?? (trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: setting('company_name'))) ?></td><?php endif; ?>
        <td class="pillcell"><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span></td>
        <td data-l="Updated"><?= h(ago($t['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
