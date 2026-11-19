<?php ob_start(); ?>
<h1>Sign in</h1>
<p class="muted" style="margin:0">Client portal for <?= h(setting('company_name', 'TerraLink Systems')) ?> customers.</p>
<?php if ($error): ?><div class="flash bad" style="margin:16px 0 0"><?= h($error) ?></div><?php endif; ?>
<?php if ($f = flash()): ?><div class="flash <?= h($f['type']) ?>" style="margin:16px 0 0"><?= h($f['msg']) ?></div><?php endif; ?>
<form method="post" action="/login">
  <?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required autofocus value="<?= h($_POST['email'] ?? '') ?>"></div>
  <div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" required></div>
  <button class="btn primary" type="submit">Sign in</button>
</form>
<p class="alt"><a href="/forgot">Forgot your password?</a> · No login yet? Ask your office or call us.</p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_authshell.php'; ?>
