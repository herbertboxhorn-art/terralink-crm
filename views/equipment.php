<div class="pagehead">
  <div><h1>Equipment</h1><div class="sub">All installed gear across client sites · <?= count($rows) ?> shown</div></div>
</div>
<form class="filters" method="get">
  <input type="search" name="q" value="<?= h($term) ?>" placeholder="Serial, MAC, IP, model, client…">
  <select name="category" onchange="this.form.submit()"><option value="">All categories</option>
    <?php foreach (EQUIP_CATEGORIES as $c): ?><option<?= $cat === $c ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select>
  <div class="seg">
    <a href="?<?= h(http_build_query(['q' => $term, 'category' => $cat])) ?>"<?= $f === '' ? ' class="on"' : '' ?>>All</a>
    <a href="?<?= h(http_build_query(['q' => $term, 'category' => $cat, 'f' => 'warranty'])) ?>"<?= $f === 'warranty' ? ' class="on"' : '' ?>>Warranty ≤ 90 d</a>
    <a href="?<?= h(http_build_query(['q' => $term, 'category' => $cat, 'f' => 'faulty'])) ?>"<?= $f === 'faulty' ? ' class="on"' : '' ?>>Faulty</a>
  </div>
  <input type="hidden" name="f" value="<?= h($f) ?>">
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No equipment found. Add gear from a client's site page.</div><?php else: ?>
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
