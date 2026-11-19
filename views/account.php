<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><h1>My account</h1><div class="sub"><?= h(user()['name']) ?> · <?= h(user()['email']) ?> · <?= h(ROLES[user()['role']]) ?></div></div></div>
<div class="card" style="max-width:520px">
  <h2>Change password</h2>
  <form method="post" class="form" style="grid-template-columns:1fr;margin-top:12px"><?= csrf_field() ?>
    <div class="field"><label>Current password</label><input type="password" name="current" autocomplete="current-password" required><?= $e('current') ?></div>
    <div class="field"><label>New password</label><input type="password" name="new" autocomplete="new-password" minlength="10" required><?= $e('new') ?></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="confirm" autocomplete="new-password" required><?= $e('confirm') ?></div>
    <div class="formbar"><button class="btn primary" type="submit">Change password</button></div>
  </form>
</div>
<div class="card" style="max-width:520px;margin-top:16px">
  <h2>Morning summary email</h2>
  <p class="muted" style="margin:6px 0 12px">Around 6:30 am: your tickets, tasks due, new leads and anything overdue. Not sent on days with nothing for you.</p>
  <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="prefs" value="1">
    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="daily_email" value="1"<?= !empty(user()['daily_email']) ? ' checked' : '' ?>> Send me the morning summary</label>
    <button class="btn" type="submit">Save</button>
  </form>
</div>
