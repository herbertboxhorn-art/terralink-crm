<?php
$u = user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$active = fn(string $p) => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' class="on"' : '';
$openTickets = (int)val("SELECT COUNT(*) FROM tickets WHERE status <> 'closed'");
$myTasks = (int)val('SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND assigned_to = ? AND (due_date IS NULL OR due_date <= CURDATE())', [uid()]);
$f = flash();
$icon = fn(string $n) => '<svg class="i" aria-hidden="true"><use href="/assets/icons.svg#' . $n . '"/></svg>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F1A24">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($title ?? 'CRM') ?> · TerraLink CRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="28" height="28"><span>TerraLink <b>CRM</b></span></a>
  <form class="gsearch" action="/search" method="get" role="search">
    <?= $icon('search') ?><input type="search" name="q" placeholder="Search companies, people, serials, tickets…" value="<?= h($_GET['q'] ?? '') ?>" aria-label="Search">
  </form>
  <details class="usermenu">
    <summary aria-label="Account menu"><span class="avatar"><?= h(mb_substr($u['name'], 0, 1)) ?></span></summary>
    <div class="menu">
      <div class="who"><b><?= h($u['name']) ?></b><small><?= h(ROLES[$u['role']]) ?></small></div>
      <a href="/account">My password</a>
      <?php if (is_role('admin')): ?><a href="/users">Users</a><a href="/settings">Settings</a><?php endif; ?>
      <form method="post" action="/logout"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </div>
  </details>
</header>

<nav class="sidenav" aria-label="Main">
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?>Dashboard</a>
  <a href="/pipeline"<?= $active('/pipeline') . $active('/deals') ?>><?= $icon('pipeline') ?>Pipeline</a>
  <a href="/companies"<?= $active('/companies') ?>><?= $icon('building') ?>Companies</a>
  <a href="/contacts"<?= $active('/contacts') ?>><?= $icon('user') ?>Contacts</a>
  <a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?>Tickets<?php if ($openTickets): ?><em><?= $openTickets ?></em><?php endif; ?></a>
  <a href="/tasks"<?= $active('/tasks') ?>><?= $icon('check') ?>Tasks<?php if ($myTasks): ?><em><?= $myTasks ?></em><?php endif; ?></a>
  <a href="/equipment"<?= $active('/equipment') . $active('/sites') ?>><?= $icon('router') ?>Equipment</a>
  <a href="/quotes"<?= $active('/quotes') ?>><?= $icon('doc') ?>Quotes</a>
  <div class="ver">v<?= APP_VERSION ?></div>
</nav>

<main class="main">
  <?php if ($f): ?><div class="flash <?= h($f['type']) ?>" role="status"><?= h($f['msg']) ?></div><?php endif; ?>
  <?= $content ?>
</main>

<nav class="tabbar" aria-label="Quick">
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?><span>Home</span></a>
  <a href="/pipeline"<?= $active('/pipeline') ?>><?= $icon('pipeline') ?><span>Pipeline</span></a>
  <a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?><span>Tickets</span><?php if ($openTickets): ?><em><?= $openTickets ?></em><?php endif; ?></a>
  <a href="/tasks"<?= $active('/tasks') ?>><?= $icon('check') ?><span>Tasks</span><?php if ($myTasks): ?><em><?= $myTasks ?></em><?php endif; ?></a>
  <button type="button" class="more" data-toggle-nav><?= $icon('menu') ?><span>More</span></button>
</nav>
<div class="scrim" data-toggle-nav></div>
<script src="/assets/app.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
