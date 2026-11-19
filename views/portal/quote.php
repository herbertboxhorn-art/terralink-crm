<?php $id = (int)$qt['id']; $co = portal_user()['company']; ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/quotes">← Quotes</a>
    <h1><?= h($qt['title']) ?></h1>
    <div class="sub"><span class="mono"><?= h($qt['number']) ?></span>
      <?php if ($open): ?><span class="pill sent">Needs your decision</span><?php elseif ($qt['status'] === 'sent'): ?><span class="pill expired">Expired</span><?php else: ?><span class="pill <?= h($qt['status']) ?>"><?= h(QUOTE_STATUS[$qt['status']]) ?></span><?php endif; ?>
      <span>Issued <?= h(fdate($qt['issued_on'])) ?></span><?php if ($qt['valid_until']): ?><span>Valid until <?= h(fdate($qt['valid_until'])) ?></span><?php endif; ?></div>
  </div>
  <div class="actions"><a class="btn" href="/quotes/<?= $id ?>/pdf" target="_blank">View PDF</a><a class="btn" href="/quotes/<?= $id ?>/pdf?dl=1">Download</a></div>
</div>

<?php if ($qt['client_decision_by']): ?><div class="notice"><?= h(QUOTE_STATUS[$qt['status']]) ?> by <b><?= h($qt['client_decision_by']) ?></b> on <?= h(fdate($qt['client_decision_at'], 'M j, Y g:i a')) ?>.</div><?php endif; ?>

<div class="grid side">
  <div class="card">
    <h2>Items</h2>
    <div class="tablewrap" style="margin-top:8px"><table class="t">
      <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
      <tbody><?php foreach ($items as $i): ?>
        <tr><td><?= h($i['description']) ?><br><small class="muted"><?= h(ITEM_KINDS[$i['kind']] ?? '') ?><?= $i['pst_applies'] ? ' · PST applies' : '' ?></small></td>
          <td class="num"><?= h(qty_fmt($i['qty'])) ?> <?= h($i['unit']) ?></td><td class="num"><?= h(money($i['unit_price'])) ?></td>
          <td class="num"><?= h(money(round((float)$i['qty'] * (float)$i['unit_price'], 2))) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <dl class="kv" style="max-width:340px;margin:14px 0 0 auto">
      <dt>Subtotal</dt><dd class="num"><?= h(money($tot['subtotal'])) ?></dd>
      <dt>GST <?= h(qty_fmt($qt['gst_rate'])) ?>%</dt><dd class="num"><?= h(money($tot['gst'])) ?></dd>
      <?php if ($tot['pst'] > 0): ?><dt>PST <?= h(qty_fmt($qt['pst_rate'])) ?>%</dt><dd class="num"><?= h(money($tot['pst'])) ?></dd><?php endif; ?>
      <dt><b>Total (CAD)</b></dt><dd class="num"><b><?= h(money($tot['total'])) ?></b></dd>
    </dl>
    <?php if ($qt['notes']): ?><p style="white-space:pre-wrap;margin:16px 0 0;padding:12px 14px;background:var(--bg);border-radius:8px"><?= h($qt['notes']) ?></p><?php endif; ?>
    <?php if (setting('quote_terms')): ?><p class="muted" style="white-space:pre-wrap;font-size:13px;margin:14px 0 0"><?= h(setting('quote_terms')) ?></p><?php endif; ?>
  </div>
  <div class="stack">
    <?php if ($open): ?>
    <div class="card" id="decide">
      <h2>Accept this quote</h2>
      <form method="post" action="/quotes/<?= $id ?>/decide" class="form" style="grid-template-columns:1fr;margin-top:10px"><?= csrf_field() ?>
        <div class="field check"><label><input type="checkbox" name="authorized" value="1" required> I'm authorized to approve <?= h(money($tot['total'])) ?> (incl. tax) for <?= h($co) ?>.</label></div>
        <div class="field"><label>Note for us (optional)</label><textarea name="reason" rows="2" placeholder="PO number, preferred install dates…"></textarea></div>
        <button class="btn primary" type="submit" name="decision" value="accepted">Accept quote</button>
      </form>
      <details style="margin-top:14px"><summary class="muted" style="cursor:pointer">Decline instead</summary>
        <form method="post" action="/quotes/<?= $id ?>/decide" class="form" style="grid-template-columns:1fr;margin-top:10px" data-confirm="Decline this quote?"><?= csrf_field() ?>
          <div class="field"><label>Reason (optional, helps us)</label><textarea name="reason" rows="2"></textarea></div>
          <button class="btn" type="submit" name="decision" value="declined">Decline quote</button>
        </form>
      </details>
    </div>
    <?php elseif ($qt['status'] === 'sent'): ?>
    <div class="card"><h2>This quote has expired</h2><p class="muted">Prices may have changed. Contact us and we'll send an updated quote.</p></div>
    <?php endif; ?>
    <div class="card"><h2>Questions?</h2><p class="muted" style="margin:6px 0 0">Call <?= tel(setting('company_phone')) ?> or email <a href="mailto:<?= h(setting('company_email')) ?>"><?= h(setting('company_email')) ?></a> and mention <?= h($qt['number']) ?>.</p></div>
  </div>
</div>
