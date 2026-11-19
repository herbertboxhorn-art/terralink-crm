<?php $id = (int)$c['id']; $low = $c['reorder_level'] !== null && (float)$c['qty_on_hand'] <= (float)$c['reorder_level']; ?>
<div class="pagehead">
  <div><a class="crumb" href="/equipment?tab=consumables">← Consumables</a><h1><?= h($c['name']) ?></h1>
    <div class="sub"><span class="pill ind"><?= h($c['category']) ?></span><?php if ($c['sku']): ?><span class="mono"><?= h($c['sku']) ?></span><?php endif; ?><?php if ($c['location']): ?><span><?= h($c['location']) ?></span><?php endif; ?><?php if (!$c['active']): ?><span class="pill retired">Archived</span><?php endif; ?></div></div>
  <div class="actions"><a class="btn" href="/consumables/<?= $id ?>/edit">Edit</a></div>
</div>

<div class="stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
  <div class="stat<?= $low ? ' hot' : '' ?>"><small>On hand</small><b><?= h(qty_fmt($c['qty_on_hand'])) ?> <span style="font-size:16px"><?= h($c['unit']) ?></span></b><span><?= $low ? '⚠ at or below re-order level' : 'in stock' ?></span></div>
  <div class="stat"><small>Re-order at</small><b><?= $c['reorder_level'] !== null ? h(qty_fmt($c['reorder_level'])) : '—' ?></b><span><?= h($c['supplier'] ?: 'no supplier set') ?></span></div>
  <div class="stat"><small>Value on hand</small><b><?= $c['unit_cost'] !== null ? h(money($c['qty_on_hand'] * $c['unit_cost'])) : '—' ?></b><span><?= $c['unit_cost'] !== null ? h(money($c['unit_cost'])) . ' / ' . h($c['unit']) : 'no cost set' ?></span></div>
</div>

<div class="grid side">
  <div class="card">
    <h2>History</h2>
    <?php if (!$moves): ?><div class="empty">No movements yet.</div><?php else: ?>
    <div class="tablewrap"><table class="t cards">
      <thead><tr><th>When</th><th>What</th><th class="num">Change</th><th>Where / note</th><th>By</th></tr></thead>
      <tbody><?php foreach ($moves as $m): ?>
        <tr><td data-l="When"><?= h(fdate($m['created_at'], 'M j, Y g:i a')) ?></td>
          <td><span class="pill <?= $m['reason'] === 'received' ? 'won' : ($m['reason'] === 'used' ? 'in_progress' : 'low') ?>"><?= h(MOVE_REASONS[$m['reason']]) ?></span></td>
          <td class="num" data-l="Change"><b><?= (float)$m['qty_change'] > 0 ? '+' : '' ?><?= h(qty_fmt($m['qty_change'])) ?></b> <?= h($c['unit']) ?></td>
          <td><?php if ($m['sid']): ?><a href="/sites/<?= (int)$m['sid'] ?>"><?= h($m['company'] . ' · ' . $m['site']) ?></a><?php endif; ?><?= $m['ticket'] ? ' · ' . h($m['ticket']) : '' ?><?= $m['note'] ? ($m['sid'] ? '<br>' : '') . '<span class="muted">' . h($m['note']) . '</span>' : '' ?></td>
          <td data-l="By"><?= h($m['who'] ?? '—') ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </div>

  <div class="stack">
    <div class="card"><h2>Use at a site</h2>
      <form method="post" action="/consumables/<?= $id ?>/move" class="inline"><?= csrf_field() ?><input type="hidden" name="reason" value="used">
        <select name="site_id" aria-label="Site"><option value="">— No site (internal use) —</option><?php foreach ($sites as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['company'] . ' · ' . $s['name']) ?></option><?php endforeach; ?></select>
        <div class="row"><input type="number" step="0.01" min="0.01" inputmode="decimal" name="qty" placeholder="Qty (<?= h($c['unit']) ?>)" required><button class="btn dark" type="submit">Use</button></div>
        <input name="note" placeholder="Note (optional)">
      </form></div>
    <div class="card"><h2>Receive</h2>
      <form method="post" action="/consumables/<?= $id ?>/move" class="inline"><?= csrf_field() ?><input type="hidden" name="reason" value="received">
        <div class="row"><input type="number" step="0.01" min="0.01" inputmode="decimal" name="qty" placeholder="Qty (<?= h($c['unit']) ?>)" required><button class="btn primary" type="submit">Add</button></div>
        <input name="note" placeholder="PO / supplier / note (optional)">
      </form></div>
    <div class="card"><h2>Stock count</h2>
      <form method="post" action="/consumables/<?= $id ?>/move" class="inline"><?= csrf_field() ?><input type="hidden" name="reason" value="adjust">
        <div class="row"><input type="number" step="0.01" min="0" inputmode="decimal" name="qty" placeholder="Counted qty" required><button class="btn" type="submit">Set count</button></div>
        <small class="muted">Sets the on-hand quantity to what you actually counted.</small>
      </form></div>
  </div>
</div>
