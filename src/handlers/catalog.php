<?php
declare(strict_types=1);
/**
 * Price list (catalog_items) + stock for quotes.
 * A price-list item can be linked to stock: an equipment model (category/make/model, counted per serial)
 * or a consumable (counted by quantity). Quotes can then show what's in stock and reserve it.
 */

// ---------- stock helpers ----------
/** Stock key for a price-list row: 'e:<cat>|<make>|<model>', 'c:<consumable id>' or null */
function stock_key(array $c): ?string {
    if (($c['stock_type'] ?? 'none') === 'equipment' && ($c['eq_model'] ?? '') !== '') return 'e:' . $c['eq_category'] . '|' . $c['eq_make'] . '|' . $c['eq_model'];
    if (($c['stock_type'] ?? 'none') === 'consumable' && !empty($c['consumable_id'])) return 'c:' . (int)$c['consumable_id'];
    return null;
}

/** Free (unreserved) stock for a key, not counting what $quoteId itself holds */
function stock_free(string $key): float {
    if (str_starts_with($key, 'e:')) {
        [$cat, $make, $model] = explode('|', substr($key, 2), 3);
        return (float)val("SELECT COUNT(*) FROM equipment WHERE site_id IS NULL AND status = 'in_stock' AND reserved_quote_id IS NULL
                           AND category = ? AND IFNULL(make,'') = ? AND model = ?", [$cat, $make, $model]);
    }
    $id = (int)substr($key, 2);
    return (float)val('SELECT c.qty_on_hand - COALESCE((SELECT SUM(r.qty) FROM consumable_reservations r WHERE r.consumable_id = c.id), 0)
                       FROM consumables c WHERE c.id = ?', [$id]);
}

function stock_held(string $key, int $quoteId): float {
    if (str_starts_with($key, 'e:')) {
        [$cat, $make, $model] = explode('|', substr($key, 2), 3);
        return (float)val("SELECT COUNT(*) FROM equipment WHERE reserved_quote_id = ? AND site_id IS NULL AND category = ? AND IFNULL(make,'') = ? AND model = ?", [$quoteId, $cat, $make, $model]);
    }
    return (float)val('SELECT COALESCE(SUM(qty), 0) FROM consumable_reservations WHERE quote_id = ? AND consumable_id = ?', [$quoteId, (int)substr($key, 2)]);
}

/** Per stock key on a quote: need, held (reserved for this quote), free (others can take), label, unit */
function quote_stock(int $quoteId): array {
    $rows = all("SELECT qi.qty, ci.* FROM quote_items qi JOIN catalog_items ci ON ci.id = qi.catalog_id WHERE qi.quote_id = ? AND ci.stock_type <> 'none'", [$quoteId]);
    $out = [];
    foreach ($rows as $r) {
        $k = stock_key($r);
        if (!$k) continue;
        $out[$k] ??= ['key' => $k, 'label' => str_starts_with($k, 'e:') ? trim($r['eq_make'] . ' ' . $r['eq_model']) : (string)val('SELECT name FROM consumables WHERE id = ?', [(int)$r['consumable_id']]),
                      'unit' => str_starts_with($k, 'e:') ? 'ea' : (string)val('SELECT unit FROM consumables WHERE id = ?', [(int)$r['consumable_id']]), 'need' => 0.0];
        $out[$k]['need'] += str_starts_with($k, 'e:') ? ceil((float)$r['qty']) : (float)$r['qty'];
    }
    foreach ($out as $k => &$s) { $s['held'] = stock_held($k, $quoteId); $s['free'] = stock_free($k); }
    return $out;
}

/** Reserves what's available for every stocked line. Returns a short summary. */
function quote_reserve(int $quoteId): string {
    $done = []; $short = [];
    foreach (quote_stock($quoteId) as $k => $s) {
        $want = $s['need'] - $s['held'];
        if ($want <= 0) continue;
        $take = min($want, max(0, $s['free']));
        if (str_starts_with($k, 'e:')) {
            [$cat, $make, $model] = explode('|', substr($k, 2), 3);
            $n = (int)$take;
            if ($n > 0) q("UPDATE equipment SET reserved_quote_id = ? WHERE site_id IS NULL AND status = 'in_stock' AND reserved_quote_id IS NULL
                           AND category = ? AND IFNULL(make,'') = ? AND model = ? ORDER BY purchased_at IS NULL, purchased_at, id LIMIT $n", [$quoteId, $cat, $make, $model]);
            $take = $n;
        } elseif ($take > 0) {
            q('INSERT INTO consumable_reservations (quote_id, consumable_id, qty, created_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
              [$quoteId, (int)substr($k, 2), $take, uid()]);
        }
        if ($take > 0) $done[] = qty_fmt($take) . ' × ' . $s['label'];
        if ($take < $want) $short[] = $s['label'] . ' (short ' . qty_fmt($want - $take) . ')';
    }
    $qt = one('SELECT number, company_id FROM quotes WHERE id = ?', [$quoteId]);
    if ($done) log_activity('system', 'quote', $quoteId, 'Stock reserved: ' . implode(', ', $done), (int)$qt['company_id']);
    return ($done ? 'Reserved ' . implode(', ', $done) . '.' : 'Nothing new to reserve.') . ($short ? ' Not enough stock: ' . implode(', ', $short) . '.' : '');
}

function quote_release(int $quoteId, string $why = ''): int {
    $n = (int)q('UPDATE equipment SET reserved_quote_id = NULL WHERE reserved_quote_id = ?', [$quoteId])->rowCount();
    $n += (int)q('DELETE FROM consumable_reservations WHERE quote_id = ?', [$quoteId])->rowCount();
    if ($n) {
        $qt = one('SELECT company_id FROM quotes WHERE id = ?', [$quoteId]);
        if ($qt) log_activity('system', 'quote', $quoteId, 'Stock reservation released' . ($why ? " ($why)" : ''), (int)$qt['company_id']);
    }
    return $n;
}

function h_quote_reserve(int $id): void {
    if (!one('SELECT id FROM quotes WHERE id = ?', [$id])) not_found();
    flash(quote_reserve($id));
    redirect("/quotes/$id#stock");
}

function h_quote_release(int $id): void {
    if (!one('SELECT id FROM quotes WHERE id = ?', [$id])) not_found();
    $n = quote_release($id, 'by hand');
    flash($n ? 'Reservation released. The stock is free again.' : 'Nothing was reserved.');
    redirect("/quotes/$id#stock");
}

/** Price list as JSON for the quote editor (with free stock) */
function catalog_for_picker(int $quoteId = 0): array {
    $out = [];
    foreach (all('SELECT * FROM catalog_items WHERE active = 1 ORDER BY name') as $c) {
        $k = stock_key($c);
        $out[] = ['id' => (int)$c['id'], 'sku' => $c['sku'], 'name' => $c['name'], 'description' => $c['description'] ?: $c['name'], 'kind' => $c['kind'],
                  'unit' => $c['unit'] ?? '', 'unit_price' => number_format((float)$c['price'], 2, '.', ''), 'pst' => (int)$c['pst_applies'],
                  'stock' => $k ? stock_free($k) + ($quoteId ? stock_held($k, $quoteId) : 0) : null];
    }
    return $out;
}

// ---------- price list pages ----------
function catalog_stock_options(): array {
    $o = ['' => '— Not a stocked item —'];
    foreach (all('SELECT category, IFNULL(make, "") make, model, SUM(site_id IS NULL AND status = "in_stock") n FROM equipment
                  WHERE model IS NOT NULL AND model <> "" GROUP BY category, IFNULL(make, ""), model ORDER BY category, make, model') as $e) {
        $o['e:' . $e['category'] . '|' . $e['make'] . '|' . $e['model']] = 'Equipment: ' . trim($e['category'] . ' · ' . $e['make'] . ' ' . $e['model']) . ' (' . (int)$e['n'] . ' in stock)';
    }
    foreach (all('SELECT id, name, unit, qty_on_hand FROM consumables WHERE active = 1 ORDER BY name') as $c) {
        $o['c:' . $c['id']] = 'Consumable: ' . $c['name'] . ' (' . qty_fmt((float)$c['qty_on_hand']) . ' ' . $c['unit'] . ')';
    }
    return $o;
}

function catalog_def(): array {
    return ['table' => 'catalog_items', 'label' => 'Price list item', 'fields' => [
        'name'        => ['Name', 'text', 'required' => true, 'placeholder' => 'e.g. UniFi U6 Pro access point'],
        'sku'         => ['SKU / part number', 'text'],
        'kind'        => ['Type', 'select', 'options' => ITEM_KINDS, 'default' => 'hardware'],
        'unit'        => ['Unit', 'text', 'placeholder' => 'ea / h / m'],
        'price'       => ['Sell price', 'money', 'required' => true],
        'cost'        => ['Your cost (optional, not shown to clients)', 'money'],
        'stock_link'  => ['Linked stock (shows availability on quotes)', 'select', 'options' => catalog_stock_options()],
        'pst_applies' => ['Charge PST on this item', 'checkbox'],
        'active'      => ['Active (shows in the quote picker)', 'checkbox', 'default' => 1],
        'description' => ['Text on the quote (if different from the name)', 'text'],
    ]];
}

function h_catalog(): void {
    $qs = trim((string)get('q', ''));
    $f = get('f', 'active');
    $w = ['1']; $p = [];
    if ($f !== 'all') { $w[] = 'ci.active = 1'; }
    if ($qs !== '') { $w[] = '(ci.name LIKE ? OR ci.sku LIKE ?)'; $p[] = "%$qs%"; $p[] = "%$qs%"; }
    $rows = all('SELECT ci.*, (SELECT COUNT(*) FROM quote_items qi WHERE qi.catalog_id = ci.id) used FROM catalog_items ci WHERE ' . implode(' AND ', $w) . ' ORDER BY ci.kind, ci.name', $p);
    foreach ($rows as &$r) { $k = stock_key($r); $r['free'] = $k ? stock_free($k) : null; }
    view('catalog', ['title' => 'Price list', 'rows' => $rows, 'qs' => $qs, 'f' => $f]);
}

function h_catalog_form(int $id = 0): void {
    require_role('admin', 'sales');
    $row = $id ? one('SELECT * FROM catalog_items WHERE id = ?', [$id]) : ['kind' => 'hardware', 'active' => 1, 'pst_applies' => 1, 'unit' => 'ea'];
    if ($id && !$row) not_found();
    $row['stock_link'] = stock_key($row) ?? '';
    // Prefill from inventory: /catalog/new?link=c:5 or e:...
    if (!$id && ($l = (string)get('link', '')) !== '') {
        $row['stock_link'] = $l;
        if (str_starts_with($l, 'c:') && ($c = one('SELECT * FROM consumables WHERE id = ?', [(int)substr($l, 2)]))) {
            $row += ['name' => $c['name'], 'sku' => $c['sku']]; $row['unit'] = $c['unit']; $row['kind'] = 'hardware';
        } elseif (str_starts_with($l, 'e:')) {
            [$cat, $make, $model] = explode('|', substr($l, 2), 3) + ['', '', ''];
            $row['name'] = trim("$make $model");
        }
    }
    $def = catalog_def();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$vals, $errors] = collect_fields($def);
        $link = (string)($vals['stock_link'] ?? ''); unset($vals['stock_link']);
        $vals['stock_type'] = 'none'; $vals['eq_category'] = $vals['eq_make'] = $vals['eq_model'] = null; $vals['consumable_id'] = null;
        if (str_starts_with($link, 'e:')) {
            [$cat, $make, $model] = explode('|', substr($link, 2), 3) + ['', '', ''];
            $vals['stock_type'] = 'equipment'; $vals['eq_category'] = $cat; $vals['eq_make'] = $make; $vals['eq_model'] = $model;
        } elseif (str_starts_with($link, 'c:')) {
            $vals['stock_type'] = 'consumable'; $vals['consumable_id'] = (int)substr($link, 2);
        }
        if (!isset($vals['cost']) || (float)$vals['cost'] == 0.0) $vals['cost'] = null;
        if (!$errors) {
            $newId = save_entity($def, $vals, $id ?: null);
            flash($id ? 'Price list item saved.' : 'Added to the price list.');
            redirect(get('back') === 'quote' ? back('/catalog') : '/catalog');
        }
        $row = array_merge($row, $vals, ['stock_link' => $link]);
    }
    view('form', ['title' => $id ? 'Edit price list item' : 'New price list item', 'def' => $def, 'row' => $row, 'errors' => $errors,
        'cancel' => '/catalog', 'deleteUrl' => ($id && is_role('admin', 'sales')) ? "/catalog/$id/delete" : null,
        'subtitle' => 'Pick it on quotes instead of typing prices each time']);
}

function h_catalog_delete(int $id): void {
    require_role('admin', 'sales');
    $c = one('SELECT * FROM catalog_items WHERE id = ?', [$id]);
    if (!$c) not_found();
    if ((int)val('SELECT COUNT(*) FROM quote_items WHERE catalog_id = ?', [$id])) {
        q('UPDATE catalog_items SET active = 0 WHERE id = ?', [$id]);
        flash($c['name'] . ' is used on quotes, so it was hidden from the picker instead of deleted.');
    } else {
        q('DELETE FROM catalog_items WHERE id = ?', [$id]);
        flash('Deleted ' . $c['name'] . '.');
    }
    redirect('/catalog');
}
