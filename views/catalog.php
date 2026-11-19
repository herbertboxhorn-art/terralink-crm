<div class="pagehead">
  <div><h1>Price list</h1><div class="sub">Your standard items and rates. Pick them on quotes instead of typing prices.</div></div>
  <?php if (is_role('admin', 'sales')): ?><div class="actions"><a class="btn primary" href="/catalog/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New item</a></div><?php endif; ?>
</div>
<div class="filters">
  <div class="seg"><?php foreach (['active' => 'Active', 'all' => 'All'] as $k => $l): ?><a href="?f=<?= $k ?>&amp;q=<?= h(rawurlencode($qs)) ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a><?php endforeach; ?></div>
  <form method="get" class="actions"><input type="hidden" name="f" value="<?= h($f) ?>"><input type="search" name="q" value="<?= h($qs) ?>" placeholder="Search name or SKU" style="min-height:40px;border:1px solid var(--line);border-radius:8px;padding:0 12px;background:var(--input)"></form>
</div>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><?= $qs !== '' ? 'Nothing matches.' : 'No items yet. Add the things you quote often: access points, Starlink kits, labour per hour, travel, monthly support.' ?></div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Item</th><th>Type</th><th class="num">Price</th><th class="num">Cost</th><th>Stock</th><th class="num">On quotes</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $margin = ($r['cost'] !== null && (float)$r['price'] > 0) ? round(((float)$r['price'] - (float)$r['cost']) / (float)$r['price'] * 100) : null; ?>
      <tr<?= $r['active'] ? '' : ' class="muted"' ?>>
        <td><a class="row" href="/catalog/<?= (int)$r['id'] ?>/edit"><?= h($r['name']) ?></a><?= $r['sku'] ? ' <span class="mono muted">' . h($r['sku']) . '</span>' : '' ?><?= $r['active'] ? '' : ' <span class="pill">Hidden</span>' ?></td>
        <td class="pillcell"><span class="pill"><?= h(ITEM_KINDS[$r['kind']]) ?></span></td>
        <td class="num" data-l="Price"><b><?= h(money($r['price'])) ?></b><?= $r['unit'] ? ' / ' . h($r['unit']) : '' ?><?= $r['pst_applies'] ? ' <small class="muted">+PST</small>' : '' ?></td>
        <td class="num" data-l="Cost"><?= $r['cost'] !== null ? h(money($r['cost'])) . ($margin !== null ? ' <small class="muted">' . $margin . '%</small>' : '') : '—' ?></td>
        <td data-l="Stock"><?php if ($r['free'] === null): ?>—<?php else: ?><span class="sla <?= $r['free'] > 0 ? 'sla-ok' : 'sla-over' ?>"><?= h(qty_fmt($r['free'])) ?> free</span><?php endif; ?></td>
        <td class="num" data-l="On quotes"><?= (int)$r['used'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
