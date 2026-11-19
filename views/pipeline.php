<div class="pagehead">
  <div><h1>Pipeline</h1><div class="sub">Drag cards between stages (on a phone, open a deal and tap its stage). Won/Lost show the last 90 days.</div></div>
  <div class="actions"><a class="btn primary" href="/deals/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New deal</a></div>
</div>
<form class="filters" method="get">
  <div class="seg"><a href="?<?= h(http_build_query(['service' => $service])) ?>"<?= $owner !== 'me' ? ' class="on"' : '' ?>>Everyone</a><a href="?<?= h(http_build_query(['owner' => 'me', 'service' => $service])) ?>"<?= $owner === 'me' ? ' class="on"' : '' ?>>Mine</a></div>
  <input type="hidden" name="owner" value="<?= h($owner) ?>">
  <select name="service" onchange="this.form.submit()"><option value="">All services</option>
    <?php foreach (SERVICES as $s): ?><option<?= $service === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
</form>
<div class="board">
<?php foreach (STAGES as $key => $label): $cards = $cols[$key]; $sum = array_sum(array_column($cards, 'value')); ?>
  <div class="col <?= $key ?>" data-stage="<?= $key ?>">
    <h3><?= h($label) ?> <small><?= count($cards) ?> · <?= h(money($sum)) ?></small></h3>
    <div class="cards">
    <?php foreach ($cards as $d): ?>
      <a class="dcard src-<?= h($d['source']) ?>" href="/deals/<?= (int)$d['id'] ?>" draggable="true" data-id="<?= (int)$d['id'] ?>" data-value="<?= (float)$d['value'] ?>">
        <b><?= h($d['title']) ?></b>
        <div class="meta"><span><?= h($d['company'] ?? '—') ?></span><span class="val"><?= $d['value'] > 0 ? h(money($d['value'])) : '' ?></span></div>
        <div class="meta"><span><?= h($d['service']) ?></span><span><?= h(ago($d['stage_changed_at'])) ?></span></div>
      </a>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
