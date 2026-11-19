<div class="pagehead">
  <div><h1>Billing</h1><div class="sub">Closed, billable tickets with charges or unbilled time. Bill them, then mark them billed.</div></div>
</div>
<div class="filters"><div class="seg">
  <a href="/billing"<?= $tab === 'ready' ? ' class="on"' : '' ?>>Ready to bill</a>
  <a href="/billing?tab=billed"<?= $tab === 'billed' ? ' class="on"' : '' ?>>Billed</a>
</div></div>
<?php if ($tab === 'ready' && $openWithCharges): ?><p class="muted" style="margin:0 0 10px"><?= $openWithCharges ?> open ticket<?= $openWithCharges === 1 ? ' has' : 's have' ?> charges too. They show here once closed.</p><?php endif; ?>
<?php $sum = array_sum(array_column($rows, 'total')); ?>
<form method="post" action="/billing/mark" data-confirm="Mark the ticked tickets as billed? Their charges will be locked."><?= csrf_field() ?>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><?= $tab === 'ready' ? 'Nothing waiting to be billed.' : 'Nothing billed yet.' ?></div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><?php if ($tab === 'ready'): ?><th style="width:34px"></th><?php endif; ?><th>Ticket</th><th>Company · site</th><th><?= $tab === 'ready' ? 'Closed' : 'Billed' ?></th><th class="num">Lines</th><th class="num">Total incl. tax</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <?php if ($tab === 'ready'): ?><td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select <?= h($r['number']) ?>"<?= $r['unbilled'] ? '' : ' checked' ?>></td><?php endif; ?>
        <td><a class="row" href="/tickets/<?= (int)$r['id'] ?>#charges"><span class="mono"><?= h($r['number']) ?></span> <?= h($r['subject']) ?></a>
          <?php if ($r['unbilled']): ?><br><span class="sla sla-soon"><?= h(dur($r['unbilled'])) ?> logged time not billed</span><?php endif; ?></td>
        <td><a href="/companies/<?= (int)$r['company_id'] ?>"><?= h($r['company']) ?></a><?= $r['site'] ? '<br><span class="muted">' . h($r['site']) . '</span>' : '' ?></td>
        <td data-l="<?= $tab === 'ready' ? 'Closed' : 'Billed' ?>"><?= h(fdate($tab === 'ready' ? $r['closed_at'] : $r['billed_at'], 'M j, Y')) ?></td>
        <td class="num" data-l="Lines"><?= (int)$r['n'] ?></td>
        <td class="num" data-l="Total"><b><?= h(money($r['total'])) ?></b></td>
        <td><a class="btn sm" href="/tickets/<?= (int)$r['id'] ?>/summary" target="_blank">PDF</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="actions" style="margin-top:12px;justify-content:space-between;flex-wrap:wrap">
    <span><?= count($rows) ?> ticket<?= count($rows) === 1 ? '' : 's' ?> · <b><?= h(money($sum)) ?></b> incl. tax</span>
    <?php if ($tab === 'ready'): ?><button class="btn primary" type="submit">Mark ticked as billed</button><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</form>
