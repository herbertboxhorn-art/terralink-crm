<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><a class="crumb" href="/users">← Users</a><h1><?= h($title) ?></h1></div></div>
<div class="card" style="max-width:720px">
  <form method="post" class="form" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label>Name <b>*</b></label><input name="name" value="<?= h($u['name'] ?? '') ?>" required><?= $e('name') ?></div>
    <div class="field"><label>Email <b>*</b></label><input type="email" name="email" value="<?= h($u['email'] ?? '') ?>" required><?= $e('email') ?></div>
    <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= h($u['phone'] ?? '') ?>"></div>
    <div class="field"><label>Role</label><select name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= ($u['role'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?= $e('role') ?></div>
    <?php if (!$id): ?><div class="field check wide"><label><input type="checkbox" name="invite" value="1" checked> Email an invite: they choose their own password (recommended)</label></div><?php endif; ?>
    <div class="field wide"><label><?= $id ? 'New password (leave blank to keep)' : 'Or set a password yourself' ?></label><input type="password" name="password" autocomplete="new-password" minlength="10" placeholder="At least 10 characters"><?= $e('password') ?></div>
    <div class="field check wide"><label><input type="checkbox" name="active" value="1"<?= !empty($u['active']) ? ' checked' : '' ?>> Active (can sign in)</label></div>
    <div class="formbar" style="grid-column:1/-1"><button class="btn primary" type="submit">Save</button><a class="btn" href="/users">Cancel</a></div>
  </form>
</div>
