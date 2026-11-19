<?php $money = portal_can('perm_invoices'); ?>
<div class="pagehead"><div><h1>Service plans</h1><div class="sub">Regular maintenance we do for you. We open a ticket shortly before each visit.</div></div></div>
<?php if (!$plans): ?><div class="card"><div class="empty">No service plans yet. Ask us about Network Care or Managed IT.</div></div><?php else: ?>
<div class="stack">
<?php foreach ($plans as $p): ?>
  <div class="card">
    <div class="cardhead"><h2><?= h($p['title']) ?></h2><span class="pill active"><?= h(PLAN_EVERY[(int)$p['every_months']] ?? 'Every ' . (int)$p['every_months'] . ' months') ?></span></div>
    <dl class="kv">
      <dt>Next visit</dt><dd><b><?= h(fdate($p['next_due'], 'l, M j, Y')) ?></b></dd>
      <?php if ($p['site']): ?><dt>Site</dt><dd><?= h($p['site']) ?></dd><?php endif; ?>
      <?php if ($p['last_visit']): ?><dt>Last visit</dt><dd><?= h(fdate($p['last_visit'])) ?></dd><?php endif; ?>
      <?php if ($money && (float)$p['fee'] > 0): ?><dt>Fee</dt><dd><?= h(money($p['fee'])) ?> per visit<?= (int)$p['every_months'] > 1 ? ' <span class="muted">(' . h(money((float)$p['fee'] / (int)$p['every_months'])) . '/month, billed monthly)</span>' : ' <span class="muted">(billed monthly in advance)</span>' ?></dd><?php endif; ?>
    </dl>
    <?php if ($p['description']): ?><details style="margin-top:10px"><summary style="cursor:pointer">What we check</summary><p style="white-space:pre-wrap;margin:8px 0 0"><?= h($p['description']) ?></p></details><?php endif; ?>
  </div>
<?php endforeach; ?>
</div><?php endif; ?>
