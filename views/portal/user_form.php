<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; $pp = $u; ?>
<div class="pagehead"><div><a class="crumb" href="/users">← Portal users</a><h1><?= h($title) ?></h1>
  <?php if (!$id): ?><div class="sub">They'll get an email to choose their own password.</div><?php endif; ?></div></div>
<div class="card" style="max-width:760px">
  <form method="post" class="form" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label>Name <b>*</b></label><input name="name" value="<?= h($u['name'] ?? '') ?>" required><?= $e('name') ?></div>
    <div class="field"><label>Email (their login) <b>*</b></label><input type="email" name="email" value="<?= h($u['email'] ?? '') ?>" required><?= $e('email') ?></div>
    <div class="field wide"><label>What they can see and do</label><?php require __DIR__ . '/../_portal_perms.php'; ?></div>
    <?php if ($id): ?><div class="field check wide"><input type="hidden" name="active" value="0"><label><input type="checkbox" name="active" value="1"<?= !empty($u['active']) ? ' checked' : '' ?>> Active (can sign in)</label></div><?php endif; ?>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit"><?= $id ? 'Save' : 'Add & send invite' ?></button><a class="btn" href="/users">Cancel</a></div>
  </form>
</div>
