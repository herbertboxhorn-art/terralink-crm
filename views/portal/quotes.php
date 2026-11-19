<div class="pagehead"><div><h1>Quotes</h1><div class="sub">Review, download and approve quotes from <?= h(setting('company_name', 'TerraLink')) ?>.</div></div></div>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No quotes yet.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Quote</th><th>Issued</th><th>Valid until</th><th class="num">Total</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $q): $open = p_quote_open($q); $exp = $q['status'] === 'sent' && !$open; ?>
      <tr>
        <td><a class="row" href="/quotes/<?= (int)$q['id'] ?>"><?= h($q['title']) ?></a><br><span class="mono muted"><?= h($q['number']) ?></span></td>
        <td data-l="Issued"><?= h(fdate($q['issued_on'])) ?></td>
        <td data-l="Valid until"><?= h($q['valid_until'] ? fdate($q['valid_until']) : '—') ?></td>
        <td class="num" data-l="Total"><b><?= h(money($q['tot'])) ?></b></td>
        <td class="pillcell"><?php if ($open): ?><span class="pill sent">Needs your decision</span><?php elseif ($exp): ?><span class="pill expired">Expired</span><?php else: ?><span class="pill <?= h($q['status']) ?>"><?= h(QUOTE_STATUS[$q['status']]) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
