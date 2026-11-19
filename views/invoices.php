<div class="pagehead">
  <div><h1>Invoices</h1><div class="sub">Drafts are made automatically on the 1st (next: <?= h($nextRun) ?>). Check each one, then approve it to email it to the client.</div></div>
  <div class="actions">
    <a class="btn" href="/billing">Ready to bill<?= $ready ? ' (' . $ready . ')' : '' ?></a>
    <?php if (is_role('admin')): ?><form method="post" action="/invoices/generate" data-confirm="Add all ready-to-bill tickets and this month's service plan fees to draft invoices now? Nothing is emailed until you approve."><?= csrf_field() ?><button class="btn dark" type="submit">Make drafts now</button></form><?php endif; ?>
  </div>
</div>
<div class="stats">
  <div class="stat"><small>Drafts to review</small><b><?= (int)($counts['draft']['n'] ?? 0) ?></b><span><?= h(money0($counts['draft']['t'] ?? 0)) ?> incl. tax</span></div>
  <div class="stat"><small>Owing</small><b><?= h(money0($owing)) ?></b><span>sent, not paid</span></div>
  <div class="stat"><small>Overdue</small><b<?= $overdue ? ' style="color:var(--bad)"' : '' ?>><?= $overdue ?></b><span>past the due date</span></div>
</div>
<div class="filters"><div class="seg">
  <?php foreach (['draft' => 'Drafts', 'open' => 'Sent / owing', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'All'] as $k => $l): ?><a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a><?php endforeach; ?>
</div></div>
<form method="post" action="/invoices/approve" data-confirm="Approve the ticked drafts and email them to the clients now?"><?= csrf_field() ?>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><?= $f === 'draft' ? 'No drafts waiting. The next ones are made on ' . h($nextRun) . '.' : 'Nothing here.' ?></div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><?php if ($f === 'draft'): ?><th style="width:34px"></th><?php endif; ?><th>Invoice</th><th>Company</th><th>Bill to</th><th><?= $f === 'draft' ? 'For' : 'Due' ?></th><th class="num">Total</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $od = inv_overdue($r); ?>
      <tr>
        <?php if ($f === 'draft'): ?><td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select"></td><?php endif; ?>
        <td><a class="row" href="/invoices/<?= (int)$r['id'] ?>"><?= h(inv_label($r)) ?></a>
          <br><small class="muted"><?= (int)$r['tickets'] ?> ticket<?= (int)$r['tickets'] === 1 ? '' : 's' ?><?= (int)$r['auto'] ? ' · <span style="color:var(--orange)">' . (int)$r['auto'] . ' auto-billed time</span>' : '' ?></small></td>
        <td><a href="/companies/<?= (int)$r['company_id'] ?>"><?= h($r['company']) ?></a></td>
        <td data-l="Bill to"><?= $r['bill_to_email'] ? h($r['bill_to_email']) : '<span class="err">No email</span>' ?></td>
        <td data-l="<?= $f === 'draft' ? 'For' : 'Due' ?>"><?= $r['status'] === 'draft' ? h($r['period'] ? inv_month_label($r['period']) : '—') : h(fdate($r['due_on'])) ?></td>
        <td class="num" data-l="Total"><b><?= h(money($r['total'])) ?></b></td>
        <td><span class="pill<?= $od ? ' urgent' : ($r['status'] === 'paid' ? ' closed' : ($r['status'] === 'draft' ? ' waiting' : ' open')) ?>"><?= $od ? 'Overdue' : h(INVOICE_STATUS[$r['status']]) ?></span></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if ($f === 'draft'): ?><div class="actions" style="margin-top:12px;justify-content:flex-end"><button class="btn primary" type="submit">Approve &amp; email ticked</button></div><?php endif; ?>
  <?php endif; ?>
</div>
</form>
