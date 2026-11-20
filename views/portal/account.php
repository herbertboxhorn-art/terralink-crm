<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; $pu = portal_user(); ?>
<div class="pagehead"><div><h1>My account</h1><div class="sub"><?= h($pu['name']) ?> · <?= h($pu['email']) ?> · <?= h($pu['company']) ?></div></div></div>
<div class="grid g2">
<div class="card">
  <h2>Change password</h2>
  <form method="post" class="form" style="grid-template-columns:1fr;margin-top:12px"><?= csrf_field() ?>
    <div class="field"><label>Current password</label><input type="password" name="current" autocomplete="current-password" required><?= $e('current') ?></div>
    <div class="field"><label>New password</label><input type="password" name="new" autocomplete="new-password" minlength="10" required><?= $e('new') ?></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="confirm" autocomplete="new-password" required><?= $e('confirm') ?></div>
    <div class="formbar"><button class="btn primary" type="submit">Change password</button></div>
  </form>
</div>
<div class="card">
  <h2>What your login can see</h2>
  <ul class="list" style="margin-top:8px">
    <?php foreach (PORTAL_PERMS as $k => [$label, $hint]): ?>
    <li><span style="width:22px;font-weight:700;color:<?= !empty($pu[$k]) ? 'var(--ok)' : 'var(--muted)' ?>"><?= !empty($pu[$k]) ? '✓' : '–' ?></span>
      <div class="grow"><b<?= empty($pu[$k]) ? ' class="muted"' : '' ?>><?= h($label) ?><?= $k === 'perm_tickets' && !empty($pu[$k]) ? (empty($pu['perm_tickets_all']) ? ' (yours)' : ' (whole company)') : '' ?></b><span class="meta"><?= h($hint) ?></span></div></li>
    <?php endforeach; ?>
  </ul>
  <p style="margin:14px 0 0"><a class="btn sm" href="/account/2fa">Two-step sign-in: <?= twofa_enabled($pu) ? 'On' : 'Off' ?></a></p>
  <p class="muted" style="font-size:13px;margin:12px 0 0">Need more access? Ask your company's portal admin or <?= h(setting('company_name')) ?>.</p>
</div>
</div>
