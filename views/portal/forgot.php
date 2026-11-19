<?php ob_start(); ?>
<h1>Forgot password</h1>
<?php if ($sent): ?>
  <div class="flash" style="margin:16px 0">If that email belongs to an active portal login, a reset link is on its way. It works for 1 hour.</div>
  <p class="alt"><a href="/login">← Back to sign in</a></p>
<?php else: ?>
  <p class="muted" style="margin:0">Enter your login email and we'll send you a link to choose a new password.</p>
  <?php if ($error): ?><div class="flash bad" style="margin:16px 0 0"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="/forgot"><?= csrf_field() ?>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required autofocus></div>
    <button class="btn primary" type="submit">Send reset link</button>
  </form>
  <p class="alt"><a href="/login">← Back to sign in</a></p>
<?php endif; ?>
<?php $inner = ob_get_clean(); require __DIR__ . '/_authshell.php'; ?>
