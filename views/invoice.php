<?php $id = (int)$inv['id']; $draft = $inv['status'] === 'draft'; $od = inv_overdue($inv); ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/invoices<?= $draft ? '' : '?f=all' ?>">← Invoices</a>
    <h1><?= h(inv_label($inv)) ?> · <?= h($inv['company']) ?></h1>
    <div class="sub"><span class="pill<?= $od ? ' urgent' : ($inv['status'] === 'paid' ? ' closed' : ($draft ? ' waiting' : ' open')) ?>"><?= $od ? 'Overdue' : h(INVOICE_STATUS[$inv['status']]) ?></span>
      <?php if ($inv['period']): ?>For <?= h(inv_month_label($inv['period'])) ?><?php endif; ?>
      <?php if ($inv['issued_on']): ?> · Issued <?= h(fdate($inv['issued_on'])) ?> · Due <?= h(fdate($inv['due_on'])) ?><?php endif; ?>
      <?php if ($inv['sent_at']): ?> · Emailed to <?= h($inv['sent_to']) ?> <?= h(ago($inv['sent_at'])) ?><?php endif; ?>
      <?php if ($inv['paid_on']): ?> · Paid <?= h(fdate($inv['paid_on'])) ?> by <?= h(PAY_METHODS[$inv['paid_method']] ?? $inv['paid_method']) ?><?php endif; ?></div>
  </div>
  <div class="actions">
    <a class="btn" href="/invoices/<?= $id ?>/pdf" target="_blank">PDF</a>
    <?php if ($draft || $inv['status'] === 'approved'): ?>
      <form method="post" action="/invoices/<?= $id ?>/approve" data-confirm="<?= $draft ? 'Approve this invoice and email it to ' . h($inv['bill_to_email'] ?: '(no email set)') . '?' : 'Send it to ' . h($inv['bill_to_email']) . ' again?' ?>"><?= csrf_field() ?>
        <button class="btn primary" type="submit"><?= $draft ? 'Approve &amp; email' : 'Send' ?></button></form>
    <?php endif; ?>
  </div>
</div>

<div class="grid side">
  <div class="stack">
    <div class="card" id="lines">
      <h2>Lines</h2>
      <?php if (!$groups): ?><div class="empty">No lines.</div><?php endif; ?>
      <?php foreach ($groups as $g): ?>
        <div style="margin-top:14px">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline">
            <b><?php if ($g['ticket_id']): ?><a href="/tickets/<?= (int)$g['ticket_id'] ?>#charges"><?= h($g['title']) ?></a><?php else: ?><?= h($g['title']) ?><?php endif; ?></b>
            <?php if ($draft && $g['ticket_id']): ?><form method="post" action="/invoices/<?= $id ?>/lines/<?= (int)$g['items'][0]['id'] ?>/delete" data-confirm="Take this whole ticket off the invoice? It goes back to Ready to bill so you can fix it."><?= csrf_field() ?><button class="btn sm" type="submit">Take ticket off</button></form><?php endif; ?>
          </div>
          <?php if ($g['sub']): ?><small class="muted"><?= h($g['sub']) ?></small><?php endif; ?>
          <div class="tablewrap"><table class="t">
            <tbody><?php foreach ($g['items'] as $it): ?>
              <tr>
                <td><?= h($it['description']) ?><?= $it['pst_applies'] ? ' <small class="muted">+PST</small>' : '' ?><?= $it['auto_added'] ? ' <span class="pill" style="background:#fff1e6;color:#b45309" title="Logged time that nobody billed; added at month end">auto-billed time</span>' : '' ?></td>
                <td class="num" style="white-space:nowrap"><?= h(qty_fmt($it['qty'])) ?><?= $it['unit'] ? ' ' . h($it['unit']) : '' ?> × <?= h(money($it['unit_price'])) ?></td>
                <td class="num" style="width:110px"><b><?= h(money(round((float)$it['qty'] * (float)$it['unit_price'], 2))) ?></b></td>
                <td class="num" style="width:44px"><?php if ($draft && !$it['ticket_id']): ?><form method="post" action="/invoices/<?= $id ?>/lines/<?= (int)$it['id'] ?>/delete" data-confirm="Remove this line?"><?= csrf_field() ?><button class="btn sm" type="submit" aria-label="Remove line">×</button></form><?php endif; ?></td>
              </tr>
            <?php endforeach; ?></tbody>
          </table></div>
        </div>
      <?php endforeach; ?>
      <dl class="kv" style="max-width:320px;margin:16px 0 0 auto">
        <dt>Subtotal</dt><dd class="num"><?= h(money($tot['subtotal'])) ?></dd>
        <dt>GST <?= qty_fmt($inv['gst_rate']) ?>%</dt><dd class="num"><?= h(money($tot['gst'])) ?></dd>
        <?php if ($tot['pst'] > 0): ?><dt>PST <?= qty_fmt($inv['pst_rate']) ?>%</dt><dd class="num"><?= h(money($tot['pst'])) ?></dd><?php endif; ?>
        <dt><b>Total</b></dt><dd class="num"><b><?= h(money($tot['total'])) ?></b></dd>
      </dl>
      <?php if ($draft): ?>
      <details style="margin-top:12px"><summary style="cursor:pointer;font-size:14px">Add a line</summary>
        <form method="post" action="/invoices/<?= $id ?>/lines" class="actions" style="margin-top:8px;flex-wrap:wrap;gap:8px;align-items:center"><?= csrf_field() ?>
          <select name="kind" aria-label="Type" style="min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)"><?php foreach (ITEM_KINDS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'other' ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
          <input name="description" placeholder="Description" required style="flex:1 1 220px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
          <input name="qty" type="number" step="0.01" value="1" aria-label="Quantity" style="width:80px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
          <input name="unit" placeholder="Unit" aria-label="Unit" style="width:70px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
          <input name="unit_price" type="number" step="0.01" required placeholder="Unit price" aria-label="Unit price" title="Use a negative price for a discount or credit" style="width:120px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
          <label style="display:flex;align-items:center;gap:6px;font-size:14px"><input type="checkbox" name="pst" value="1"> PST</label>
          <button class="btn" type="submit">Add line</button>
        </form>
        <p class="muted" style="margin:6px 0 0;font-size:13px">A negative price makes a discount or credit line.</p>
      </details>
      <?php endif; ?>
    </div>
    <?php $etype = 'invoice'; $eid = $id; $noForm = true; require __DIR__ . '/_timeline.php'; ?>
  </div>

  <div class="stack">
    <div class="card"><h2>Bill to</h2>
      <?php if ($draft): ?>
      <form method="post" action="/invoices/<?= $id ?>/edit" class="form" style="grid-template-columns:1fr"><?= csrf_field() ?>
        <div class="field"><label for="i_to">Billing email</label><input id="i_to" type="email" name="bill_to_email" value="<?= h($inv['bill_to_email']) ?>" placeholder="Where the invoice is emailed"><?= $inv['bill_to_email'] ? '' : '<div class="err">Needed before you can approve</div>' ?></div>
        <div class="field"><label for="i_terms">Payment terms (days)</label><input id="i_terms" type="number" min="0" max="120" name="terms_days" value="<?= (int)$inv['terms_days'] ?>"></div>
        <div class="field"><label for="i_notes">Note on the invoice (optional)</label><textarea id="i_notes" name="notes" rows="3"><?= h($inv['notes']) ?></textarea></div>
        <button class="btn" type="submit">Save</button>
      </form>
      <p class="muted" style="margin:10px 0 0;font-size:13px">Defaults come from the company (billing email, payment terms). <a href="/companies/<?= (int)$inv['company_id'] ?>/edit">Edit company</a></p>
      <?php else: ?>
      <dl class="kv"><dt>Email</dt><dd><?= h($inv['bill_to_email'] ?: '—') ?></dd><dt>Terms</dt><dd>Net <?= (int)$inv['terms_days'] ?></dd>
        <?php if ($inv['approver']): ?><dt>Approved by</dt><dd><?= h($inv['approver']) ?>, <?= h(fdate($inv['approved_at'], 'M j, Y')) ?></dd><?php endif; ?></dl>
      <?php endif; ?>
    </div>

    <?php if (in_array($inv['status'], ['approved', 'sent'], true)): ?>
    <div class="card"><h2>Payment</h2>
      <form method="post" action="/invoices/<?= $id ?>/paid" class="form" style="grid-template-columns:1fr"><?= csrf_field() ?>
        <div class="field"><label for="p_on">Paid on</label><input id="p_on" type="date" name="paid_on" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label for="p_m">Paid by</label><select id="p_m" name="paid_method"><?php foreach (PAY_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= h($l) ?></option><?php endforeach; ?></select></div>
        <button class="btn primary" type="submit">Mark paid</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if (is_role('admin') && $inv['status'] !== 'paid' && $inv['status'] !== 'void'): ?>
    <form method="post" action="/invoices/<?= $id ?>/void" data-confirm="<?= $draft ? 'Delete this draft? Its tickets go back to Ready to bill.' : 'Void this invoice? Its tickets go back to Ready to bill. The number stays used.' ?>" style="text-align:right"><?= csrf_field() ?>
      <button class="btn danger sm" type="submit"><?= $draft ? 'Delete draft' : 'Void invoice' ?></button></form>
    <?php endif; ?>
  </div>
</div>
