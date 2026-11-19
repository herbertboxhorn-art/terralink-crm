<div class="pagehead">
  <div><h1>Contacts</h1><div class="sub"><?= count($rows) ?> shown</div></div>
  <div class="actions"><a class="btn primary" href="/contacts/new"><svg class="i"><use href="/assets/icons.svg#plus"/></svg>New contact</a></div>
</div>
<form class="filters" method="get">
  <input type="search" name="q" value="<?= h($term) ?>" placeholder="Name, email or company…">
  <button class="btn" type="submit">Search</button>
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No contacts found.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards">
    <thead><tr><th>Name</th><th>Company</th><th>Title</th><th>Phone</th><th>Email</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td><a class="row" href="/contacts/<?= (int)$r['id'] ?>"><?= h(contact_name($r)) ?></a></td>
        <td><?php if ($r['company_id']): ?><a href="/companies/<?= (int)$r['company_id'] ?>"><?= h($r['company']) ?></a><?php endif; ?></td>
        <td><?= h($r['title']) ?></td>
        <td><?= tel($r['mobile'] ?: $r['phone']) ?></td>
        <td><?php if ($r['email']): ?><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
