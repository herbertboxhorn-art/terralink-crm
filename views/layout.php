<?php
$u = user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$active = fn(string $p) => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' class="on"' : '';
$openTickets = (int)val("SELECT COUNT(*) FROM tickets WHERE status <> 'closed'");
$clientWait = (int)val("SELECT COUNT(*) FROM tickets WHERE status <> 'closed' AND client_waiting_at IS NOT NULL");
$myTasks = (int)val('SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND assigned_to = ? AND (due_date IS NULL OR due_date <= CURDATE())', [uid()]);
$myDay = $myTasks + (int)val("SELECT COUNT(*) FROM tickets WHERE status <> 'closed' AND assigned_to = ?", [uid()]);
$lowStock = (int)val('SELECT COUNT(*) FROM consumables WHERE active = 1 AND reorder_level IS NOT NULL AND qty_on_hand <= reorder_level');
$isTech = is_role('tech');
$f = flash();
$icon = fn(string $n) => '<svg class="i" aria-hidden="true"><use href="/assets/icons.svg#' . $n . '"/></svg>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F1A24">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TerraLink">
<title><?= h($title ?? 'CRM') ?> · TerraLink CRM</title>
<script>try{var t=localStorage.getItem('tl-theme')||'auto';if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.dataset.theme='dark'}catch(e){}</script>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/assets/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="30" height="30"><span>TerraLink <b>CRM</b></span></a>
  <form class="gsearch" action="/search" method="get" role="search">
    <?= $icon('search') ?><input type="search" name="q" placeholder="Search companies, people, serials, tickets…" value="<?= h($_GET['q'] ?? '') ?>" aria-label="Search">
  </form>
  <details class="usermenu">
    <summary aria-label="Account menu"><?= avatar($u['name'], $u['id'], 'lg') ?></summary>
    <div class="menu">
      <div class="who"><b><?= h($u['name']) ?></b><small><?= h(ROLES[$u['role']]) ?></small></div>
      <a href="/account">My password</a>
      <?php if (is_role('admin')): $errN = (int)val('SELECT COUNT(*) FROM app_errors WHERE last_at > NOW() - INTERVAL 7 DAY'); ?><a href="/users">Users</a><a href="/settings">Settings</a><a href="/errors">Error log<?= $errN ? ' (' . $errN . ')' : '' ?></a><?php if (function_exists('h_data')): ?><a href="/data">Import &amp; export</a><?php endif; ?><?php endif; ?>
      <div class="themepick" role="group" aria-label="Theme"><button type="button" data-theme-set="light">Light</button><button type="button" data-theme-set="auto">Auto</button><button type="button" data-theme-set="dark">Dark</button></div>
      <form method="post" action="/logout"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </div>
  </details>
</header>

<nav class="sidenav" aria-label="Main">
  <a href="/today"<?= $active('/today') ?>><?= $icon('pin') ?>My day<?php if ($myDay): ?><em><?= $myDay ?></em><?php endif; ?></a>
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?>Dashboard</a>
  <a href="/pipeline"<?= $active('/pipeline') . $active('/deals') ?>><?= $icon('pipeline') ?>Pipeline</a>
  <a href="/companies"<?= $active('/companies') ?>><?= $icon('building') ?>Companies</a>
  <a href="/contacts"<?= $active('/contacts') ?>><?= $icon('user') ?>Contacts</a>
  <a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?>Tickets<?php if ($clientWait): ?><em class="cw" title="<?= $clientWait ?> client<?= $clientWait === 1 ? '' : 's' ?> waiting for a reply" onclick="location.href='/tickets?f=client';return false"><?= $clientWait ?></em><?php endif; ?><?php if ($openTickets): ?><em<?= $clientWait ? ' style="margin-left:4px"' : '' ?>><?= $openTickets ?></em><?php endif; ?></a>
  <a href="/tasks"<?= $active('/tasks') ?>><?= $icon('check') ?>Tasks<?php if ($myTasks): ?><em><?= $myTasks ?></em><?php endif; ?></a>
  <a href="/equipment"<?= $active('/equipment') . $active('/sites') . $active('/consumables') ?>><?= $icon('router') ?>Inventory<?php if ($lowStock): ?><em title="Low stock"><?= $lowStock ?></em><?php endif; ?></a>
  <?php if (!$isTech): ?><a href="/quotes"<?= $active('/quotes') ?>><?= $icon('doc') ?>Quotes</a><?php endif; ?>
  <a href="/catalog"<?= $active('/catalog') ?>><?= $icon('tag') ?>Price list</a>
  <?php if (function_exists('h_plans')): ?><a href="/plans"<?= $active('/plans') ?>><?= $icon('repeat') ?>Service plans</a><?php endif; ?>
  <?php if (is_role('admin', 'sales')): $draftInv = (int)val("SELECT COUNT(*) FROM invoices WHERE status = 'draft'"); ?><a href="/invoices"<?= $active('/invoices') . $active('/billing') ?>><?= $icon('doc') ?>Invoices<?php if ($draftInv): ?><em title="Drafts to review"><?= $draftInv ?></em><?php endif; ?></a><?php endif; ?>
  <div class="ver">v<?= APP_VERSION ?></div>
</nav>

<main class="main">
  <?php if ($f): ?><div class="flash <?= h($f['type']) ?>" role="status"><?= h($f['msg']) ?></div><?php endif; ?>
  <?= $content ?>
</main>

<nav class="tabbar" aria-label="Quick">
  <?php if ($isTech): ?>
  <a href="/today"<?= $active('/today') ?>><?= $icon('pin') ?><span>My day</span><?php if ($myDay): ?><em><?= $myDay ?></em><?php endif; ?></a>
  <a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?><span>Tickets</span><?php if ($openTickets): ?><em><?= $openTickets ?></em><?php endif; ?></a>
  <a href="/equipment"<?= $active('/equipment') ?>><?= $icon('router') ?><span>Inventory</span></a>
  <a href="/companies"<?= $active('/companies') ?>><?= $icon('building') ?><span>Clients</span></a>
  <?php else: ?>
  <a href="/"<?= $active('/') ?>><?= $icon('home') ?><span>Home</span></a>
  <a href="/today"<?= $active('/today') ?>><?= $icon('pin') ?><span>My day</span><?php if ($myDay): ?><em><?= $myDay ?></em><?php endif; ?></a>
  <a href="/pipeline"<?= $active('/pipeline') ?>><?= $icon('pipeline') ?><span>Pipeline</span></a>
  <a href="/tickets"<?= $active('/tickets') ?>><?= $icon('ticket') ?><span>Tickets</span><?php if ($openTickets): ?><em><?= $openTickets ?></em><?php endif; ?></a>
  <?php endif; ?>
  <button type="button" class="more" data-toggle-nav><?= $icon('menu') ?><span>More</span></button>
</nav>
<div class="scrim" data-toggle-nav></div>
<script src="/assets/app.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
