<?php
$pu = portal_user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$active = fn(string $p) => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' class="on"' : '';
$icon = fn(string $n) => '<svg class="i" aria-hidden="true"><use href="/assets/icons.svg#' . $n . '"/></svg>';
$openT = 0;
if (portal_can('perm_tickets')) { [$w, $p] = portal_ticket_scope($pu); $openT = (int)val("SELECT COUNT(*) FROM tickets t WHERE $w AND t.status = 'waiting'", $p); }
$f = flash();
$coName = setting('company_name', 'TerraLink Systems');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F1A24">
<meta name="robots" content="noindex">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="TL Portal">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($title ?? 'Client portal') ?> · <?= h($coName) ?> client portal</title>
<script>try{var t=localStorage.getItem('tl-theme')||'auto';if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.dataset.theme='dark'}catch(e){}</script>
<link rel="icon" href="/assets/portal-favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/portal-apple-touch-icon.png">
<link rel="manifest" href="/portal.webmanifest">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body class="portal" data-idle="<?= idle_minutes('tlportal') * 60 ?>">
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="30" height="30"><span>TerraLink <b>Client portal</b></span></a>
  <div style="flex:1"></div>
  <details class="usermenu">
    <summary aria-label="Account menu"><?= avatar($pu['name'], $pu['id'], 'lg') ?></summary>
    <div class="menu">
      <div class="who"><b><?= h($pu['name']) ?></b><small><?= h($pu['company']) ?></small></div>
      <a href="/account">My account</a>
      <?php if (portal_can('is_admin')): ?><a href="/users">Portal users</a><?php endif; ?>
      <button type="button" data-install hidden>Install app on this device</button>
      <div class="themepick" role="group" aria-label="Theme"><button type="button" data-theme-set="light">Light</button><button type="button" data-theme-set="auto">Auto</button><button type="button" data-theme-set="dark">Dark</button></div>
      <form method="post" action="/logout"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </div>
  </details>
</header>

<nav class="sidenav" aria-label="Main">
  <div class="co"><?= h($pu['company']) ?><small>Signed in as <?= h($pu['name']) ?></small></div>
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?>Home</a>
  <?php if (portal_can('perm_tickets')): ?><a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?>Tickets<?php if ($openT): ?><em title="Waiting on you"><?= $openT ?></em><?php endif; ?></a><?php endif; ?>
  <?php if (portal_can('perm_sites')): ?><a href="/sites"<?= $active('/sites') ?>><?= $icon('router') ?>Sites &amp; equipment</a><?php endif; ?>
  <?php if (portal_can('perm_plans')): ?><a href="/plans"<?= $active('/plans') ?>><?= $icon('repeat') ?>Service plans</a><?php endif; ?>
  <?php if (portal_can('perm_quotes')): ?><a href="/quotes"<?= $active('/quotes') ?>><?= $icon('doc') ?>Quotes</a><?php endif; ?>
  <?php if (portal_can('perm_invoices')): ?><a href="/invoices"<?= $active('/invoices') ?>><?= $icon('tag') ?>Invoices</a><?php endif; ?>
  <?php if (portal_can('is_admin')): ?><a href="/users"<?= $active('/users') ?>><?= $icon('user') ?>Portal users</a><?php endif; ?>
  <a href="/account"<?= $active('/account') ?>><?= $icon('check') ?>My account</a>
  <div class="ver"><?= h($coName) ?> · <?= h(setting('company_phone')) ?></div>
</nav>

<main class="main">
  <?php if ($f): ?><div class="flash <?= h($f['type']) ?>" role="status"><?= h($f['msg']) ?></div><?php endif; ?>
  <?= $content ?>
</main>

<nav class="tabbar" aria-label="Quick">
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?><span>Home</span></a>
  <?php if (portal_can('perm_tickets')): ?><a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?><span>Tickets</span><?php if ($openT): ?><em><?= $openT ?></em><?php endif; ?></a>
  <a href="/tickets/new"><?= $icon('plus') ?><span>New ticket</span></a><?php endif; ?>
  <?php if (portal_can('perm_invoices')): ?><a href="/invoices"<?= $active('/invoices') ?>><?= $icon('tag') ?><span>Invoices</span></a><?php elseif (portal_can('perm_sites')): ?><a href="/sites"<?= $active('/sites') ?>><?= $icon('router') ?><span>Equipment</span></a><?php endif; ?>
  <button type="button" class="more" data-toggle-nav><?= $icon('menu') ?><span>More</span></button>
</nav>
<div class="scrim" data-toggle-nav></div>
<script src="/assets/app.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
