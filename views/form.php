<div class="pagehead">
  <div>
    <h1><?= h($title) ?></h1>
    <?php if (!empty($subtitle)): ?><div class="sub"><?= h($subtitle) ?></div><?php endif; ?>
  </div>
</div>
<div class="card" style="max-width:860px">
  <form method="post" class="form"<?= !empty($dependent) ? ' data-dependent' : '' ?> novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash bad wide" style="grid-column:1/-1;margin:0">Please fix the highlighted fields.</div><?php endif; ?>
    <?= render_fields($def, $row ?? [], $errors) ?>
    <?php if (!empty($geo)): ?><div class="field wide"><button type="button" class="btn sm" data-geo hidden>📍 Use my current location for GPS</button></div><?php endif; ?>
    <div class="formbar" style="grid-column:1/-1">
      <button class="btn primary" type="submit">Save</button>
      <a class="btn" href="<?= h($cancel) ?>">Cancel</a>
      <span class="spacer"></span>
    </div>
  </form>
  <?php if (!empty($deleteUrl)): ?>
  <form method="post" action="<?= h($deleteUrl) ?>" data-confirm="Delete this <?= h(strtolower($def['label'])) ?>? This can't be undone." style="margin-top:10px;text-align:right">
    <?= csrf_field() ?><button class="btn danger sm" type="submit">Delete <?= h(strtolower($def['label'])) ?></button>
  </form>
  <?php endif; ?>
</div>
