<?php if ($preview): $by = array_count_values(array_column($preview, 'action')); ?>
<div class="pagehead"><div><h1>Import preview</h1><div class="sub"><?= h($fileName) ?> · <?= count($preview) ?> rows</div></div></div>

<div class="stats">
  <div class="stat"><small>New companies</small><b><?= (int)($by['new'] ?? 0) ?></b><span>will be created</span></div>
  <div class="stat"><small>Contacts added</small><b><?= count(array_filter($preview, fn($r) => in_array($r['action'], ['new', 'add'], true) && ($r['first_name'] !== ''))) ?></b><span>to new or existing companies</span></div>
  <div class="stat"><small>Skipped</small><b><?= (int)($by['skip'] ?? 0) ?></b><span>already in the CRM</span></div>
  <div class="stat<?= !empty($by['err']) ? ' hot' : '' ?>"><small>Problems</small><b><?= (int)($by['err'] ?? 0) ?></b><span>not imported</span></div>
</div>

<div class="card">
  <p style="margin:0 0 10px"><b>Columns recognized:</b> <?= h(implode(', ', $found)) ?><?php if ($unused): ?><br><span class="muted">Ignored columns: <?= h(implode(', ', $unused)) ?></span><?php endif; ?></p>
  <div class="tablewrap"><table class="t cards imp">
    <thead><tr><th>Line</th><th>What happens</th><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Town</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($preview, 0, 500) as $r): ?>
      <tr class="<?= h($r['action']) ?>">
        <td class="muted"><?= (int)$r['line'] ?></td>
        <td class="act"><?= h($r['why']) ?></td>
        <td><?= h($r['company'] ?: '—') ?></td>
        <td data-l="Contact"><?= h(trim($r['first_name'] . ' ' . $r['last_name']) ?: '—') ?><?= $r['title'] ? ' <span class="muted">· ' . h($r['title']) . '</span>' : '' ?></td>
        <td data-l="Email"><?= h($r['email'] ?: '—') ?></td>
        <td data-l="Phone"><?= h($r['mobile'] ?: $r['phone'] ?: $r['c_phone'] ?: '—') ?></td>
        <td data-l="Town"><?= h($r['town'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (count($preview) > 500): ?><p class="muted">Showing the first 500 of <?= count($preview) ?> rows; all of them will be imported.</p><?php endif; ?>
  <div class="formbar" style="margin-top:14px">
    <?php if (($by['new'] ?? 0) + ($by['add'] ?? 0) > 0): ?>
    <form method="post" action="/data/import/confirm"><?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
      <button class="btn primary" type="submit">Import <?= ($by['new'] ?? 0) + ($by['add'] ?? 0) ?> rows</button></form>
    <?php else: ?><b>Nothing new to import.</b><?php endif; ?>
    <a class="btn" href="/data#import">Cancel</a>
  </div>
</div>

<?php else: ?>
<div class="pagehead"><div><h1>Import &amp; export</h1><div class="sub">Spreadsheets in and out, plus full backups</div></div></div>

<div class="card" id="export">
  <div class="cardhead"><h2>Export to spreadsheet (CSV)</h2></div>
  <p class="muted" style="margin:0 0 12px">Opens in Excel, Numbers or Google Sheets.</p>
  <div class="datagrid">
    <?php foreach ($defs as $k => [$label]): ?>
      <a class="btn" href="/data/export/<?= h($k) ?>"><span><?= h($label) ?></span><span class="muted"><?= (int)($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" id="import">
  <div class="cardhead"><h2>Import companies &amp; contacts</h2><a href="/data/template">Download template →</a></div>
  <p style="margin:0 0 12px">One row per contact. Rows with the same company name end up under one company. Existing companies are matched by name, and contacts with an email already in the CRM are skipped. <b>You'll see a preview before anything is saved.</b></p>
  <p class="muted" style="margin:0 0 12px">Recognized columns: Company, Industry, Company phone, Company email, Website, Address, Town, Province, First name, Last name (or Name), Job title, Phone, Mobile, Email, Notes. Exports from Outlook, Google Contacts or a spreadsheet work too.</p>
  <form method="post" action="/data/import" enctype="multipart/form-data" class="upload" style="border:0;padding:0">
    <?= csrf_field() ?>
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button class="btn primary" type="submit">Preview import</button>
  </form>
</div>

<div class="card" id="backup">
  <div class="cardhead"><h2>Backup</h2></div>
  <p style="margin:0 0 12px">Last downloaded: <b><?= $lastBackup ? h(fdate($lastBackup, 'M j, Y g:i a')) . ' (' . h(ago($lastBackup)) . ')' : 'never' ?></b>. Keep a copy off the server, for example in Google Drive, about once a month.</p>
  <div class="actions">
    <a class="btn dark" href="/data/backup">Download database (.sql.gz)</a>
    <a class="btn" href="/data/files">Download photos &amp; files (.zip)</a>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:13px"><?= (int)$files['n'] ?> uploaded file<?= (int)$files['n'] === 1 ? '' : 's' ?><?= $files['n'] ? ', ' . h(file_size_fmt((int)$files['bytes'])) : '' ?></p>
  <p class="muted" style="margin:12px 0 0;font-size:13px">To restore the database: hPanel → Databases → phpMyAdmin → choose <code>u300896537_terralink_crm</code> → Import → pick the .sql.gz file. The backup includes user logins (hashed passwords), so store it somewhere private.</p>
</div>
<?php endif; ?>
