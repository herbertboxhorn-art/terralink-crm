<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><a class="crumb" href="/users">← Users</a><h1><?= h($title) ?></h1></div></div>
<div class="card" style="max-width:720px">
  <form method="post" class="form" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label>Name <b>*</b></label><input name="name" value="<?= h($u['name'] ?? '') ?>" required><?= $e('name') ?></div>
    <div class="field"><label>Email <b>*</b></label><input type="email" name="email" value="<?= h($u['email'] ?? '') ?>" required><?= $e('email') ?></div>
    <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= h($u['phone'] ?? '') ?>"></div>
    <div class="field"><label>Role</label><select name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= ($u['role'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?= $e('role') ?></div>
    <?php if (!$id): ?><div class="field check wide"><label><input type="checkbox" name="invite" value="1" checked> Email an invite: they choose their own password (recommended)</label></div><?php endif; ?>
    <div class="field wide"><label><?= $id ? 'New password (leave blank to keep)' : 'Or set a password yourself' ?></label><input type="password" name="password" autocomplete="new-password" minlength="10" placeholder="At least 10 characters"><?= $e('password') ?></div>
    <div class="field check wide"><label><input type="checkbox" name="totp_required" value="1"<?= !empty($u['totp_required']) ? ' checked' : '' ?>> Require two-step sign-in for this user<?= setting('twofa_policy', 'optional') !== 'optional' ? ' (already required by Settings: ' . h(strtolower(TWOFA_POLICIES[setting('twofa_policy')])) . ')' : '' ?></label></div>
    <div class="field check wide"><label><input type="checkbox" name="active" value="1"<?= !empty($u['active']) ? ' checked' : '' ?>> Active (can sign in)</label></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Save</button><a class="btn" href="/users">Cancel</a></div>
  </form>
</div>
<?php if ($id): ?>
<div class="card" style="max-width:720px;margin-top:16px">
  <div class="cardhead"><h2>Two-step sign-in</h2><span class="pill <?= twofa_enabled($u) ? 'active' : '' ?>"><?= twofa_enabled($u) ? 'On since ' . h(fdate($u['totp_enabled_at'])) : 'Not set up' ?></span></div>
  <?php if (twofa_enabled($u)): ?>
  <p class="muted" style="margin:6px 0 12px">Lost or replaced phone? Reset it: their code, recovery codes and trusted devices are cleared<?= twofa_required($u) ? ', and they set it up again at their next sign-in' : '' ?>. They get an email about it.</p>
  <form method="post" action="/users/<?= (int)$id ?>/2fa-reset" data-confirm="Reset two-step sign-in for <?= h($u['name']) ?>?"><?= csrf_field() ?><button class="btn" type="submit">Reset two-step sign-in</button></form>
  <?php else: ?><p class="muted" style="margin:6px 0 0"><?= twofa_required($u) ? 'Required: they\'ll be asked to set it up at their next sign-in.' : 'They can turn it on under My account.' ?></p><?php endif; ?>
</div>
<?php endif; ?>
