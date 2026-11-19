<div class="pagehead"><div><a class="crumb" href="/equipment?tab=stock">← In stock</a><h1>Install at a site</h1>
  <div class="sub"><b><?= h(equip_label($e)) ?></b><?= $e['stock_location'] ? ' · from ' . h($e['stock_location']) : '' ?></div></div></div>
<div class="card" style="max-width:640px">
  <form method="post" class="form" style="grid-template-columns:1fr" data-dependent novalidate><?= csrf_field() ?>
    <div class="field"><label for="f_company_id">Company</label>
      <select id="f_company_id" name="company_id" required><?php foreach (company_opts() as $k => $l): ?><option value="<?= h($k) ?>"<?= (string)$k === (string)$cid ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f_site_id">Site</label>
      <select id="f_site_id" name="site_id" required><?php foreach (site_opts($cid) as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?></select>
      <?= isset($errors['site_id']) ? '<div class="err">' . h($errors['site_id']) . '</div>' : '' ?>
      <small class="muted">No site yet? Open the company and add a site first.</small></div>
    <div class="field"><label>Installed on</label><input type="date" name="installed_at" value="<?= date('Y-m-d') ?>"></div>
    <div class="formbar"><button class="btn primary" type="submit">Install</button><a class="btn" href="/equipment?tab=stock">Cancel</a></div>
  </form>
</div>
