<?php $id = (int)$ct['id']; ?>
<div class="pagehead">
  <div>
    <?php if ($ct['company_id']): ?><a class="crumb" href="/companies/<?= (int)$ct['company_id'] ?>#contacts">← <?= h($ct['company']) ?></a><?php else: ?><a class="crumb" href="/contacts">← Contacts</a><?php endif; ?>
    <h1><?= h(contact_name($ct)) ?></h1>
    <div class="sub"><?= h($ct['title']) ?><?= $ct['is_primary'] ? ' <span class="pill new">Primary contact</span>' : '' ?></div>
  </div>
  <div class="actions">
    <?php if ($ct['company_id']): ?><a class="btn" href="/deals/new?company_id=<?= (int)$ct['company_id'] ?>">+ Deal</a><?php endif; ?>
    <a class="btn" href="/contacts/<?= $id ?>/edit">Edit</a>
  </div>
</div>
<div class="quick" style="margin-bottom:18px">
  <?php foreach (['mobile' => 'Mobile', 'phone' => 'Office'] as $k => $l): if ($ct[$k]): ?>
  <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $ct[$k])) ?>"><svg class="i"><use href="/assets/icons.svg#phone"/></svg><?= $l ?> <?= h($ct[$k]) ?></a>
  <?php endif; endforeach; ?>
  <?php if ($ct['email']): ?><a href="mailto:<?= h($ct['email']) ?>"><svg class="i"><use href="/assets/icons.svg#mail"/></svg><?= h($ct['email']) ?></a><?php endif; ?>
</div>
<div class="grid side">
  <div class="stack"><?php $etype = 'contact'; $eid = $id; require __DIR__ . '/_timeline.php'; ?></div>
  <div class="stack">
    <?php if ($ct['notes']): ?><div class="card"><h2>Notes</h2><p style="white-space:pre-wrap;margin:0"><?= h($ct['notes']) ?></p></div><?php endif; ?>
    <div class="card"><h2>Deals</h2>
      <?php if (!$deals): ?><div class="empty">None.</div><?php else: ?><ul class="list"><?php foreach ($deals as $d): ?>
        <li><span class="pill <?= h($d['stage']) ?>"><?= h(STAGES[$d['stage']]) ?></span><div class="grow"><a href="/deals/<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card"><h2>Tickets reported</h2>
      <?php if (!$tickets): ?><div class="empty">None.</div><?php else: ?><ul class="list"><?php foreach ($tickets as $t): ?>
        <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span><div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['number']) ?> — <?= h($t['subject']) ?></a></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</div>
