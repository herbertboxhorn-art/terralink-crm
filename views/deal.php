<?php $id = (int)$d['id']; ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/pipeline">← Pipeline</a>
    <h1><?= h($d['title']) ?></h1>
    <div class="sub"><span class="pill <?= h($d['stage']) ?>"><?= h(STAGES[$d['stage']]) ?></span>
      <?php if ($d['company_id']): ?><a href="/companies/<?= (int)$d['company_id'] ?>"><?= h($d['company']) ?></a><?php endif; ?>
      <span><?= h($d['service']) ?></span><?php if ($d['value'] > 0): ?><b><?= h(money($d['value'])) ?></b><?php endif; ?></div>
  </div>
  <div class="actions">
    <?php if ($d['company_id']): ?><a class="btn primary" href="/quotes/new?deal_id=<?= $id ?>">+ Quote</a><?php endif; ?>
    <a class="btn" href="/deals/<?= $id ?>/edit">Edit</a>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <form method="post" action="/deals/<?= $id ?>/stage" class="stagebar"><?= csrf_field() ?>
    <?php foreach (STAGES as $k => $l): ?><button type="submit" name="stage" value="<?= $k ?>"<?= $d['stage'] === $k ? ' class="on"' : '' ?>><?= h($l) ?></button><?php endforeach; ?>
  </form>
</div>

<div class="grid side">
  <div class="stack">
    <?php if ($d['message']): ?><div class="card"><h2>Request details</h2><p style="white-space:pre-wrap;margin:0"><?= h($d['message']) ?></p></div><?php endif; ?>
    <?php $etype = 'deal'; $eid = $id; require __DIR__ . '/_timeline.php'; ?>
  </div>
  <div class="stack">
    <div class="card"><h2>Details</h2>
      <dl class="kv">
        <?php if ($d['contact_id']): ?><dt>Contact</dt><dd><a href="/contacts/<?= (int)$d['contact_id'] ?>"><?= h(trim($d['first_name'] . ' ' . $d['last_name'])) ?></a><br><?= tel($d['ct_mobile'] ?: $d['ct_phone']) ?><?php if ($d['ct_email']): ?><br><a href="mailto:<?= h($d['ct_email']) ?>"><?= h($d['ct_email']) ?></a><?php endif; ?></dd><?php endif; ?>
        <dt>Source</dt><dd><?= h(SOURCES[$d['source']]) ?></dd>
        <dt>Owner</dt><dd><?= h($d['owner'] ?? 'Unassigned') ?></dd>
        <?php if ($d['expected_close']): ?><dt>Expected close</dt><dd><?= h(fdate($d['expected_close'])) ?></dd><?php endif; ?>
        <?php if ($d['stage'] === 'lost' && $d['lost_reason']): ?><dt>Lost reason</dt><dd><?= h($d['lost_reason']) ?></dd><?php endif; ?>
        <dt>Created</dt><dd><?= h(fdate($d['created_at'])) ?></dd>
      </dl>
    </div>
    <?php if ($quotes): ?><div class="card"><h2>Quotes</h2><ul class="list"><?php foreach ($quotes as $qt): ?>
      <li><span class="pill <?= h($qt['status']) ?>"><?= h(QUOTE_STATUS[$qt['status']]) ?></span><div class="grow"><a href="/quotes/<?= (int)$qt['id'] ?>"><?= h($qt['number']) ?></a><span class="meta"><?= h(fdate($qt['issued_on'])) ?></span></div></li>
    <?php endforeach; ?></ul></div><?php endif; ?>
    <?php $link = ['deal_id' => $id]; $back = "/deals/$id"; require __DIR__ . '/_tasks.php'; ?>
  </div>
</div>
