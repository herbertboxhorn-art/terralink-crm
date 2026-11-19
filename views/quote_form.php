<?php
$itemsJson = json_encode(array_map(fn($i) => [
  'kind' => $i['kind'], 'description' => $i['description'], 'qty' => (string)(float)$i['qty'],
  'unit' => $i['unit'] ?? '', 'unit_price' => number_format((float)$i['unit_price'], 2, '.', ''), 'pst_applies' => (int)$i['pst_applies'],
  'catalog_id' => $i['catalog_id'] ?? ''], $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<div class="pagehead"><div><?php if ($id): ?><a class="crumb" href="/quotes/<?= $id ?>">← <?= h($row['number']) ?></a><?php endif; ?><h1><?= h($title) ?></h1></div></div>
<form method="post" data-dependent novalidate>
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="flash bad"><?= h($errors['_items'] ?? 'Please fix the highlighted fields.') ?></div><?php endif; ?>
  <div class="card" style="margin-bottom:18px"><div class="form"><?= render_fields($def, $row, $errors) ?></div></div>

  <div class="card" id="items" data-catalog='<?= json_encode(catalog_for_picker((int)($id ?? 0)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>' data-items='<?= $itemsJson ?>' data-travel-rate="<?= h(number_format($travelRate, 2, '.', '')) ?>">
    <div class="cardhead"><h2>Line items</h2>
      <div class="actions"><button type="button" class="btn sm" id="add-travel">+ Travel (km)</button><button type="button" class="btn sm dark" id="add-item">+ Line</button></div></div>
    <div class="picker">
      <input type="search" id="cat-pick" list="cat-list" placeholder="Add from price list — type a name or SKU…" autocomplete="off" aria-label="Add from price list">
      <datalist id="cat-list"></datalist>
      <a class="muted" href="/catalog" target="_blank" style="font-size:13px;white-space:nowrap">Edit price list</a>
    </div>
    <table class="items">
      <thead><tr><th class="c-kind">Type</th><th class="c-desc">Description</th><th class="c-qty">Qty</th><th class="c-unit">Unit</th><th class="c-price">Unit price</th><th class="c-pst" title="Charge PST on this line">PST</th><th class="c-line">Line</th><th class="c-x"></th></tr></thead>
      <tbody></tbody>
    </table>
    <template id="item-tpl"><tr>
      <td class="c-kind"><select name="items[kind][]" aria-label="Type"><?php foreach (ITEM_KINDS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></td>
      <td class="c-desc"><input name="items[description][]" placeholder="Description" aria-label="Description"><input type="hidden" name="items[catalog_id][]" value=""><small class="stockhint"></small></td>
      <td class="c-qty"><input name="items[qty][]" type="number" step="0.01" inputmode="decimal" value="1" aria-label="Quantity"></td>
      <td class="c-unit"><input name="items[unit][]" placeholder="ea / h / km" aria-label="Unit"></td>
      <td class="c-price"><input name="items[unit_price][]" type="number" step="0.01" inputmode="decimal" placeholder="0.00" aria-label="Unit price"></td>
      <td class="c-pst"><input type="checkbox" class="pstbox" aria-label="PST applies"><input type="hidden" name="items[pst][]" value="0"></td>
      <td class="c-line">$0.00</td>
      <td class="c-x"><button type="button" class="btn sm rm" aria-label="Remove line">✕</button></td>
    </tr></template>
    <div class="totals" style="margin-top:14px">
      <div><span>Subtotal</span><span id="t-sub">$0.00</span></div>
      <div><span>GST</span><span id="t-gst">$0.00</span></div>
      <div><span>PST</span><span id="t-pst">$0.00</span></div>
      <div class="grand"><span>Total</span><span id="t-total">$0.00</span></div>
    </div>
    <p class="muted" style="font-size:13px;margin:10px 0 0">PST is ticked by default on hardware and software lines. Check each line against your own tax rules.</p>
    <div class="formbar">
      <button class="btn primary" type="submit">Save quote</button>
      <a class="btn" href="<?= $id ? "/quotes/$id" : '/quotes' ?>">Cancel</a>
    </div>
  </div>
</form>
