<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Setup · TerraLink CRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body><div class="login"><div class="card">
  <h1><img src="/assets/favicon.svg" alt="" width="34" height="34"> First-run setup</h1>
  <?php if ($invalid): ?>
    <p class="muted">This setup link is invalid or has expired. Ask your administrator for a new setup link.</p>
  <?php else: ?>
    <p class="muted" style="margin:0">Create the first admin account. This link stops working once you save.</p>
    <form method="post" action="/setup">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
      <div class="field"><label>Your name</label><input name="name" value="<?= h($_POST['name'] ?? '') ?>" required autofocus><?= $e('name') ?></div>
      <div class="field"><label>Email (your login)</label><input type="email" name="email" autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>" required><?= $e('email') ?></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="new-password" minlength="10" required><?= $e('password') ?></div>
      <div class="field"><label>Confirm password</label><input type="password" name="confirm" autocomplete="new-password" required><?= $e('confirm') ?></div>
      <button class="btn primary" type="submit">Create admin account</button>
    </form>
  <?php endif; ?>
</div></div></body></html>
