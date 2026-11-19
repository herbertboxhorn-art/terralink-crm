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
</div>
