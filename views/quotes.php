<div class="pagehead">
  <div><h1>Quotes</h1><div class="sub"><?= count($rows) ?> shown · amounts before tax</div></div>
  <div class="actions"><a class="btn primary" href="/quotes/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New quote</a></div>
</div>
<div class="filters"><div class="seg">
  <a href="/quotes"<?= $f === '' ? ' class="on"' : '' ?>>All</a>
  <?php foreach (QUOTE_STATUS as $k => $l): ?><a href="?f=<?= $k ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= $l ?></a><?php endforeach; ?>
</div></div>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No quotes yet.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>#</th><th>Title</th><th>Client</th><th>Issued</th><th>Status</th><th class="num">Subtotal</th></tr></thead>
    <tbody><?php foreach ($rows as $qt): ?>
      <tr>
        <td class="mono"><?= h($qt['number']) ?></td>
        <td><a class="row" href="/quotes/<?= (int)$qt['id'] ?>"><?= h($qt['title']) ?></a></td>
        <td><a href="/companies/<?= (int)$qt['company_id'] ?>"><?= h($qt['company']) ?></a></td>
        <td data-l="Issued"><?= h(fdate($qt['issued_on'])) ?></td>
        <td><span class="pill <?= h($qt['status']) ?>"><?= h(QUOTE_STATUS[$qt['status']]) ?></span></td>
        <td class="num" data-l="Subtotal"><b><?= h(money($qt['subtotal'])) ?></b></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
