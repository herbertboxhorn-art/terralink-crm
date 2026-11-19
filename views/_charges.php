<?php
/** Charges card on a ticket. Needs $t, $charges, $ctot, $catGroups, $labourItems */
$id = (int)$t['id'];
$locked = (bool)$t['billed_at'];
$noCharge = $t['billing'] === 'no_charge';
$unbilled = ticket_unbilled_minutes($t);
$canBill = is_role('admin', 'sales');
?>
<div class="card" id="charges">
  <div class="cardhead"><h2>Charges</h2>
    <div class="actions">
      <?php if ($locked): ?><span class="pill closed">Billed <?= h(fdate($t['billed_at'], 'M j, Y')) ?></span>
      <?php elseif ($noCharge): ?><span class="pill">No charge</span>
      <?php else: ?><span class="pill open">Billable</span><?php endif; ?>
      <a class="btn sm" href="/tickets/<?= $id ?>/summary" target="_blank">Work summary PDF</a>
    </div>
  </div>

  <?php if ($charges): ?>
  <div class="tablewrap"><table class="t">
    <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Line</th><th></th></tr></thead>
    <tbody><?php foreach ($charges as $c): $line = round((float)$c['qty'] * (float)$c['unit_price'], 2); ?>
      <tr>
        <td><?= h($c['description']) ?><?= $c['pst_applies'] ? ' <small class="muted">+PST</small>' : '' ?>
          <?php if ($c['minutes'] || $c['consumable_id'] || $c['equipment_ids']): ?><br><small class="muted"><?= $c['minutes'] ? h(dur((int)$c['minutes'])) . ' logged' : 'From stock' ?></small><?php endif; ?></td>
        <td class="num"><?= h(qty_fmt($c['qty'])) ?><?= $c['unit'] ? ' ' . h($c['unit']) : '' ?></td>
        <td class="num"><?= h(money($c['unit_price'])) ?></td>
        <td class="num"><b><?= h(money($line)) ?></b></td>
        <td class="num"><?php if (!$locked && ((int)$c['created_by'] === (int)uid() || $canBill)): ?>
          <form method="post" action="/tickets/<?= $id ?>/charges/<?= (int)$c['id'] ?>/delete" data-confirm="Remove this charge?<?= $c['consumable_id'] || $c['equipment_ids'] ? ' Its stock goes back into inventory.' : '' ?>"><?= csrf_field() ?><button class="btn sm" type="submit" aria-label="Remove charge">×</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <dl class="kv" style="max-width:320px;margin:10px 0 0 auto">
    <dt>Subtotal</dt><dd class="num"><?= h(money($ctot['subtotal'])) ?></dd>
    <dt>GST <?= qty_fmt(CHARGE_GST) ?>%</dt><dd class="num"><?= h(money($ctot['gst'])) ?></dd>
    <?php if ($ctot['pst'] > 0): ?><dt>PST <?= qty_fmt(CHARGE_PST) ?>%</dt><dd class="num"><?= h(money($ctot['pst'])) ?></dd><?php endif; ?>
    <dt><b>Total</b></dt><dd class="num"><b><?= h(money($ctot['total'])) ?></b></dd>
  </dl>
  <?php if ($noCharge): ?><p class="muted" style="margin:8px 0 0;font-size:13px">No charge: these lines are recorded but won't be billed<?= $t['plan_id'] ? ' (covered by the service plan)' : '' ?>.</p><?php endif; ?>
  <?php else: ?>
  <p class="muted" style="margin:6px 0 0">No charges yet.<?= $noCharge ? ' This ticket is set to no charge.' : '' ?></p>
  <?php endif; ?>

  <?php if (!$locked): ?>
    <?php if ($unbilled > 0 && $labourItems): ?>
    <form method="post" action="/tickets/<?= $id ?>/charges/time" class="actions" style="margin-top:14px;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 12px;background:var(--tint, #f4f6f8);border-radius:8px"><?= csrf_field() ?>
      <span><b><?= h(dur($unbilled)) ?></b> logged, not billed yet → bill as <b><?= qty_fmt(minutes_to_hours($unbilled)) ?> h</b> of</span>
      <select name="catalog_id" aria-label="Labour rate" style="min-height:40px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <?php foreach ($labourItems as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $l['sku'] === 'LAB-FIELD' ? ' selected' : '' ?>><?= h($l['name']) ?> — <?= h(money($l['price'])) ?>/h</option><?php endforeach; ?>
      </select>
      <button class="btn sm primary" type="submit">Add labour</button>
    </form>
    <?php endif; ?>

    <form method="post" action="/tickets/<?= $id ?>/charges" class="chargeform" style="margin-top:14px"><?= csrf_field() ?>
      <div class="actions" style="flex-wrap:wrap;gap:8px;align-items:center">
        <select name="catalog_id" aria-label="Price list item" style="min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input);flex:1 1 260px;min-width:0">
          <option value="">Add from price list…</option>
          <?php foreach ($catGroups as $g => $items): ?><optgroup label="<?= h($g) ?>">
            <?php foreach ($items as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?> — <?= h(money($c['price'])) ?><?= $c['unit'] ? '/' . h($c['unit']) : '' ?><?= $c['free'] !== null ? ' (' . qty_fmt($c['free']) . ' in stock)' : '' ?></option><?php endforeach; ?>
          </optgroup><?php endforeach; ?>
        </select>
        <input name="qty" type="number" step="0.01" min="0.01" value="1" inputmode="decimal" aria-label="Quantity" title="Quantity (km for mileage, hours for labour)" style="width:90px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <input name="unit_price" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Price (optional)" aria-label="Unit price override" style="width:140px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px" title="For stocked items: take consumables off inventory, or install equipment units at this ticket's site"><input type="checkbox" name="take_stock" value="1" checked> Take from stock</label>
        <button class="btn primary" type="submit">Add charge</button>
      </div>
      <p class="muted" style="margin:6px 0 0;font-size:13px">Quantity is km for mileage and hours for labour. Leave the price empty to use the price list.</p>
    </form>

    <details style="margin-top:10px"><summary style="cursor:pointer;font-size:14px">Custom line (not on the price list)</summary>
      <form method="post" action="/tickets/<?= $id ?>/charges" class="actions" style="margin-top:8px;flex-wrap:wrap;gap:8px;align-items:center"><?= csrf_field() ?>
        <select name="kind" aria-label="Type" style="min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)"><?php foreach (ITEM_KINDS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'hardware' ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
        <input name="description" placeholder="Description" required style="flex:1 1 220px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <input name="qty" type="number" step="0.01" min="0.01" value="1" aria-label="Quantity" style="width:80px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <input name="unit" placeholder="Unit" aria-label="Unit" style="width:70px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <input name="unit_price" type="number" step="0.01" min="0" required placeholder="Unit price" aria-label="Unit price" style="width:120px;min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 8px;background:var(--input)">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px"><input type="checkbox" name="pst" value="1" checked> PST</label>
        <button class="btn" type="submit">Add line</button>
      </form>
    </details>
  <?php endif; ?>

  <div class="actions" style="margin-top:14px;flex-wrap:wrap;gap:8px;border-top:1px solid var(--line);padding-top:12px">
    <?php if (!$locked): ?>
      <form method="post" action="/tickets/<?= $id ?>/billing"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $noCharge ? 'billable' : 'no_charge' ?>">
        <button class="btn sm" type="submit"><?= $noCharge ? 'Make billable' : 'Set to no charge' ?></button></form>
      <?php if ($canBill && !$noCharge && $charges): ?>
      <form method="post" action="/tickets/<?= $id ?>/billing" data-confirm="Mark this ticket as billed? Its charges will be locked."><?= csrf_field() ?><input type="hidden" name="do" value="billed">
        <button class="btn sm dark" type="submit">Mark billed</button></form>
      <?php endif; ?>
    <?php elseif (is_role('admin') && !$t['invoice_id']): ?>
      <form method="post" action="/tickets/<?= $id ?>/billing" data-confirm="Reopen billing so the charges can be changed?"><?= csrf_field() ?><input type="hidden" name="do" value="reopen">
        <button class="btn sm" type="submit">Reopen billing</button></form>
    <?php endif; ?>
  </div>
</div>
