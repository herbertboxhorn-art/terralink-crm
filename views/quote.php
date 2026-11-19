<?php $id = (int)$qt['id']; ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/quotes">← Quotes</a>
    <h1><?= h($qt['title']) ?></h1>
    <div class="sub"><span class="mono"><?= h($qt['number']) ?></span><span class="pill <?= h($qt['status']) ?>"><?= h(QUOTE_STATUS[$qt['status']]) ?></span>
      <a href="/companies/<?= (int)$qt['company_id'] ?>"><?= h($qt['company']) ?></a>
      <?php if ($qt['deal_id']): ?><a href="/deals/<?= (int)$qt['deal_id'] ?>">Deal: <?= h($qt['deal']) ?></a><?php endif; ?></div>
  </div>
  <div class="actions">
    <a class="btn primary" href="/quotes/<?= $id ?>/email"><svg class="i"><use href="/assets/icons.svg#send"/></svg>Email to client</a>
    <a class="btn" href="/quotes/<?= $id ?>/pdf" target="_blank">PDF</a>
    <a class="btn" href="/quotes/<?= $id ?>/edit">Edit</a>
    <form method="post" action="/quotes/<?= $id ?>/duplicate"><?= csrf_field() ?><button class="btn" type="submit">Copy as revision</button></form>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <form method="post" action="/quotes/<?= $id ?>/status" class="stagebar"><?= csrf_field() ?>
    <?php foreach (QUOTE_STATUS as $k => $l): ?><button type="submit" name="status" value="<?= $k ?>"<?= $qt['status'] === $k ? ' class="on"' : '' ?>><?= h($l) ?></button><?php endforeach; ?>
  </form>
  <?php if ($qt['deal_id']): ?><p class="muted" style="font-size:13px;margin:10px 0 0">Marking it Accepted moves the linked deal to Won; Declined moves it to Lost.</p><?php endif; ?>
</div>

<div class="grid side">
  <div class="stack">
    <div class="card">
      <div class="tablewrap"><table class="t">
        <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Line</th></tr></thead>
        <tbody><?php foreach ($items as $i): ?>
          <tr><td><?= h($i['description']) ?><br><small><?= h(ITEM_KINDS[$i['kind']]) ?><?= $i['pst_applies'] ? ' · PST' : '' ?></small></td>
            <td class="num"><?= h(rtrim(rtrim(number_format((float)$i['qty'], 2), '0'), '.')) ?> <?= h($i['unit']) ?></td>
            <td class="num"><?= h(money($i['unit_price'])) ?></td>
            <td class="num"><b><?= h(money($i['qty'] * $i['unit_price'])) ?></b></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <div class="totals" style="margin-top:14px">
        <div><span>Subtotal</span><span><?= h(money($tot['subtotal'])) ?></span></div>
        <div><span>GST (<?= (float)$qt['gst_rate'] ?>%)</span><span><?= h(money($tot['gst'])) ?></span></div>
        <div><span>PST (<?= (float)$qt['pst_rate'] ?>% on <?= h(money($tot['pst_base'])) ?>)</span><span><?= h(money($tot['pst'])) ?></span></div>
        <div class="grand"><span>Total CAD</span><span><?= h(money($tot['total'])) ?></span></div>
      </div>
    </div>
    <?php $etype = 'quote'; $eid = $id; require __DIR__ . '/_timeline.php'; ?>
  </div>
  <div class="stack">
    <div class="card"><h2>Details</h2>
      <dl class="kv">
        <?php if ($qt['first_name']): ?><dt>Attention</dt><dd><?= h(trim($qt['first_name'] . ' ' . $qt['last_name'])) ?><?php if ($qt['ct_email']): ?><br><a href="mailto:<?= h($qt['ct_email']) ?>?subject=<?= rawurlencode('Quote ' . $qt['number'] . ' — ' . $qt['title']) ?>"><?= h($qt['ct_email']) ?></a><?php endif; ?></dd><?php endif; ?>
        <dt>Issued</dt><dd><?= h(fdate($qt['issued_on'])) ?></dd>
        <?php if ($qt['valid_until']): ?><dt>Valid until</dt><dd><?= h(fdate($qt['valid_until'])) ?></dd><?php endif; ?>
        <dt>Prepared by</dt><dd><?= h($qt['author'] ?? '—') ?></dd>
      </dl>
      <?php if ($qt['notes']): ?><p style="white-space:pre-wrap;margin:12px 0 0"><?= h($qt['notes']) ?></p><?php endif; ?>
    </div>
    <?php $ftype = 'quote'; $fid = $id; require __DIR__ . '/_files.php'; ?>
    <?php if (is_role('admin', 'sales')): ?>
    <form method="post" action="/quotes/<?= $id ?>/delete" data-confirm="Delete quote <?= h($qt['number']) ?>?"><?= csrf_field() ?><button class="btn danger sm" type="submit">Delete quote</button></form>
    <?php endif; ?>
  </div>
</div>
