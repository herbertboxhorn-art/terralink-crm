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
    <div class="field wide"><label>Ticket response targets (hours until someone must respond)</label>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
        <?php foreach (['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $k => $l): ?>
        <label style="font-weight:500;font-size:13px"><?= $l ?><input type="number" min="1" name="sla_<?= $k ?>" value="<?= h($s['sla_' . $k] ?? '') ?>" style="width:100%;min-height:42px;border:1px solid var(--line-strong);border-radius:8px;padding:0 10px;background:var(--input);color:var(--ink)"></label>
        <?php endforeach; ?>
      </div></div>
    <div class="field wide"><label for="s_mail">Email service URL (n8n "TerraLink CRM — Outbound Email" webhook)</label><input id="s_mail" name="mail_webhook_url" value="<?= h($s['mail_webhook_url'] ?? '') ?>"></div>
    <div class="field wide"><label for="s_loc">Stock locations (one per line, first is the default)</label><textarea id="s_loc" name="stock_locations" rows="3" placeholder="Shop&#10;Truck 1&#10;Truck 2"><?= h($s['stock_locations'] ?? 'Shop') ?></textarea></div>
    <div class="field wide"><label for="s_terms">Quote terms (printed at the bottom of every quote)</label><textarea id="s_terms" name="quote_terms" rows="4"><?= h($s['quote_terms'] ?? '') ?></textarea></div>
    <div class="field wide" id="invoices" style="border-top:1px solid var(--line);padding-top:14px;margin-top:4px"><label style="font-size:16px">Invoices</label>
      <span class="muted" style="font-size:13px">Draft invoices are made automatically on the 1st of each month. You approve each one, then it's emailed to the client.</span></div>
    <div class="field"><label for="s_pay">E-Transfer email (on every invoice)</label><input id="s_pay" type="email" name="payment_email" value="<?= h($s['payment_email'] ?? 'payment@terralinksystems.com') ?>"></div>
    <div class="field"><label for="s_terms_d">Standard payment terms (days)</label><input id="s_terms_d" type="number" min="0" max="120" name="invoice_terms_days" value="<?= h($s['invoice_terms_days'] ?? '15') ?>"></div>
    <div class="field"><label for="s_rev">Send the "drafts ready to review" email to</label><input id="s_rev" type="email" name="invoice_review_email" placeholder="Empty = the first admin" value="<?= h($s['invoice_review_email'] ?? '') ?>"></div>
    <div class="field wide"><label for="s_eft">EFT / direct deposit details (printed on invoices)</label><textarea id="s_eft" name="eft_details" rows="3" placeholder="e.g. Bank: … · Transit: … · Institution: … · Account: …&#10;Empty = &quot;contact us for banking details&quot;"><?= h($s['eft_details'] ?? '') ?></textarea></div>
    <div class="field wide"><label for="s_cc">Credit card payment (printed on invoices)</label><textarea id="s_cc" name="cc_details" rows="2" placeholder="e.g. Pay online at … or call 778-648-2400&#10;Empty = &quot;call us&quot; with the company phone"><?= h($s['cc_details'] ?? '') ?></textarea></div>
    <div class="field check wide"><input type="hidden" name="invoice_reminders" value="0"><label><input type="checkbox" name="invoice_reminders" value="1"<?= ($s['invoice_reminders'] ?? '1') === '1' ? ' checked' : '' ?>> Email overdue reminders automatically, 3 and 14 days after the due date (you get a summary; turn off per company on its Edit page)</label></div>
    <div class="field wide"><label for="s_inote">Invoice footer note (optional)</label><textarea id="s_inote" name="invoice_notes" rows="2" placeholder="e.g. Late payments may be charged 2% per month."><?= h($s['invoice_notes'] ?? '') ?></textarea></div>
    <h2 id="security" style="grid-column:1/-1;margin:18px 0 0">Security</h2>
    <div class="field"><label for="s_2fa">Two-step sign-in (staff)</label><select id="s_2fa" name="twofa_policy">
      <?php foreach (TWOFA_POLICIES as $k => $l): ?><option value="<?= $k ?>"<?= ($s['twofa_policy'] ?? 'optional') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Who has it on</label><div><?php $on = (int)val('SELECT COUNT(*) FROM users WHERE active = 1 AND totp_enabled_at IS NOT NULL'); $all = (int)val('SELECT COUNT(*) FROM users WHERE active = 1'); ?><?= $on ?> of <?= $all ?> active users · <a href="/users">Users</a><br><span class="muted" style="font-size:13px">When required, people without it are sent to set it up at their next page view. You can also require it per user (Users → Edit).</span></div></div>
    <h2 id="portal" style="grid-column:1/-1;margin:18px 0 0">Client portal</h2>
    <div class="field"><label for="s_pa">New portal tickets go to</label><select id="s_pa" name="portal_assignee">
      <?php foreach (user_opts() as $k => $l): ?><option value="<?= h($k) ?>"<?= (string)$k === (string)($s['portal_assignee'] ?? '') ? ' selected' : '' ?>><?= h($k === '' ? '— Nobody (admins get the email) —' : $l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Portal address</label><div><a href="<?= h(portal_url()) ?>" target="_blank" rel="noopener"><?= h(portal_url()) ?> ↗</a><br><span class="muted" style="font-size:13px">Add logins on each company's Client portal tab.</span></div></div>
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

<div class="card" id="email" style="max-width:860px;margin-top:18px">
  <div class="cardhead"><h2>Recent emails</h2>
    <form method="post" action="/settings"><?= csrf_field() ?><input type="hidden" name="test_email" value="1"><button class="btn sm" type="submit">Send me a test email</button></form></div>
  <?php if (!$mails): ?><div class="empty">No emails sent yet.</div><?php else: ?>
  <div class="tablewrap"><table class="t cards"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($mails as $m): ?><tr><td data-l="When"><?= h(ago($m['created_at'])) ?></td><td><?= h($m['to_addr']) ?></td><td><?= h($m['subject']) ?></td>
    <td><span class="pill <?= $m['status'] === 'sent' ? 'won' : 'lost' ?>"><?= h($m['status']) ?></span><?= $m['error'] ? '<br><small class="muted">' . h($m['error']) . '</small>' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
