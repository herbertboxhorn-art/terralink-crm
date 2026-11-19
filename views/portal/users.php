<div class="pagehead">
  <div><h1>Portal users</h1><div class="sub">People at <?= h(portal_user()['company']) ?> who can sign in, and what each one can see.</div></div>
  <div class="actions"><a class="btn primary" href="/users/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>Add a colleague</a></div>
</div>
<div class="card">
  <ul class="list"><?php foreach ($users as $u): $me = (int)$u['id'] === pu_id(); ?>
    <li><?= avatar($u['name'], $u['id']) ?>
      <div class="grow"><b><?= h($u['name']) ?></b><?= $me ? ' <span class="pill">You</span>' : '' ?><?= $u['is_admin'] ? ' <span class="pill new">Admin</span>' : '' ?><?= !$u['active'] ? ' <span class="pill">Disabled</span>' : '' ?>
        <span class="meta"><?= h($u['email']) ?> · <?= h(portal_perm_summary($u)) ?></span>
        <span class="meta"><?= $u['last_login_at'] ? 'Last signed in ' . h(ago($u['last_login_at'])) : 'Hasn\'t signed in yet' ?></span></div>
      <?php if (!$me): ?>
        <?php if ($u['active'] && !$u['last_login_at']): ?><form method="post" action="/users/<?= (int)$u['id'] ?>/invite"><?= csrf_field() ?><button class="btn sm ghost" type="submit">Resend invite</button></form><?php endif; ?>
        <a class="btn sm" href="/users/<?= (int)$u['id'] ?>/edit">Edit</a>
      <?php endif; ?></li>
  <?php endforeach; ?></ul>
</div>
<p class="muted" style="font-size:13px">You can only give colleagues access you have yourself. To remove someone completely, contact <?= h(setting('company_name')) ?>.</p>
