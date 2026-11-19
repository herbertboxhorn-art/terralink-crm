<?php /** @var string $etype @var int $eid @var array $timeline */ ?>
<div class="card" id="timeline">
  <div class="tlbar"><h2>Activity</h2><label><input type="checkbox" data-tl-toggle> Hide automatic updates</label></div>
  <?php if (empty($noForm)): ?>
  <form method="post" action="/activities" class="inline" style="margin-bottom:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="entity_type" value="<?= h($etype) ?>">
    <input type="hidden" name="entity_id" value="<?= (int)$eid ?>">
    <textarea name="body" rows="2" placeholder="Log a note, call or site visit…" required></textarea>
    <div class="row">
      <select name="type" aria-label="Type"><?php foreach (ACTIVITY_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
      <button class="btn dark" type="submit">Log it</button>
    </div>
  </form>
  <?php endif; ?>
  <?php if (!$timeline): ?><div class="empty">Nothing logged yet.</div><?php else: ?>
  <ul class="timeline">
    <?php foreach ($timeline as $a): ?>
    <li class="<?= h($a['type']) ?>">
      <div class="head">
        <?= $a['user_id'] ? avatar($a['who'], $a['user_id']) : '' ?>
        <b><?= h(['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'visit' => 'Site visit', 'stage' => 'Update', 'system' => 'Auto'][$a['type']] ?? ucfirst($a['type'])) ?></b>
        <span><?= h($a['who'] ?? 'System') ?> · <time title="<?= h($a['created_at']) ?>"><?= h(ago($a['created_at'])) ?></time></span>
        <?php if (in_array($a['type'], ['note', 'call', 'email', 'visit'], true) && ((int)$a['user_id'] === uid() || is_role('admin'))): ?>
        <form method="post" action="/activities/<?= (int)$a['id'] ?>/delete" data-confirm="Delete this entry?" style="margin-left:auto"><?= csrf_field() ?><button class="linkbtn" type="submit">delete</button></form>
        <?php endif; ?>
      </div>
      <div class="body"><?= h($a['body']) ?></div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
