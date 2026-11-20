<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; $pp = $pu; $grantable = null; $siteLimit = null; ?>
<div class="pagehead"><div><a class="crumb" href="/companies/<?= (int)$pu['company_id'] ?>#portal">← <?= h($pu['company']) ?></a><h1><?= h($pu['name']) ?></h1>
  <div class="sub">Client portal login · <?= $pu['last_login_at'] ? 'last signed in ' . h(ago($pu['last_login_at'])) : 'has not signed in yet' ?></div></div></div>
<div class="card" style="max-width:760px">
  <form method="post" class="form" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label>Name <b>*</b></label><input name="name" value="<?= h($pu['name']) ?>" required><?= $e('name') ?></div>
    <div class="field"><label>Email (their login) <b>*</b></label><input type="email" name="email" value="<?= h($pu['email']) ?>" required><?= $e('email') ?></div>
    <div class="field wide"><label>Linked contact</label><select name="contact_id"><?php foreach ($contacts as $k => $l): ?><option value="<?= h($k) ?>"<?= (string)$k === (string)($pu['contact_id'] ?? '') ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
      <small class="muted">Tickets where this contact is the contact show up for them, even without "See all company tickets".</small></div>
    <div class="field wide"><label>What they can see and do</label><?php require __DIR__ . '/_portal_perms.php'; ?></div>
    <div class="field check wide"><input type="hidden" name="active" value="0"><label><input type="checkbox" name="active" value="1"<?= !empty($pu['active']) ? ' checked' : '' ?>> Active (can sign in)</label></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Save</button><a class="btn" href="/companies/<?= (int)$pu['company_id'] ?>#portal">Cancel</a></div>
  </form>
</div>
<div class="card" style="max-width:760px;margin-top:16px">
  <div class="cardhead"><h2>Login link</h2></div>
  <p class="muted" style="margin-top:0">Emails a new link so they can set (or reset) their password. Old links stop working.</p>
  <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($pu['active']): ?><form method="post" action="/portal-users/<?= (int)$pu['id'] ?>/invite"><?= csrf_field() ?><button class="btn" type="submit">Email a login link</button></form><?php endif; ?>
    <form method="post" action="/portal-users/<?= (int)$pu['id'] ?>/delete" data-confirm="Remove this portal login? Their tickets and messages stay on record."><?= csrf_field() ?><button class="btn ghost" type="submit" style="color:var(--bad)">Remove login</button></form>
  </div>
</div>
<div class="card" style="max-width:760px;margin-top:16px">
  <div class="cardhead"><h2>Two-step sign-in</h2></div>
  <p class="muted" style="margin-top:0"><?= twofa_enabled($pu) ? 'On since ' . h(fdate($pu['totp_enabled_at'])) . '.' : 'Not set up.' ?> <?= portal_twofa_required($pu) ? 'Required for this login (Settings → Security).' : 'Optional for this login.' ?> Changing the email above resets their password, so send a new login link afterwards.</p>
  <?php if (twofa_enabled($pu)): ?><form method="post" action="/portal-users/<?= (int)$pu['id'] ?>/2fa-reset" data-confirm="Reset two-step sign-in for this login? They'll have to set it up again (lost or new phone)."><?= csrf_field() ?><button class="btn" type="submit">Reset two-step sign-in</button></form><?php endif; ?>
</div>
