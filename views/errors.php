<div class="pagehead">
  <div><h1>Error log</h1><div class="sub">Problems the app hit on the server (CRM, client portal, n8n calls). The same error on the same line is counted, not repeated.</div></div>
  <?php if ($rows): ?><div class="actions"><form method="post" action="/errors/clear" data-confirm="Clear the whole error log?"><?= csrf_field() ?><button class="btn" type="submit">Clear all</button></form></div><?php endif; ?>
</div>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No errors logged. 👍</div><?php else: ?>
  <ul class="list"><?php foreach ($rows as $e): ?>
    <li style="align-items:flex-start">
      <span class="pill <?= str_starts_with($e['message'], 'Warning') || str_starts_with($e['message'], 'Notice') ? 'waiting' : 'urgent' ?>"><?= (int)$e['hits'] ?>×</span>
      <div class="grow"><b style="overflow-wrap:anywhere"><?= h($e['message']) ?></b>
        <span class="meta mono"><?= h($e['file']) ?><?= $e['line'] ? ':' . (int)$e['line'] : '' ?></span>
        <span class="meta">Last <?= h(ago($e['last_at'])) ?> (first <?= h(fdate($e['first_at'], 'M j g:i a')) ?>) · <?= h($e['method']) ?> <?= h($e['url']) ?><?= $e['who'] ? ' · ' . h($e['who']) : '' ?></span>
        <?php if ($e['trace']): ?><details><summary class="muted" style="cursor:pointer;font-size:13px">Stack trace</summary><pre style="white-space:pre-wrap;font-size:12px;margin:6px 0 0"><?= h($e['trace']) ?></pre></details><?php endif; ?></div>
      <form method="post" action="/errors/clear"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn sm ghost" type="submit">Clear</button></form>
    </li>
  <?php endforeach; ?></ul><?php endif; ?>
</div>
