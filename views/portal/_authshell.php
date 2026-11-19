<?php /** Portal login / forgot / reset shell. Expects $inner (HTML) */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0F1A24"><meta name="robots" content="noindex">
<title><?= h($title ?? 'Sign in') ?> · <?= h(setting('company_name', 'TerraLink Systems')) ?> client portal</title>
<script>try{var t=localStorage.getItem('tl-theme')||'auto';if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.dataset.theme='dark'}catch(e){}</script>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="login2">
  <aside class="brandside">
    <div class="lg"><img src="/assets/favicon.svg" alt="" width="44" height="44"><div><?= h(setting('company_name', 'TerraLink Systems')) ?><small>Client portal</small></div></div>
    <div><h2>Your IT, <em>in one place</em>.</h2><p>Open and follow support tickets, check your equipment and service visits, and see quotes and invoices, whenever it suits you.</p></div>
    <div class="ft">Need help now? <?= h(setting('company_phone', '778-648-2400')) ?> · <?= h(setting('company_email', 'info@terralinksystems.com')) ?></div>
  </aside>
  <main class="formside"><div class="box"><?= $inner ?></div></main>
</div>
</body>
</html>
