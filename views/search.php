<div class="pagehead"><div><h1>Search</h1><div class="sub"><?= $term !== '' ? 'Results for “' . h($term) . '”' : 'Type at least 2 characters in the search bar.' ?></div></div></div>
<?php
$sections = [
  'companies' => ['Companies', fn($r) => ['/companies/' . $r['id'], $r['name'], implode(' · ', array_filter([$r['industry'], $r['town']]))]],
  'contacts'  => ['Contacts', fn($r) => ['/contacts/' . $r['id'], contact_name($r), implode(' · ', array_filter([$r['company'], $r['email'], $r['mobile'] ?: $r['phone']]))]],
  'deals'     => ['Deals', fn($r) => ['/deals/' . $r['id'], $r['title'], STAGES[$r['stage']] . ($r['company'] ? ' · ' . $r['company'] : '')]],
  'tickets'   => ['Tickets', fn($r) => ['/tickets/' . $r['id'], $r['number'] . ' — ' . $r['subject'], TICKET_STATUS[$r['status']] . ' · ' . $r['company']]],
  'equipment' => ['Equipment', fn($r) => [$r['site_id'] ? '/sites/' . $r['site_id'] : '/equipment/' . $r['id'] . '/edit', trim($r['category'] . ' ' . $r['make'] . ' ' . $r['model']), implode(' · ', array_filter([$r['company'] ?? 'In stock' . ($r['stock_location'] ? ' (' . $r['stock_location'] . ')' : ''), $r['site'], $r['serial'] ? 'S/N ' . $r['serial'] : null, $r['mac'], $r['ip_address']]))]],
  'quotes'    => ['Quotes', fn($r) => ['/quotes/' . $r['id'], $r['number'] . ' — ' . $r['title'], QUOTE_STATUS[$r['status']] . ' · ' . $r['company']]],
];
$any = false;
?>
<div class="grid g2">
<?php foreach ($sections as $k => [$label, $fmt]): if (!$res[$k]) continue; $any = true; ?>
  <div class="card"><h2><?= $label ?> <small>(<?= count($res[$k]) ?>)</small></h2>
    <ul class="list"><?php foreach ($res[$k] as $r): [$url, $main, $meta] = $fmt($r); ?>
      <li><div class="grow"><a href="<?= h($url) ?>"><?= h($main) ?></a><span class="meta"><?= h($meta) ?></span></div></li>
    <?php endforeach; ?></ul>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$any && $term !== ''): ?><div class="card"><div class="empty">Nothing matched. Try a serial number, phone number, or part of a name.</div></div><?php endif; ?>
