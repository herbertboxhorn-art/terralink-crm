<div class="pagehead">
  <div><a class="crumb" href="/sites/<?= (int)$s['id'] ?>">← <?= h($s['name']) ?></a><h1>Add equipment</h1><div class="sub"><?= h($s['company']) ?> · <?= h($s['name']) ?></div></div>
  <div class="actions"><a class="btn" href="/equipment/new?site_id=<?= (int)$s['id'] ?>">+ New item (not from stock)</a></div>
</div>
<div class="card">
  <div class="cardhead"><h2>From stock</h2>
    <form method="get" class="filters" style="margin:0"><select name="category" onchange="this.form.submit()"><option value="">All categories</option>
      <?php foreach (EQUIP_CATEGORIES as $c): ?><option<?= $cat === $c ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select></form></div>
  <?php if (!$stock): ?>
    <div class="empty">Nothing<?= $cat ? ' in ' . h($cat) : '' ?> in stock. <a href="/equipment/new?site_id=<?= (int)$s['id'] ?>">Add a new item directly</a> or <a href="/equipment/receive">receive stock</a> first.</div>
  <?php else: ?>
  <form method="post"><?= csrf_field() ?>
    <ul class="list">
      <?php foreach ($stock as $e): ?>
      <li><label style="display:flex;gap:12px;align-items:center;width:100%;cursor:pointer">
        <input type="checkbox" name="ids[]" value="<?= (int)$e['id'] ?>" style="width:22px;height:22px;accent-color:var(--orange)">
        <span class="grow"><b><?= h(trim($e['category'] . ' ' . $e['make'] . ' ' . $e['model'])) ?></b>
          <span class="meta mono"><?= $e['serial'] ? 'S/N ' . h($e['serial']) : 'no serial' ?><?= $e['mac'] ? ' · ' . h($e['mac']) : '' ?><?= $e['stock_location'] ? ' · ' . h($e['stock_location']) : '' ?></span></span>
      </label></li>
      <?php endforeach; ?>
    </ul>
    <div class="formbar">
      <label class="muted" style="display:flex;gap:8px;align-items:center">Installed on <input type="date" name="installed_at" value="<?= date('Y-m-d') ?>" style="min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 10px"></label>
      <span class="spacer"></span>
      <button class="btn primary" type="submit">Install selected</button>
    </div>
  </form>
  <?php endif; ?>
</div>
