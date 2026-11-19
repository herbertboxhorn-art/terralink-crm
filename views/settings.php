<?php
$fields = [
  'company_name' => ['Company name', 'text'], 'company_phone' => ['Phone', 'tel'], 'company_email' => ['Email', 'email'],
  'company_address' => ['Address (shown on quotes)', 'text'], 'gst_number' => ['GST / HST registration #', 'text'],
  'travel_rate_per_km' => ['Travel rate per km ($)', 'number'], 'quote_valid_days' => ['Quotes valid for (days)', 'number'],
];
?>
<div class="pagehead"><div><h1>Settings</h1><div class="sub">Used on quotes and PDFs</div></div></div>
<div class="card" style="max-width:860px">
  <form method="post" class="form"><?= csrf_field() ?>
    <?php foreach ($fields as $k => [$l, $t]): ?>
      <div class="field"><label for="s_<?= $k ?>"><?= h($l) ?></label><input id="s_<?= $k ?>" type="<?= $t ?>"<?= $t === 'number' ? ' step="0.01" min="0"' : '' ?> name="<?= $k ?>" value="<?= h($s[$k] ?? '') ?>"></div>
    <?php endforeach; ?>
    <div class="field wide"><label for="s_loc">Stock locations (one per line, first is the default)</label><textarea id="s_loc" name="stock_locations" rows="3" placeholder="Shop&#10;Truck 1&#10;Truck 2"><?= h($s['stock_locations'] ?? 'Shop') ?></textarea></div>
    <div class="field wide"><label for="s_terms">Quote terms (printed at the bottom of every quote)</label><textarea id="s_terms" name="quote_terms" rows="4"><?= h($s['quote_terms'] ?? '') ?></textarea></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Save settings</button></div>
  </form>
</div>

<div class="card" style="max-width:860px;margin-top:18px">
  <h2>Website leads API (for n8n)</h2>
  <p class="muted" style="margin:6px 0 12px">Add an HTTP Request node to the callback branch of the website-forms workflow. Use a Header Auth credential with the name <code>X-CRM-Key</code> and the value below.</p>
  <dl class="kv">
    <dt>URL</dt><dd class="mono">POST <?= h($apiUrl) ?></dd>
    <dt>Header</dt><dd class="mono">X-CRM-Key</dd>
    <dt>Key</dt><dd><?php if ($apiKey): ?><details><summary style="cursor:pointer">Show key</summary><code class="mono" style="word-break:break-all;user-select:all"><?= h($apiKey) ?></code></details><?php else: ?><span class="err">Not configured</span><?php endif; ?></dd>
  </dl>
</div>
