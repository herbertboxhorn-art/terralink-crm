<?php $id = (int)$t['id']; $m = (int)$t['minutes_spent']; ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/tickets">← Tickets</a>
    <h1><?= h($t['subject']) ?></h1>
    <div class="sub"><span class="mono"><?= h($t['number']) ?></span>
      <span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span>
      <span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span>
      <a href="/companies/<?= (int)$t['company_id'] ?>"><?= h($t['company']) ?></a>
      <?php if ($t['site_id']): ?><a href="/sites/<?= (int)$t['site_id'] ?>"><?= h($t['site']) ?></a><?php endif; ?></div>
  </div>
  <div class="actions"><a class="btn" href="/tickets/<?= $id ?>/edit">Edit</a></div>
</div>

<div class="grid side">
  <div class="stack">
    <div class="card">
      <h2>Update</h2>
      <form method="post" action="/tickets/<?= $id ?>/update" class="inline"><?= csrf_field() ?>
        <div class="row">
          <select name="status" aria-label="Status"><?php foreach (TICKET_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select name="minutes" aria-label="Add time"><option value="0">+ time…</option><?php foreach ([15, 30, 45, 60, 90, 120, 180, 240, 480] as $mm): ?><option value="<?= $mm ?>">+<?= $mm >= 60 ? ($mm / 60) . ' h' : $mm . ' min' ?></option><?php endforeach; ?></select>
        </div>
        <textarea name="note" rows="2" placeholder="What was done / next step (optional)"></textarea>
        <button class="btn primary" type="submit">Save update</button>
      </form>
    </div>
    <?php if ($t['description']): ?><div class="card"><h2>Description</h2><p style="white-space:pre-wrap;margin:0"><?= h($t['description']) ?></p></div><?php endif; ?>
    <?php $etype = 'ticket'; $eid = $id; require __DIR__ . '/_timeline.php'; ?>
  </div>
  <div class="stack">
    <div class="card"><h2>Details</h2>
      <dl class="kv">
        <?php if ($t['first_name']): ?><dt>Reported by</dt><dd><a href="/contacts/<?= (int)$t['contact_id'] ?>"><?= h(trim($t['first_name'] . ' ' . $t['last_name'])) ?></a><br><?= tel($t['ct_mobile'] ?: $t['ct_phone']) ?></dd><?php endif; ?>
        <?php if ($t['equipment_id']): ?><dt>Device</dt><dd><?= h(trim($t['e_cat'] . ' ' . $t['e_make'] . ' ' . $t['e_model'])) ?><?= $t['e_serial'] ? '<br><span class="mono">S/N ' . h($t['e_serial']) . '</span>' : '' ?></dd><?php endif; ?>
        <dt>Assigned</dt><dd><?= h($t['assignee'] ?? 'Unassigned') ?></dd>
        <dt>Time spent</dt><dd><b><?= intdiv($m, 60) ?> h <?= $m % 60 ?> min</b></dd>
        <dt>Opened</dt><dd><?= h(fdate($t['created_at'], 'M j, Y g:i a')) ?></dd>
        <?php if ($t['closed_at']): ?><dt>Closed</dt><dd><?= h(fdate($t['closed_at'], 'M j, Y g:i a')) ?></dd><?php endif; ?>
      </dl>
    </div>
    <?php $link = ['ticket_id' => $id]; $back = "/tickets/$id"; require __DIR__ . '/_tasks.php'; ?>
  </div>
</div>
