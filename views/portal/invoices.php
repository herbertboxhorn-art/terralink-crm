<?php $eft = trim(setting('eft_details')); $cc = trim(setting('cc_details')); $pay = setting('payment_email', 'payment@terralinksystems.com'); ?>
<div class="pagehead"><div><h1>Invoices</h1><div class="sub">Monthly invoices from <?= h(setting('company_name', 'TerraLink')) ?>.</div></div></div>
<div class="stats">
  <div class="stat<?= $overdue > 0 ? ' hot' : '' ?>"><small>Balance owing</small><b><?= h(money($owing)) ?></b><span><?= $overdue > 0 ? h(money($overdue)) . ' overdue' : 'nothing overdue' ?></span></div>
</div>
<div class="grid side">
  <div class="card">
    <?php if (!$rows): ?><div class="empty">No invoices yet.</div><?php else: ?>
    <div class="tablewrap"><table class="t cards">
      <thead><tr><th>Invoice</th><th>For</th><th>Issued</th><th>Due</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $i): $od = inv_overdue($i); ?>
        <tr>
          <td class="mono"><a class="row" href="/invoices/<?= (int)$i['id'] ?>/pdf" target="_blank"><?= h($i['number']) ?></a></td>
          <td data-l="For"><?= h($i['period'] ? inv_month_label($i['period']) : '—') ?></td>
          <td data-l="Issued"><?= h(fdate($i['issued_on'])) ?></td>
          <td data-l="Due"><?= h(fdate($i['due_on'])) ?></td>
          <td class="num" data-l="Total"><b><?= h(money($i['total'])) ?></b></td>
          <td class="pillcell"><?php if ($i['status'] === 'paid'): ?><span class="pill accepted">Paid <?= h(fdate($i['paid_on'], 'M j')) ?></span><?php elseif ($od): ?><span class="pill urgent">Overdue</span><?php else: ?><span class="pill sent">Due</span><?php endif; ?></td>
          <td><a class="btn sm" href="/invoices/<?= (int)$i['id'] ?>/pdf?dl=1">PDF</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </div>
  <div class="card">
    <h2>How to pay</h2>
    <dl class="kv" style="margin-top:8px">
      <dt>e-Transfer</dt><dd><a href="mailto:<?= h($pay) ?>"><?= h($pay) ?></a><br><span class="muted">Put the invoice number in the message.</span></dd>
      <?php if ($eft): ?><dt>EFT</dt><dd class="pay"><?= h($eft) ?></dd><?php endif; ?>
      <?php if ($cc): ?><dt>Credit card</dt><dd class="pay"><?= h($cc) ?></dd><?php endif; ?>
    </dl>
    <p class="muted" style="font-size:13px;margin:12px 0 0">We don't accept cheques. Questions about an invoice? Email <?= h(setting('company_email')) ?>.</p>
  </div>
</div>
