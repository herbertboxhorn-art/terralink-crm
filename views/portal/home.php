<?php $pu = portal_user(); $first = explode(' ', $pu['name'])[0]; ?>
<div class="pagehead">
  <div><h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= h($first) ?></h1>
  <div class="sub"><?= h($pu['company']) ?> · <?= date('l, F j') ?></div></div>
  <?php if (portal_can('perm_tickets')): ?><div class="actions"><a class="btn primary" href="/tickets/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Open a ticket</a></div><?php endif; ?>
</div>

<div class="stats">
  <?php if (portal_can('perm_tickets')): ?>
  <a class="stat<?= $waiting ? ' hot' : '' ?>" href="/tickets"><small>Open tickets</small><b><?= $openTickets ?></b><span><?= $waiting ? "$waiting waiting on your reply" : 'we\'re on it' ?></span></a>
  <?php endif; ?>
  <?php if (portal_can('perm_plans')): ?>
  <a class="stat" href="/plans"><small>Next service visit</small><b><?= !empty($nextVisit) ? h(fdate($nextVisit['next_due'], 'M j')) : '—' ?></b><span><?= !empty($nextVisit) ? h($nextVisit['title']) : 'no plan yet' ?></span></a>
  <?php endif; ?>
  <?php if (portal_can('perm_quotes')): ?>
  <a class="stat<?= $quotesWaiting ? ' hot' : '' ?>" href="/quotes"><small>Quotes</small><b><?= $quotesWaiting ?></b><span>waiting for your decision</span></a>
  <?php endif; ?>
  <?php if (portal_can('perm_invoices')): ?>
  <a class="stat<?= $overdue ? ' hot' : '' ?>" href="/invoices"><small>Balance owing</small><b><?= h(money0($owing)) ?></b><span><?= $overdue ? "$overdue overdue" : 'nothing overdue' ?></span></a>
  <?php endif; ?>
  <?php if (portal_can('perm_sites')): ?>
  <a class="stat" href="/sites"><small>Equipment</small><b><?= $devices ?></b><span>devices on record</span></a>
  <?php endif; ?>
</div>

<div class="grid side">
  <?php if (portal_can('perm_tickets')): ?>
  <div class="card">
    <div class="cardhead"><h2>Recent tickets</h2><a href="/tickets">All tickets →</a></div>
    <?php if (!$recent): ?><div class="empty">No tickets yet. Something not working? <a href="/tickets/new">Open a ticket</a> and we'll take it from there.</div><?php else: ?>
    <ul class="list"><?php foreach ($recent as $t): ?>
      <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span>
        <div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><span class="meta"><?= h($t['number']) ?><?= $t['site'] ? ' · ' . h($t['site']) : '' ?> · updated <?= h(ago($t['updated_at'])) ?></span></div></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
  <?php else: ?><div class="card"><h2>Welcome</h2><p class="muted">Use the menu to see what's shared with you.</p></div><?php endif; ?>
  <div class="card">
    <h2>Contact <?= h(setting('company_name', 'TerraLink')) ?></h2>
    <dl class="kv" style="margin-top:8px">
      <dt>Phone</dt><dd><?= tel(setting('company_phone')) ?></dd>
      <dt>Email</dt><dd><a href="mailto:<?= h(setting('company_email')) ?>"><?= h(setting('company_email')) ?></a></dd>
    </dl>
    <p class="muted" style="font-size:13.5px;margin:12px 0 0">For an outage or anything urgent, call us. Then open a ticket so the details are on record.</p>
  </div>
</div>
