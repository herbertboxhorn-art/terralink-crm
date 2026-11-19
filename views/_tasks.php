<?php /** @var array $tasks @var array $link e.g. ['company_id'=>5] @var string $back */ ?>
<div class="card">
  <h2>Follow-ups</h2>
  <?php if ($tasks): ?>
  <ul class="list" style="margin-bottom:12px">
    <?php foreach ($tasks as $t): $overdue = !$t['done_at'] && $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
    <li>
      <form method="post" action="/tasks/<?= (int)$t['id'] ?>/toggle"><?= csrf_field() ?>
        <button class="btn sm" type="submit" title="<?= $t['done_at'] ? 'Reopen' : 'Mark done' ?>" aria-label="Toggle done"><?= $t['done_at'] ? '↺' : '✓' ?></button></form>
      <div class="grow"<?= $t['done_at'] ? ' style="opacity:.55;text-decoration:line-through"' : '' ?>>
        <b><?= h($t['title']) ?></b>
        <span class="meta"><?= $t['due_date'] ? '<span' . ($overdue ? ' style="color:var(--bad);font-weight:600"' : '') . '>Due ' . h(fdate($t['due_date'], 'M j')) . '</span> · ' : '' ?><?= h($t['assignee'] ?? 'Unassigned') ?></span>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <form method="post" action="/tasks" class="inline">
    <?= csrf_field() ?>
    <?php foreach ($link as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= (int)$v ?>"><?php endforeach; ?>
    <input type="hidden" name="back" value="<?= h($back) ?>">
    <input name="title" placeholder="New follow-up…" required>
    <div class="row">
      <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+2 weekdays')) ?>" aria-label="Due date">
      <select name="assigned_to" aria-label="Assign to"><?php foreach (user_opts() as $id => $n): if ($id === '') continue; ?><option value="<?= $id ?>"<?= $id == uid() ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
      <button class="btn" type="submit">Add</button>
    </div>
  </form>
</div>
