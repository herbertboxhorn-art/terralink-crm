<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ob_start(); ?>
<?php if (!$row): ?>
  <h1>Link expired</h1>
  <p class="muted">This link is invalid, was already used, or has expired. Request a new one.</p>
  <p><a class="btn primary" href="/forgot">Send a new link</a></p>
<?php else: ?>
  <h1><?= $row['kind'] === 'invite' ? 'Welcome, ' . h(explode(' ', $row['name'])[0]) : 'Choose a new password' ?></h1>
  <p class="muted" style="margin:0">Your login is <b><?= h($row['email']) ?></b>.</p>
  <form method="post" action="/reset"><?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
    <div class="field"><label>New password</label><input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus><?= $e('password') ?></div>
    <div class="field"><label>Confirm password</label><input type="password" name="confirm" autocomplete="new-password" required><?= $e('confirm') ?></div>
    <button class="btn primary" type="submit"><?= $row['kind'] === 'invite' ? 'Set password & sign in' : 'Save & sign in' ?></button>
  </form>
<?php endif; ?>
<?php $inner = ob_get_clean(); require __DIR__ . '/_authshell.php'; ?>
