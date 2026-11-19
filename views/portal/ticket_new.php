<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><a class="crumb" href="/tickets">← Tickets</a><h1>Open a ticket</h1>
  <div class="sub">Tell us what's going on. Photos of error messages or blinking lights help a lot.</div></div></div>
<div class="card" style="max-width:780px">
  <form method="post" action="/tickets/new" enctype="multipart/form-data"  class="form"><?= csrf_field() ?>
    <div class="field wide"><label>What's the problem? <b>*</b></label><input name="subject" maxlength="190" value="<?= h($row['subject']) ?>" placeholder="e.g. Wi-Fi down in the shop, printer offline" required autofocus><?= $e('subject') ?></div>
    <?php if ($sites): ?>
    <div class="field"><label>Site</label><select name="site_id"><option value="">— Not sure / general —</option>
      <?php foreach ($sites as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)$row['site_id'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="field"><label>How urgent?</label><select name="priority">
      <?php foreach (['low' => 'Low: whenever you get to it', 'normal' => 'Normal', 'high' => 'High: slowing us down', 'urgent' => 'Urgent: we can\'t work'] as $k => $l): ?><option value="<?= $k ?>"<?= $row['priority'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <?php if ($equip): ?><div class="field wide"><label>Device</label><input type="hidden" name="equipment_id" value="<?= (int)$equip['id'] ?>">
      <div><?= h(trim($equip['category'] . ' · ' . $equip['make'] . ' ' . $equip['model'])) ?><?= $equip['serial'] ? ' <span class="mono muted">S/N ' . h($equip['serial']) . '</span>' : '' ?></div></div><?php endif; ?>
    <div class="field wide"><label>Details <b>*</b></label><textarea name="description" rows="6" placeholder="When did it start? Who is affected? What have you tried? Best number to reach you?" required><?= h($row['description']) ?></textarea><?= $e('description') ?></div>
    <div class="field wide"><label>Photos or files</label><input type="file" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.heic,.mp4,.mov">
      <small class="muted">Max <?= upload_limit_mb() ?> MB each</small></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Send ticket</button><a class="btn" href="/tickets">Cancel</a></div>
  </form>
  <p class="muted" style="font-size:13.5px;margin:14px 0 0">If your business is down, also call us at <b><?= h(setting('company_phone')) ?></b>.</p>
</div>
