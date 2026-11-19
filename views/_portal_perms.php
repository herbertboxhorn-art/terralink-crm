<?php
/** Permission ticks for a portal login. Needs $pp (current values); optional $grantable (null = all, for staff) */
$grantable ??= null;
$can = fn(string $p) => $grantable === null || in_array($p, $grantable, true);
?>
<div class="perms">
  <?php foreach (PORTAL_PERMS as $k => [$label, $hint]): if (!$can($k)) continue; ?>
  <label class="perm"><input type="checkbox" name="<?= $k ?>" value="1"<?= !empty($pp[$k]) ? ' checked' : '' ?>>
    <span><b><?= h($label) ?></b><small><?= h($hint) ?></small></span></label>
  <?php if ($k === 'perm_tickets' && $can('perm_tickets_all')): ?>
  <label class="perm sub"><input type="checkbox" name="perm_tickets_all" value="1"<?= !empty($pp['perm_tickets_all']) ? ' checked' : '' ?>>
    <span><b>See all company tickets</b><small>Without this they only see tickets they opened or are the contact on</small></span></label>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php
  // Limit to sites (v1.15). Needs $siteList (all company sites); $siteLimit = sites the editor may hand out (null = any)
  $siteList = $siteList ?? [];
  $siteLimit = $siteLimit ?? null;
  $chosen = array_map('intval', (array)($pp['site_ids'] ?? []));
  if ($siteList): ?>
  <div class="perm" style="cursor:default;flex-direction:column;gap:6px">
    <span><b>Limit to sites</b><small><?= $siteLimit === null ? 'Leave all unticked to allow every site. Ticked: they only see tickets, gear and plans at those sites (plus tickets they opened).' : 'You can only give access to your own sites.' ?></small></span>
    <div style="display:flex;flex-wrap:wrap;gap:6px 16px">
    <?php foreach ($siteList as $st): if ($siteLimit !== null && !in_array((int)$st['id'], $siteLimit, true)) continue; ?>
      <label style="display:flex;gap:6px;align-items:center;font-weight:400"><input type="checkbox" name="site_ids[]" value="<?= (int)$st['id'] ?>" style="width:auto"<?= in_array((int)$st['id'], $chosen, true) ? ' checked' : '' ?>> <?= h($st['name']) ?></label>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
