<div class="pagehead">
  <div>
    <h1><?= h($title) ?></h1>
    <?php if (!empty($subtitle)): ?><div class="sub"><?= h($subtitle) ?></div><?php endif; ?>
  </div>
</div>
<div class="card" style="max-width:860px">
  <form method="post" class="form"<?= !empty($dependent) ? ' data-dependent' : '' ?> novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash bad wide" style="grid-column:1/-1;margin:0">Please fix the highlighted fields.</div><?php endif; ?>
    <?= render_fields($def, $row ?? [], $errors) ?>
    <?php if (!empty($extraField)): [$xn, $xl, $xv] = $extraField; ?>
      <div class="field"><label for="f_<?= h($xn) ?>"><?= h($xl) ?></label><input id="f_<?= h($xn) ?>" type="number" inputmode="decimal" step="0.01" min="0" name="<?= h($xn) ?>" value="<?= h($xv) ?>"><?= isset($errors[$xn]) ? '<div class="err">' . h($errors[$xn]) . '</div>' : '' ?></div>
    <?php endif; ?>
    <?php if (!empty($geo)): ?><div class="field wide"><button type="button" class="btn sm" data-geo hidden>📍 Use my current location for GPS</button></div><?php endif; ?>
    <div class="formbar" style="grid-column:1/-1">
      <button class="btn primary" type="submit">Save</button>
      <a class="btn" href="<?= h($cancel) ?>">Cancel</a>
      <span class="spacer"></span>
    </div>
  </form>
  <?php if (!empty($extraActions)): ?>
  <div class="actions" style="margin-top:14px">
    <?php foreach ($extraActions as $a): if (!empty($a[2])): ?>
      <form method="post" action="<?= h($a[1]) ?>" class="actions"><?= csrf_field() ?>
        <select name="stock_location" aria-label="Return to location" style="min-height:42px;border:1px solid var(--line);border-radius:8px;padding:0 10px"><?php foreach (stock_locations() as $l): ?><option><?= h($l) ?></option><?php endforeach; ?></select>
        <label style="display:flex;align-items:center;gap:6px;font-size:14px"><input type="checkbox" name="faulty" value="1"> faulty</label>
        <button class="btn" type="submit"><?= h($a[0]) ?></button>
      </form>
    <?php else: ?>
      <a class="btn dark" href="<?= h($a[1]) ?>"><?= h($a[0]) ?></a>
    <?php endif; endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($deleteUrl)): ?>
  <form method="post" action="<?= h($deleteUrl) ?>" data-confirm="Delete this <?= h(strtolower($def['label'])) ?>? This can't be undone." style="margin-top:10px;text-align:right">
    <?= csrf_field() ?><button class="btn danger sm" type="submit">Delete <?= h(strtolower($def['label'])) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php if (!empty($files)): [$ftype, $fid] = $files; ?>
<div style="max-width:860px;margin-top:16px"><?php require __DIR__ . '/_files.php'; ?></div>
<?php endif; ?>
<?php if (!empty($planId)): ?>
<div class="card" style="max-width:860px;margin-top:16px">
  <div class="cardhead"><h2>Tickets from this plan</h2>
    <form method="post" action="/plans/<?= (int)$planId ?>/run" data-confirm="Open the ticket for the next visit now?"><?= csrf_field() ?><button class="btn sm" type="submit">Open ticket now</button></form></div>
  <?php if (empty($planTickets)): ?><div class="empty" style="padding:6px 0">None yet.</div><?php else: ?><ul class="list"><?php foreach ($planTickets as $t): ?>
    <li><span class="pill <?= h($t['status']) ?>"><?= h(TICKET_STATUS[$t['status']]) ?></span><div class="grow"><a href="/tickets/<?= (int)$t['id'] ?>"><?= h($t['number']) ?></a><span class="meta"><?= h(fdate($t['created_at'], 'M j, Y')) ?></span></div></li>
  <?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
