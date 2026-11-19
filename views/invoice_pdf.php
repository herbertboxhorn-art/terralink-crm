<?php
$logo = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(APP_ROOT . '/public/assets/favicon.svg'));
$addr = implode(', ', array_filter([$inv['c_address'], $inv['c_town'], $inv['c_province']]));
$draft = $inv['status'] === 'draft';
$gstNo = setting('gst_number');
?><!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 0.55in 0.6in 0.7in; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #14212c; }
  .bar { background: #0F1A24; color: #fff; padding: 16px 20px; border-bottom: 4px solid #FF7A1A; }
  .bar table { width: 100%; border-collapse: collapse; }
  .bar .co { font-size: 18pt; font-weight: bold; letter-spacing: .5px; }
  .bar .co span { color: #FF7A1A; }
  .bar .meta { text-align: right; font-size: 9.5pt; color: #c3d0db; line-height: 1.5; }
  .bar .meta b { color: #fff; font-size: 14pt; display: block; letter-spacing: 1px; }
  .two { width: 100%; margin: 22px 0 18px; border-collapse: collapse; }
  .two td { vertical-align: top; width: 50%; }
  .lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #5d6f7e; margin-bottom: 3px; }
  h1 { font-size: 15pt; margin: 0 0 14px; }
  table.items { width: 100%; border-collapse: collapse; }
  .items th { background: #eef1f4; text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .5px; padding: 7px 8px; color: #40525f; }
  .items td { padding: 8px; border-bottom: 1px solid #dde3e8; vertical-align: top; }
  .items .n { text-align: right; white-space: nowrap; }
  .items small { color: #5d6f7e; }
  .tot { width: 46%; margin-left: 54%; margin-top: 12px; border-collapse: collapse; }
  .tot td { padding: 4px 8px; }
  .tot td.n { text-align: right; }
  .tot .g td { border-top: 2px solid #14212c; font-weight: bold; font-size: 13pt; padding-top: 8px; }
  .notes { margin-top: 24px; padding: 12px 14px; background: #f3f5f7; border-left: 3px solid #FF7A1A; white-space: pre-wrap; }
  .terms { margin-top: 18px; font-size: 8.5pt; color: #5d6f7e; white-space: pre-wrap; }
  .sign { margin-top: 36px; width: 100%; border-collapse: collapse; }
  .sign td { width: 50%; padding-right: 30px; font-size: 9pt; color: #5d6f7e; }
  .sign .line { border-bottom: 1px solid #14212c; height: 30px; }
  .foot { position: fixed; bottom: -0.45in; left: 0; right: 0; text-align: center; font-size: 8pt; color: #8a9aa7; }
  .grp { margin-top: 14px; page-break-inside: avoid; }
  .grp .gh { font-weight: bold; font-size: 10pt; padding: 6px 8px; background: #eef1f4; }
  .grp .gs { font-weight: normal; color: #5d6f7e; font-size: 8.5pt; }
  .pay { margin-top: 22px; border: 2px solid #0F1A24; padding: 12px 16px; page-break-inside: avoid; }
  .pay h3 { margin: 0 0 6px; font-size: 11pt; text-transform: uppercase; letter-spacing: .8px; }
  .pay td { padding: 3px 10px 3px 0; vertical-align: top; font-size: 10pt; }
  .pay .no { margin-top: 6px; font-weight: bold; color: #b42318; }
  .draft { position: fixed; top: 38%; left: 0; right: 0; text-align: center; font-size: 90pt; color: rgba(255,122,26,.12); font-weight: bold; transform: rotate(-25deg); }
  .items td { padding: 5px 8px; }
  .two { margin: 14px 0 6px; }
  .grp { margin-top: 10px; }
  .pay { margin-top: 14px; padding: 10px 14px; }
  .notes { margin-top: 12px; padding: 8px 12px; }
</style></head>
<body>
<?php if ($draft): ?><div class="draft">DRAFT</div><?php endif; ?>
<div class="bar"><table><tr>
  <td><img src="<?= $logo ?>" width="34" height="34" style="vertical-align:middle;margin-right:8px"><span class="co"><?= h(setting('company_name', 'TerraLink Systems')) ?></span></td>
  <td class="meta"><b>INVOICE</b><?= h($inv['number'] ?: 'DRAFT') ?><br>Date <?= h(fdate($inv['issued_on'] ?: date('Y-m-d'))) ?><br>Due <?= h(fdate($inv['due_on'] ?: date('Y-m-d', strtotime('+' . (int)$inv['terms_days'] . ' days')))) ?> (Net <?= (int)$inv['terms_days'] ?>)</td>
</tr></table></div>

<table class="two"><tr>
  <td>
    <div class="lbl">Bill to</div>
    <b><?= h($inv['company']) ?></b><br>
    <?php if ($addr): ?><?= h($addr) ?><br><?php endif; ?>
    <?= h($inv['bill_to_email']) ?>
  </td>
  <td>
    <div class="lbl">From</div>
    <b><?= h(setting('company_name')) ?></b><br>
    <?= h(setting('company_address')) ?><br>
    <?= h(setting('company_phone')) ?> · <?= h(setting('company_email')) ?>
    <?php if ($gstNo): ?><br>GST # <?= h($gstNo) ?><?php endif; ?>
  </td>
</tr></table>

<?php foreach ($groups as $g): ?>
<div class="grp">
  <div class="gh"><?= h($g['title']) ?><?= $g['sub'] ? ' <span class="gs">· ' . h($g['sub']) . '</span>' : '' ?></div>
  <table class="items">
    <tbody>
    <?php foreach ($g['items'] as $i): ?>
      <tr>
        <td><?= h($i['description']) ?><?= $i['pst_applies'] ? '<br><small>PST applies</small>' : '' ?></td>
        <td class="n" style="width:16%"><?= h(qty_fmt($i['qty'])) ?> <?= h($i['unit']) ?></td>
        <td class="n" style="width:16%"><?= h(money($i['unit_price'])) ?></td>
        <td class="n" style="width:16%"><?= h(money(round((float)$i['qty'] * (float)$i['unit_price'], 2))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<table class="tot">
  <tr><td>Subtotal</td><td class="n"><?= h(money($tot['subtotal'])) ?></td></tr>
  <tr><td>GST <?= qty_fmt($inv['gst_rate']) ?>%</td><td class="n"><?= h(money($tot['gst'])) ?></td></tr>
  <?php if ($tot['pst'] > 0): ?><tr><td>PST <?= qty_fmt($inv['pst_rate']) ?>%</td><td class="n"><?= h(money($tot['pst'])) ?></td></tr><?php endif; ?>
  <tr class="g"><td>Amount due (CAD)</td><td class="n"><?= h(money($tot['total'])) ?></td></tr>
</table>

<?php if ($inv['notes']): ?><div class="notes"><?= h($inv['notes']) ?></div><?php endif; ?>

<div class="pay">
  <h3>How to pay</h3>
  <table>
    <tr><td><b>Interac e-Transfer</b></td><td>Send to <b><?= h(setting('payment_email', 'payment@terralinksystems.com')) ?></b> and put <b><?= h($inv['number'] ?: 'the invoice number') ?></b> in the message.</td></tr>
    <tr><td><b>EFT / direct deposit</b></td><td><?= setting('eft_details') ? nl2br(h(setting('eft_details'))) : 'Contact us at ' . h(setting('payment_email', 'payment@terralinksystems.com')) . ' for our banking details.' ?></td></tr>
    <tr><td><b>Credit card</b></td><td><?= setting('cc_details') ? nl2br(h(setting('cc_details'))) : 'Call us at ' . h(setting('company_phone')) . '.' ?></td></tr>
  </table>
  <div class="no">We do not accept cheques.</div>
</div>
<?php if (setting('invoice_notes')): ?><div class="terms"><?= h(setting('invoice_notes')) ?></div><?php endif; ?>

<div class="foot"><?= h(setting('company_name')) ?> · <?= h(setting('company_address')) ?> · <?= h(setting('company_phone')) ?> · <?= h(setting('company_email')) ?></div>
</body></html>
