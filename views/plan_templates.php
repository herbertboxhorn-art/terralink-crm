<div class="pagehead">
  <div><h1>Plan templates</h1><div class="sub">Standard plans you add to clients. Pick one under "Start from a template" on a new plan.</div></div>
  <div class="actions"><a class="btn" href="/plans">Service plans</a><?php if (is_role('admin', 'sales')): ?><a class="btn primary" href="/plans/templates/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New template</a><?php endif; ?></div>
</div>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No templates yet.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Template</th><th>How often</th><th class="num">Ticket opens</th><th class="num">Fee per visit</th><th class="num">Per month</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $t): ?>
      <tr<?= $t['active'] ? '' : ' class="muted"' ?>>
        <td><?php if (is_role('admin', 'sales')): ?><a class="row" href="/plans/templates/<?= (int)$t['id'] ?>/edit"><?= h($t['name']) ?></a><?php else: ?><b><?= h($t['name']) ?></b><?php endif; ?><?= $t['active'] ? '' : ' <span class="pill">Hidden</span>' ?>
          <?= $t['fee_note'] ? '<br><small class="muted">' . h($t['fee_note']) . '</small>' : '' ?></td>
        <td data-l="Every"><?= h(PLAN_EVERY[(int)$t['every_months']] ?? '') ?></td>
        <td class="num" data-l="Opens"><?= (int)$t['lead_days'] ?> days before</td>
        <td class="num" data-l="Fee"><?= (float)$t['fee'] ? h(money($t['fee'])) : 'per client' ?></td>
        <td class="num" data-l="Per month"><?= (float)$t['fee'] ? h(money((float)$t['fee'] / max(1, (int)$t['every_months']))) : '—' ?></td>
        <td><?php if ($t['active']): ?><a class="btn sm" href="/plans/new?template=<?= (int)$t['id'] ?>">Use</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
