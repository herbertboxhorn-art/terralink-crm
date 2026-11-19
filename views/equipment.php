<?php $qs = fn(array $x) => '?' . h(http_build_query(array_filter($x + ['tab' => $tab], fn($v) => $v !== '' && $v !== null))); ?>
<div class="pagehead">
  <div><h1>Inventory</h1><div class="sub">Installed gear, stock on the shelf, and consumables</div></div>
  <div class="actions">
    <?php if ($tab === 'consumables'): ?>
      <a class="btn primary" href="/consumables/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New consumable</a>
    <?php else: ?>
      <a class="btn primary" href="/equipment/receive"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Receive stock</a>
      <a class="btn" href="/equipment/new">+ Single item</a>
    <?php endif; ?>
  </div>
</div>

<nav class="tabs">
  <a href="/equipment?tab=installed"<?= $tab === 'installed' ? ' class="on"' : '' ?>>Installed <span><?= $counts['installed'] ?></span></a>
  <a href="/equipment?tab=stock"<?= $tab === 'stock' ? ' class="on"' : '' ?>>In stock <span><?= $counts['stock'] ?></span></a>
  <a href="/equipment?tab=consumables"<?= $tab === 'consumables' ? ' class="on"' : '' ?>>Consumables <span><?= $counts['consumables'] ?></span><?php if ($counts['low']): ?> <span style="background:var(--orange-t);color:#a64a00">⚠ <?= $counts['low'] ?> low</span><?php endif; ?></a>
</nav>

<form class="filters" method="get">
  <input type="hidden" name="tab" value="<?= h($tab) ?>">
  <input type="search" name="q" value="<?= h($term) ?>" placeholder="<?= $tab === 'consumables' ? 'Name, SKU, supplier…' : 'Serial, MAC, IP, model' . ($tab === 'installed' ? ', client' : ', location') . '…' ?>">
  <?php if ($tab !== 'consumables'): ?>
  <select name="category" onchange="this.form.submit()"><option value="">All categories</option>
    <?php foreach (EQUIP_CATEGORIES as $c): ?><option<?= $cat === $c ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <div class="seg">
    <?php if ($tab === 'installed'): ?>
      <a href="<?= $qs(['q' => $term, 'category' => $cat]) ?>"<?= $f === '' ? ' class="on"' : '' ?>>All</a>
      <a href="<?= $qs(['q' => $term, 'category' => $cat, 'f' => 'warranty']) ?>"<?= $f === 'warranty' ? ' class="on"' : '' ?>>Warranty ≤ 90 d</a>
      <a href="<?= $qs(['q' => $term, 'category' => $cat, 'f' => 'faulty']) ?>"<?= $f === 'faulty' ? ' class="on"' : '' ?>>Faulty</a>
    <?php elseif ($tab === 'stock'): ?>
      <a href="<?= $qs(['q' => $term, 'category' => $cat]) ?>"<?= $f === '' ? ' class="on"' : '' ?>>Ready to install</a>
      <a href="<?= $qs(['q' => $term, 'category' => $cat, 'f' => 'all']) ?>"<?= $f === 'all' ? ' class="on"' : '' ?>>Incl. faulty / retired</a>
    <?php else: ?>
      <a href="<?= $qs(['q' => $term]) ?>"<?= $f === '' ? ' class="on"' : '' ?>>All</a>
      <a href="<?= $qs(['q' => $term, 'f' => 'low']) ?>"<?= $f === 'low' ? ' class="on"' : '' ?>>Low stock</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($tab === 'stock' && !empty($summary)): ?>
<div class="stats" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr))">
  <?php foreach ($summary as $s): ?>
  <a class="stat" href="<?= $qs(['category' => $s['category']]) ?>"><small><?= h($s['category']) ?></small><b><?= (int)$s['n'] ?></b><span><?= $s['v'] > 0 ? h(money($s['v'])) . ' value' : 'in stock' ?></span></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'consumables'): ?>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No consumables yet. <a href="/consumables/new">Add your first one</a>: cable, connectors, mounts…</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Item</th><th>Category</th><th class="num">On hand</th><th class="num">Re-order at</th><th>Location</th><th class="num">Value</th></tr></thead>
    <tbody><?php foreach ($rows as $c): $low = $c['reorder_level'] !== null && (float)$c['qty_on_hand'] <= (float)$c['reorder_level']; ?>
      <tr>
        <td><a class="row" href="/consumables/<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a><?= $c['sku'] ? '<br><span class="muted mono">' . h($c['sku']) . '</span>' : '' ?></td>
        <td><?= h($c['category']) ?></td>
        <td class="num" data-l="On hand"><b<?= $low ? ' style="color:var(--bad)"' : '' ?>><?= h(qty_fmt($c['qty_on_hand'])) ?></b> <?= h($c['unit']) ?><?= $low ? ' <span class="pill urgent">low</span>' : '' ?></td>
        <td class="num" data-l="Re-order at"><?= $c['reorder_level'] !== null ? h(qty_fmt($c['reorder_level'])) : '—' ?></td>
        <td data-l="Location"><?= h($c['location']) ?></td>
        <td class="num" data-l="Value"><?= $c['unit_cost'] !== null ? h(money($c['qty_on_hand'] * $c['unit_cost'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if ($value > 0): ?><p class="muted" style="margin:12px 0 0;text-align:right">Total consumables value: <b><?= h(money($value)) ?></b></p><?php endif; ?>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'stock'): ?>
<div class="card">
  <?php if (!$rows): ?><div class="empty">Nothing in stock. Use <a href="/equipment/receive">Receive stock</a> when gear arrives.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Device</th><th>Serial / MAC</th><th>Location</th><th>Purchased</th><th class="num">Cost</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $e): ?>
      <tr>
        <td><b><?= h($e['category']) ?></b> <span class="muted"><?= h(trim($e['make'] . ' ' . $e['model'])) ?></span></td>
        <td class="mono" data-l="S/N"><?= h($e['serial'] ?: '—') ?><?= $e['mac'] ? '<br>' . h($e['mac']) : '' ?></td>
        <td data-l="Location"><?= h($e['stock_location'] ?: '—') ?></td>
        <td data-l="Purchased"><?= h(fdate($e['purchased_at'], 'M j, Y')) ?><?= $e['supplier'] ? '<br><span class="muted">' . h($e['supplier']) . '</span>' : '' ?></td>
        <td class="num" data-l="Cost"><?= $e['cost'] !== null ? h(money($e['cost'])) : '—' ?></td>
        <td><span class="pill <?= h($e['status']) ?>"><?= h(EQUIP_STATUS[$e['status']]) ?></span></td>
        <td class="num"><?php if ($e['status'] === 'in_stock'): ?><a class="btn sm dark" href="/equipment/<?= (int)$e['id'] ?>/install">Install →</a> <?php endif; ?><a href="/equipment/<?= (int)$e['id'] ?>/edit">Edit</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No installed equipment found. Gear is added from a client's site page, or installed from stock.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Client / site</th><th>Device</th><th>Serial</th><th>MAC / IP</th><th>Warranty</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $e): ?>
      <tr>
        <td><a class="row" href="/sites/<?= (int)$e['site_id'] ?>"><?= h($e['company']) ?></a><br><span class="muted"><?= h($e['site']) ?></span></td>
        <td><b><?= h($e['category']) ?></b> <span class="muted"><?= h(trim($e['make'] . ' ' . $e['model'])) ?></span></td>
        <td class="mono" data-l="S/N"><?= h($e['serial']) ?></td>
        <td class="mono"><?= h($e['mac']) ?><?= $e['mac'] && $e['ip_address'] ? '<br>' : '' ?><?= h($e['ip_address']) ?><?= $e['phone_number'] ? h($e['phone_number']) : '' ?></td>
        <td data-l="Warranty"><?= h(fdate($e['warranty_until'], 'M j, Y')) ?></td>
        <td><span class="pill <?= h($e['status']) ?>"><?= h(EQUIP_STATUS[$e['status']]) ?></span></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
<?php endif; ?>
