<div class="pagehead">
  <div><h1>Users</h1><div class="sub">Admins manage everything · Sales and Tech can't delete companies, tickets or users · Two-step sign-in: <a href="/settings#security"><?= h(TWOFA_POLICIES[setting('twofa_policy', 'optional')] ?? 'Optional') ?></a></div></div>
  <div class="actions"><a class="btn primary" href="/users/new">+ New user</a></div>
</div>
<div class="card"><div class="tablewrap"><table class="t cards">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2-step</th><th>Status</th><th>Last login</th><th></th></tr></thead>
  <tbody><?php foreach ($users as $u): ?>
    <tr<?= $u['active'] ? '' : ' style="opacity:.55"' ?>>
      <td><?= avatar($u['name'], $u['id']) ?> <b><?= h($u['name']) ?></b></td>
      <td><?= h($u['email']) ?></td>
      <td><span class="pill ind"><?= h(ROLES[$u['role']]) ?></span></td>
      <td data-l="2-step"><?= twofa_enabled($u) ? '<span class="pill active">On</span>' : (twofa_required($u) ? '<span class="pill urgent">Required, not set up</span>' : '<span class="pill">Off</span>') ?></td>
      <td><span class="pill <?= $u['active'] ? 'active' : 'retired' ?>"><?= $u['active'] ? 'Active' : 'Disabled' ?></span></td>
      <td data-l="Last login"><?= $u['last_login_at'] ? h(ago($u['last_login_at'])) : 'Never' ?></td>
      <td class="num"><a href="/users/<?= (int)$u['id'] ?>/edit">Edit</a>
        <?php if ($u['active'] && !$u['last_login_at']): ?> · <form method="post" action="/users/<?= (int)$u['id'] ?>/invite" style="display:inline"><?= csrf_field() ?><button class="linkbtn" style="color:var(--info)" type="submit">Resend invite</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div></div>
