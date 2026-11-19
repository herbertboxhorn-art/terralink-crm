<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0F1A24">
<title>Sign in · TerraLink CRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="login">
  <div class="card">
    <h1><img src="/assets/favicon.svg" alt="" width="34" height="34"> TerraLink CRM</h1>
    <p class="muted" style="margin:0">Sign in to continue.</p>
    <?php if ($error): ?><div class="flash bad" style="margin:16px 0 0"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="/login">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required autofocus value="<?= h($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" required></div>
      <button class="btn primary" type="submit">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
