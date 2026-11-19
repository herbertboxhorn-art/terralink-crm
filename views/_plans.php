<?php /** Service plans card. Needs $pl (rows), $newUrl */ ?>
<div class="card" id="plans">
  <div class="cardhead"><h2>Service plans</h2><a href="<?= h($newUrl) ?>">+ Plan</a></div>
  <?php if (!$pl): ?><div class="empty" style="padding:6px 0">No recurring visits set up.</div><?php else: ?>
  <ul class="list"><?php foreach ($pl as $p): ?>
    <li><span class="pill <?= $p['active'] ? 'active' : '' ?>"><?= h(PLAN_EVERY[(int)$p['every_months']] ?? '') ?></span>
      <div class="grow"><a href="/plans/<?= (int)$p['id'] ?>/edit"><?= h($p['title']) ?></a>
        <span class="meta"><?= $p['active'] ? 'Next ' . h(fdate($p['next_due'], 'M j, Y')) : 'Paused' ?><?= $p['site'] ? ' · ' . h($p['site']) : '' ?></span></div></li>
  <?php endforeach; ?></ul><?php endif; ?>
</div>
