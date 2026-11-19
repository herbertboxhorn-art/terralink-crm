<?php
$logo = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(APP_ROOT . '/public/assets/favicon.svg'));
$addr = implode(', ', array_filter([$qt['c_address'], $qt['c_town'], $qt['c_province']]));
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
</style></head>
<body>
<div class="bar"><table><tr>
  <td><img src="<?= $logo ?>" width="34" height="34" style="vertical-align:middle;margin-right:8px"><span class="co"><?= h(setting('company_name', 'TerraLink Systems')) ?></span></td>
  <td class="meta"><b>QUOTE</b><?= h($qt['number']) ?><br>Issued <?= h(fdate($qt['issued_on'])) ?><?php if ($qt['valid_until']): ?><br>Valid until <?= h(fdate($qt['valid_until'])) ?><?php endif; ?></td>
</tr></table></div>

<table class="two"><tr>
  <td>
    <div class="lbl">Prepared for</div>
    <b><?= h($qt['company']) ?></b><br>
    <?php if ($qt['first_name']): ?>Attn: <?= h(trim($qt['first_name'] . ' ' . $qt['last_name'])) ?><br><?php endif; ?>
    <?php if ($addr): ?><?= h($addr) ?><br><?php endif; ?>
    <?= h($qt['ct_email'] ?: $qt['c_email']) ?>
  </td>
  <td>
    <div class="lbl">From</div>
    <b><?= h(setting('company_name')) ?></b><br>
    <?= h(setting('company_address')) ?><br>
    <?= h(setting('company_phone')) ?> · <?= h(setting('company_email')) ?>
    <?php if ($gstNo): ?><br>GST # <?= h($gstNo) ?><?php endif; ?>
    <?php if ($qt['author']): ?><br>Prepared by <?= h($qt['author']) ?><?php endif; ?>
  </td>
</tr></table>

<h1><?= h($qt['title']) ?></h1>

<table class="items">
  <thead><tr><th>Description</th><th class="n">Qty</th><th class="n">Unit price</th><th class="n">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): ?>
    <tr>
      <td><?= h($i['description']) ?><br><small><?= h(ITEM_KINDS[$i['kind']]) ?><?= $i['pst_applies'] ? ' · PST applies' : '' ?></small></td>
      <td class="n"><?= h(rtrim(rtrim(number_format((float)$i['qty'], 2), '0'), '.')) ?> <?= h($i['unit']) ?></td>
      <td class="n"><?= h(money($i['unit_price'])) ?></td>
      <td class="n"><?= h(money($i['qty'] * $i['unit_price'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<table class="tot">
  <tr><td>Subtotal</td><td class="n"><?= h(money($tot['subtotal'])) ?></td></tr>
  <tr><td>GST <?= (float)$qt['gst_rate'] ?>%</td><td class="n"><?= h(money($tot['gst'])) ?></td></tr>
  <?php if ($tot['pst'] > 0): ?><tr><td>PST <?= (float)$qt['pst_rate'] ?>%</td><td class="n"><?= h(money($tot['pst'])) ?></td></tr><?php endif; ?>
  <tr class="g"><td>Total (CAD)</td><td class="n"><?= h(money($tot['total'])) ?></td></tr>
</table>

<?php if ($qt['notes']): ?><div class="notes"><?= h($qt['notes']) ?></div><?php endif; ?>
<?php if (setting('quote_terms')): ?><div class="terms"><?= h(setting('quote_terms')) ?></div><?php endif; ?>

<table class="sign"><tr>
  <td><div class="line"></div>Accepted by (name &amp; signature)</td>
  <td><div class="line"></div>Date</td>
</tr></table>

<div class="foot"><?= h(setting('company_name')) ?> · <?= h(setting('company_address')) ?> · <?= h(setting('company_phone')) ?> · <?= h(setting('company_email')) ?></div>
</body></html>
