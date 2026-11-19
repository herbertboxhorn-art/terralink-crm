<?php $id = (int)$c['id']; $addr = trim(implode(', ', array_filter([$c['address'], $c['town'], $c['province']]))); ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/companies">← Companies</a>
    <h1><?= h($c['name']) ?></h1>
    <div class="sub"><span class="pill ind"><?= h($c['industry']) ?></span><?php if ($c['town']): ?><span><?= h($c['town']) ?></span><?php endif; ?><?php if ($c['owner']): ?><span>Owner: <?= h($c['owner']) ?></span><?php endif; ?></div>
  </div>
  <div class="actions">
    <a class="btn primary" href="/deals/new?company_id=<?= $id ?>">+ Deal</a>
    <a class="btn" href="/tickets/new?company_id=<?= $id ?>">+ Ticket</a>
    <a class="btn" href="/quotes/new?company_id=<?= $id ?>">+ Quote</a>
    <a class="btn" href="/companies/<?= $id ?>/edit">Edit</a>
  </div>
</div>

<div class="quick" style="margin-bottom:18px">
  <?php if ($c['phone']): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><svg class="i"><use href="/assets/icons.svg#phone"/></svg><?= h($c['phone']) ?></a><?php endif; ?>
  <?php if ($c['email']): ?><a href="mailto:<?= h($c['email']) ?>"><svg class="i"><use href="/assets/icons.svg#mail"/></svg>Email</a><?php endif; ?>
  <?php if ($addr): ?><a href="https://maps.google.com/?q=<?= rawurlencode($addr) ?>" target="_blank" rel="noopener"><svg class="i"><use href="/assets/icons.svg#pin"/></svg>Directions</a><?php endif; ?>
</div>

<nav class="tabs" data-tabs>
  <a href="#overview">Overview</a>
  <a href="#contacts">Contacts <span><?= count($contacts) ?></span></a>
  <a href="#deals">Deals <span><?= count($deals) ?></span></a>
  <a href="#sites">Sites &amp; gear <span><?= count($sites) ?></span></a>
  <a href="#tickets">Tickets <span><?= count($tickets) ?></span></a>
  <a href="#quotes">Quotes <span><?= count($quotes) ?></span></a>
  <a href="#portal">Client portal <span><?= count(array_filter($portalUsers, fn($p) => $p['active'])) ?></span></a>
</nav>

<section data-tabpanel="overview">
  <div class="grid side">
    <div class="stack">
      <?php $etype = 'company'; $eid = $id; require __DIR__ . '/_timeline.php'; ?>
    </div>
    <div class="stack">
      <div class="card">
        <h2>Details</h2>
        <dl class="kv">
          <?php if ($c['phone']): ?><dt>Phone</dt><dd><?= tel($c['phone']) ?></dd><?php endif; ?>
          <?php if ($c['email']): ?><dt>Email</dt><dd><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></dd><?php endif; ?>
          <?php if ($c['website']): ?><dt>Website</dt><dd><a href="<?= h(preg_match('#^https?://#', $c['website']) ? $c['website'] : 'https://' . $c['website']) ?>" target="_blank" rel="noopener"><?= h($c['website']) ?></a></dd><?php endif; ?>
          <?php if ($addr): ?><dt>Address</dt><dd><?= h($addr) ?></dd><?php endif; ?>
          <dt>Added</dt><dd><?= h(fdate($c['created_at'])) ?></dd>
        </dl>
        <?php if ($c['notes']): ?><p style="white-space:pre-wrap;margin:12px 0 0"><?= h($c['notes']) ?></p><?php endif; ?>
      </div>
      <?php if ($contacts): $p = $contacts[0]; ?>
      <div class="card"><h2>Main contact</h2>
        <b><a href="/contacts/<?= (int)$p['id'] ?>"><?= h(contact_name($p)) ?></a></b><?php if ($p['title']): ?> <span class="muted">· <?= h($p['title']) ?></span><?php endif; ?>
        <div style="margin-top:6px"><?= tel($p['mobile'] ?: $p['phone']) ?><?php if ($p['email']): ?> · <a href="mailto:<?= h($p['email']) ?>"><?= h($p['email']) ?></a><?php endif; ?></div>
      </div>
      <?php endif; ?>
      <?php $link = ['company_id' => $id]; $back = "/companies/$id"; require __DIR__ . '/_tasks.php'; ?>
      <?php $pl = plans_for('company_id', $id); $newUrl = "/plans/new?company_id=$id"; require __DIR__ . '/_plans.php'; ?>
      <?php $ftype = 'company'; $fid = $id; require __DIR__ . '/_files.php'; ?>
    </div>
  </div>
</section>

<section data-tabpanel="contacts">
  <div class="card">
    <div class="cardhead"><h2>Contacts</h2><a class="btn sm" href="/contacts/new?company_id=<?= $id ?>">+ Contact</a></div>
    <?php if (!$contacts): ?><div class="empty">No contacts yet.</div><?php else: ?>
    <ul class="list"><?php foreach ($contacts as $ct): ?>
      <li><div class="grow"><a href="/contacts/<?= (int)$ct['id'] ?>"><?= h(contact_name($ct)) ?></a><?= $ct['is_primary'] ? ' <span class="pill new">Primary</span>' : '' ?>
        <span class="meta"><?= h($ct['title']) ?><?= $ct['title'] && $ct['email'] ? ' · ' : '' ?><?= h($ct['email']) ?></span></div>
        <?= tel($ct['mobile'] ?: $ct['phone']) ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</section>

<section data-tabpanel="deals">
  <div class="card">
    <div class="cardhead"><h2>Deals</h2><a class="btn sm" href="/deals/new?company_id=<?= $id ?>">+ Deal</a></div>
    <?php if (!$deals): ?><div class="empty">No deals yet.</div><?php else: ?>
    <ul class="list"><?php foreach ($deals as $d): ?>
      <li><span class="pill <?= h($d['stage']) ?>"><?= h(STAGES[$d['stage']]) ?></span>
        <div class="grow"><a href="/deals/<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a><span class="meta"><?= h($d['service']) ?> · <?= h($d['owner'] ?? 'Unassigned') ?></span></div>
        <b><?= $d['value'] > 0 ? h(money($d['value'])) : '' ?></b></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</section>

<section data-tabpanel="sites">
  <div class="card">
    <div class="cardhead"><h2>Sites &amp; installed equipment</h2><a class="btn sm" href="/sites/new?company_id=<?= $id ?>">+ Site</a></div>
    <?php if (!$sites): ?><div class="empty">No sites yet. Add the office, yard or camp where gear is installed.</div><?php else: ?>
    <ul class="list"><?php foreach ($sites as $s): ?>
      <li><div class="grow"><a href="/sites/<?= (int)$s['id'] ?>"><?= h($s['name']) ?></a>
        <span class="meta"><?= h(implode(' · ', array_filter([$s['town'], $s['connectivity'], $s['n_equip'] . ' device' . ($s['n_equip'] == 1 ? '' : 's')]))) ?></span></div>
        <?= maplink($s['address'] ? $s['address'] . ', ' . $s['town'] : $s['town'], $s['latitude'], $s['longitude']) ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</section>

<section data-tabpanel="tickets">
  <div class="card">
    <div class="cardhead"><h2>Tickets</h2><a class="btn sm" href="/tickets/new?company_id=<?= $id ?>">+ Ticket</a></div>
    <?php if (!$tickets): ?><div class="empty">No tickets.</div><?php else: ?>
    <ul class="list"><?php foreach ($tickets as $t): ?>
      <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span>
        <div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['subject']) ?></a><span class="meta"><?= h($t['number']) ?> · <?= h($t['assignee'] ?? 'Unassigned') ?> · <?= h(ago($t['created_at'])) ?></span></div>
        <?php if ($t['priority'] !== 'normal'): ?><span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span><?php endif; ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</section>

<section data-tabpanel="quotes">
  <div class="card">
    <div class="cardhead"><h2>Quotes</h2><a class="btn sm" href="/quotes/new?company_id=<?= $id ?>">+ Quote</a></div>
    <?php if (!$quotes): ?><div class="empty">No quotes.</div><?php else: ?>
    <ul class="list"><?php foreach ($quotes as $qt): ?>
      <li><span class="pill <?= h($qt['status']) ?>"><?= h(QUOTE_STATUS[$qt['status']]) ?></span>
        <div class="grow"><a href="/quotes/<?= (int)$qt['id'] ?>"><?= h($qt['number']) ?> — <?= h($qt['title']) ?></a><span class="meta"><?= h(fdate($qt['issued_on'])) ?></span></div>
        <b><?= h(money($qt['subtotal'])) ?></b></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</section>

<section data-tabpanel="portal">
  <div class="grid side">
    <div class="card">
      <div class="cardhead"><h2>Portal logins</h2><a href="<?= h(portal_url()) ?>" target="_blank" rel="noopener"><?= h(preg_replace('#^https?://#', '', portal_url())) ?> ↗</a></div>
      <?php if (!$portalUsers): ?><div class="empty">No one at <?= h($c['name']) ?> can sign in to the client portal yet. Add a login on the right.</div><?php else: ?>
      <ul class="list"><?php foreach ($portalUsers as $p): ?>
        <li><?= avatar($p['name'], $p['id']) ?>
          <div class="grow"><a href="/portal-users/<?= (int)$p['id'] ?>/edit"><?= h($p['name']) ?></a><?= $p['is_admin'] ? ' <span class="pill new">Portal admin</span>' : '' ?><?= !$p['active'] ? ' <span class="pill">Disabled</span>' : '' ?>
            <span class="meta"><?= h($p['email']) ?> · <?= h(portal_perm_summary($p)) ?></span>
            <span class="meta"><?= $p['last_login_at'] ? 'Last signed in ' . h(ago($p['last_login_at'])) : ($p['invited_at'] ? 'Invited ' . h(ago($p['invited_at'])) . ', not signed in yet' : 'Not invited yet') ?></span></div>
          <?php if (is_role('admin', 'sales')): ?><a class="btn sm" href="/portal-users/<?= (int)$p['id'] ?>/edit">Edit</a><?php endif; ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php if (is_role('admin', 'sales')): ?>
    <div class="card">
      <h2>Add a portal login</h2>
      <form method="post" action="/companies/<?= $id ?>/portal-users" class="form" style="grid-template-columns:1fr" autocomplete="off"><?= csrf_field() ?>
        <div class="field"><label>From a contact</label><select name="contact_id"><option value="">— Type a name and email below —</option>
          <?php foreach ($contacts as $ct): if (!$ct['email']) continue; ?><option value="<?= (int)$ct['id'] ?>"><?= h(contact_name($ct)) ?> · <?= h($ct['email']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Name</label><input name="name" placeholder="Leave blank when picking a contact"></div>
        <div class="field"><label>Email (their login)</label><input type="email" name="email" placeholder="Leave blank when picking a contact"></div>
        <div class="field"><label>Access</label><select name="preset" data-preset>
          <?php foreach (PORTAL_PRESETS as $k => $p): ?><option value="<?= $k ?>" data-perms="<?= h(implode(',', $p['perms'])) ?>"><?= h($p['label']) ?></option><?php endforeach; ?>
          <option value="">Custom (tick below)</option></select></div>
        <details><summary class="muted" style="cursor:pointer">Custom permissions</summary><?php $pp = ['perm_tickets' => 1]; $grantable = null; require __DIR__ . '/_portal_perms.php'; ?></details>
        <div class="field check"><label><input type="checkbox" name="invite" value="1" checked> Email them an invite now</label></div>
        <button class="btn primary" type="submit">Add login</button>
      </form>
      <p class="muted" style="font-size:13px;margin:12px 0 0">Tickets only: their own tickets. Operations: all tickets, sites, plans, no prices. Full access: also quotes, invoices, and they can add colleagues themselves.</p>
    </div>
    <?php endif; ?>
  </div>
</section>
