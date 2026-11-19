<div class="pagehead">
  <div><h1>Companies</h1><div class="sub"><?= count($rows) ?> shown</div></div>
  <div class="actions"><a class="btn primary" href="/companies/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New company</a></div>
</div>
<form class="filters" method="get">
  <input type="search" name="q" value="<?= h($term) ?>" placeholder="Name or town…">
  <select name="industry" onchange="this.form.submit()">
    <option value="">All industries</option>
    <?php foreach (INDUSTRIES as $i): ?><option<?= $ind === $i ? ' selected' : '' ?>><?= h($i) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No companies yet. <a href="/companies/new">Add the first one</a>.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Company</th><th>Industry</th><th>Town</th><th>Phone</th><th class="num">Open deals</th><th class="num">Open tickets</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a class="row" href="/companies/<?= (int)$r['id'] ?>"><?= h($r['name']) ?></a></td>
        <td><span class="pill ind"><?= h($r['industry']) ?></span></td>
        <td data-l="Town"><?= h($r['town']) ?></td>
        <td><?= tel($r['phone']) ?></td>
        <td class="num" data-l="Open deals"><?= (int)$r['open_deals'] ?: '—' ?></td>
        <td class="num" data-l="Open tickets"><?= (int)$r['open_tickets'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
