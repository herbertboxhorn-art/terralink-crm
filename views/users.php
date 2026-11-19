<div class="pagehead">
  <div><h1>Users</h1><div class="sub">Admins manage everything · Sales and Tech can't delete companies, tickets or users</div></div>
  <div class="actions"><a class="btn primary" href="/users/new">+ New user</a></div>
</div>
<div class="card"><div class="tablewrap"><table class="t cards">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
  <tbody><?php foreach ($users as $u): ?>
    <tr<?= $u['active'] ? '' : ' style="opacity:.55"' ?>>
      <td><b><?= h($u['name']) ?></b></td>
      <td><?= h($u['email']) ?></td>
      <td><span class="pill ind"><?= h(ROLES[$u['role']]) ?></span></td>
      <td><span class="pill <?= $u['active'] ? 'active' : 'retired' ?>"><?= $u['active'] ? 'Active' : 'Disabled' ?></span></td>
      <td data-l="Last login"><?= $u['last_login_at'] ? h(ago($u['last_login_at'])) : 'Never' ?></td>
      <td class="num"><a href="/users/<?= (int)$u['id'] ?>/edit">Edit</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div></div>
