<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><a class="crumb" href="/equipment?tab=stock">← In stock</a><h1>Receive stock</h1>
  <div class="sub">Add several of the same model at once. One item is created per serial number.</div></div></div>
<div class="card" style="max-width:860px">
  <form method="post" class="form" novalidate><?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash bad" style="grid-column:1/-1;margin:0">Please fix the highlighted fields.</div><?php endif; ?>
    <div class="field"><label>Category <b>*</b></label><select name="category"><?php foreach (EQUIP_CATEGORIES as $c): ?><option<?= ($v['category'] ?? '') === $c ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select><?= $e('category') ?></div>
    <div class="field"><label>Make</label><input name="make" value="<?= h($v['make'] ?? '') ?>" placeholder="Ubiquiti, Starlink, Yealink…"></div>
    <div class="field"><label>Model <b>*</b></label><input name="model" value="<?= h($v['model'] ?? '') ?>" placeholder="e.g. U6 Pro, Standard Kit, T54W"><?= $e('model') ?></div>
    <div class="field"><label>Stock location</label><select name="stock_location"><?php foreach (stock_locations() as $l): ?><option<?= ($v['stock_location'] ?? '') === $l ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="field wide"><label>Serial numbers: one per line (scan or paste)</label>
      <textarea name="serials" rows="6" class="mono" placeholder="SN-0001&#10;SN-0002&#10;SN-0003"><?= h($v['serials'] ?? '') ?></textarea><?= $e('serials') ?>
      <small class="muted">No serials? Leave this empty and enter a quantity below.</small></div>
    <div class="field"><label>Quantity (only if no serials)</label><input type="number" name="qty" min="1" max="500" value="<?= h($v['qty'] ?? '1') ?>"></div>
    <div class="field"><label>Cost per item ($)</label><input type="number" step="0.01" min="0" inputmode="decimal" name="cost" value="<?= h($v['cost'] ?? '') ?>"><?= $e('cost') ?></div>
    <div class="field"><label>Supplier</label><input name="supplier" value="<?= h($v['supplier'] ?? '') ?>"></div>
    <div class="field"><label>Purchased</label><input type="date" name="purchased_at" value="<?= h($v['purchased_at'] ?? '') ?>"><?= $e('purchased_at') ?></div>
    <div class="field"><label>Warranty until</label><input type="date" name="warranty_until" value="<?= h($v['warranty_until'] ?? '') ?>"><?= $e('warranty_until') ?></div>
    <div class="field wide"><label>Notes</label><textarea name="notes" rows="2"><?= h($v['notes'] ?? '') ?></textarea></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Add to stock</button><a class="btn" href="/equipment?tab=stock">Cancel</a></div>
  </form>
</div>
