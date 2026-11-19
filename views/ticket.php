<?php $id = (int)$t['id']; $m = (int)$t['minutes_spent']; ?>
<div class="pagehead">
  <div>
    <a class="crumb" href="/tickets">← Tickets</a>
    <h1><?= h($t['subject']) ?></h1>
    <div class="sub"><span class="mono"><?= h($t['number']) ?></span>
      <span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span>
      <span class="pill <?= h($t['priority']) ?>"><?= h(TICKET_PRIORITY[$t['priority']]) ?></span> <?= sla_badge($t) ?>
      <a href="/companies/<?= (int)$t['company_id'] ?>"><?= h($t['company']) ?></a>
      <?php if ($t['site_id']): ?><a href="/sites/<?= (int)$t['site_id'] ?>"><?= h($t['site']) ?></a><?php endif; ?></div>
  </div>
  <div class="actions"><a class="btn" href="/tickets/<?= $id ?>/edit">Edit</a></div>
</div>

<div class="grid side">
  <div class="stack">
    <div class="card">
      <h2>Update</h2>
      <form method="post" action="/tickets/<?= $id ?>/update" class="inline" style="margin-top:10px"><?= csrf_field() ?>
        <textarea name="note" rows="3" placeholder="What was done, what's next, or what the client said…"></textarea>
        <?php $watchers = portal_ticket_watchers($t); $anyPortal = (int)val('SELECT COUNT(*) FROM portal_users WHERE company_id = ? AND active = 1 AND perm_tickets = 1', [$t['company_id']]); ?>
        <?php if ($anyPortal): ?><label class="muted" style="display:flex;gap:8px;align-items:center;font-size:14px"><input type="checkbox" name="client" value="1" style="width:auto"<?= !empty($t['portal_user_id']) ? ' checked' : '' ?>>
          Reply to client: show in the portal<?= $watchers ? ' and email ' . h(implode(', ', array_column($watchers, 'name'))) : '' ?></label><?php endif; ?>
        <div class="row">
          <select name="type" aria-label="Kind"><?php foreach (ACTIVITY_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
          <select name="status" aria-label="Status"><?php foreach (TICKET_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select name="minutes" aria-label="Add time"><option value="0">+ time…</option><?php foreach ([15, 30, 45, 60, 90, 120, 180, 240, 480] as $mm): ?><option value="<?= $mm ?>">+<?= $mm >= 60 ? ($mm / 60) . ' h' : $mm . ' min' ?></option><?php endforeach; ?></select>
        </div>
        <button class="btn primary" type="submit">Save update</button>
      </form>
    </div>
    <?php require __DIR__ . '/_charges.php'; ?>
    <?php if ($t['description']): ?><div class="card"><h2>Description</h2><p style="white-space:pre-wrap;margin:0"><?= h($t['description']) ?></p></div><?php endif; ?>
    <?php $ftype = 'ticket'; $fid = $id; require __DIR__ . '/_files.php'; ?>
    <?php $etype = 'ticket'; $eid = $id; $noForm = true; require __DIR__ . '/_timeline.php'; ?>
  </div>
  <div class="stack">
    <div class="card"><h2>Details</h2>
      <dl class="kv">
        <?php if (!empty($t['portal_user_id'])): ?><dt>Opened in portal</dt><dd><?= h($t['portal_opener'] ?? '') ?> <span class="pubtag">Client portal</span></dd><?php endif; ?>
        <?php if ($t['first_name']): ?><dt>Reported by</dt><dd><a href="/contacts/<?= (int)$t['contact_id'] ?>"><?= h(trim($t['first_name'] . ' ' . $t['last_name'])) ?></a><br><?= tel($t['ct_mobile'] ?: $t['ct_phone']) ?></dd><?php endif; ?>
        <?php if ($t['equipment_id']): ?><dt>Device</dt><dd><?= h(trim($t['e_cat'] . ' ' . $t['e_make'] . ' ' . $t['e_model'])) ?><?= $t['e_serial'] ? '<br><span class="mono">S/N ' . h($t['e_serial']) . '</span>' : '' ?></dd><?php endif; ?>
        <?php if (!empty($t['plan_id'])): ?><dt>Service plan</dt><dd><a href="/plans/<?= (int)$t['plan_id'] ?>/edit"><?= h($t['plan_title'] ?? 'View plan') ?></a></dd><?php endif; ?>
        <dt>Assigned</dt><dd><?= avatar($t['assignee'], $t['assigned_to']) ?> <?= h($t['assignee'] ?? 'Unassigned') ?></dd>
        <dt>Time spent</dt><dd><?= $m ? '<b>' . h(dur($m)) . '</b>' . ((int)$t['minutes_billed'] ? ' <small class="muted">(' . h(dur((int)$t['minutes_billed'])) . ' billed)</small>' : '') : '—' ?></dd>
        <dt>Charges</dt><dd><?= $charges ? '<a href="#charges"><b>' . h(money($ctot['total'])) . '</b></a> incl. tax' : '—' ?><?= $t['billing'] === 'no_charge' ? ' <span class="pill">No charge</span>' : '' ?></dd>
        <dt>Response target</dt><dd><?= sla_badge($t) ?: ($t['first_response_at'] ? 'Responded ' . h(ago($t['first_response_at'])) : '—') ?> <small class="muted">(<?= sla_hours($t['priority']) ?> h for <?= h(strtolower(TICKET_PRIORITY[$t['priority']])) ?>)</small></dd>
        <dt>Opened</dt><dd><?= h(fdate($t['created_at'], 'M j, Y g:i a')) ?></dd>
        <?php if ($t['closed_at']): ?><dt>Closed</dt><dd><?= h(fdate($t['closed_at'], 'M j, Y g:i a')) ?></dd><?php endif; ?>
      </dl>
    </div>
    <?php $link = ['ticket_id' => $id]; $back = "/tickets/$id"; require __DIR__ . '/_tasks.php'; ?>
  </div>
</div>
