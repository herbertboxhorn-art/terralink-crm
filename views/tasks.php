<div class="pagehead"><div><h1>Tasks</h1></div></div>
<div class="filters">
  <div class="seg">
    <?php foreach (['mine' => 'Mine', 'all' => 'All open', 'overdue' => 'Overdue', 'done' => 'Done'] as $k => $l): ?>
    <a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="grid side">
  <div class="card">
    <?php if (!$rows): ?><div class="empty">No tasks here.</div><?php else: ?>
    <ul class="list"><?php foreach ($rows as $t): $over = !$t['done_at'] && $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
      <li>
        <form method="post" action="/tasks/<?= (int)$t['id'] ?>/toggle"><?= csrf_field() ?><button class="btn sm" type="submit" aria-label="Toggle done"><?= $t['done_at'] ? '↺' : '✓' ?></button></form>
        <div class="grow">
          <b><?= h($t['title']) ?></b>
          <span class="meta">
            <?php if ($t['company']): ?><a href="/companies/<?= (int)$t['company_id'] ?>"><?= h($t['company']) ?></a> · <?php endif; ?>
            <?php if ($t['deal']): ?><a href="/deals/<?= (int)$t['deal_id'] ?>"><?= h($t['deal']) ?></a> · <?php endif; ?>
            <?php if ($t['ticket']): ?><a href="/tickets/<?= (int)$t['ticket_id'] ?>"><?= h($t['ticket']) ?></a> · <?php endif; ?>
            <span<?= $over ? ' style="color:var(--bad);font-weight:600"' : '' ?>><?= $t['done_at'] ? 'Done ' . h(ago($t['done_at'])) : ($t['due_date'] ? 'Due ' . h(fdate($t['due_date'], 'D M j')) : 'No date') ?></span>
            <?= $f !== 'mine' ? ' · ' . h($t['assignee'] ?? 'Unassigned') : '' ?>
          </span>
        </div>
        <form method="post" action="/tasks/<?= (int)$t['id'] ?>/delete" data-confirm="Delete this task?"><?= csrf_field() ?><button class="linkbtn" type="submit" aria-label="Delete">✕</button></form>
      </li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
  <?php $tasks = []; $link = []; $back = '/tasks?f=' . $f; require __DIR__ . '/_tasks.php'; ?>
</div>
