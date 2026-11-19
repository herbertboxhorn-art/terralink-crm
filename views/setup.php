<?php $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<?php ob_start(); ?>
  <h1>First-run setup</h1>
  <?php if ($invalid): ?>
    <p class="muted">This setup link is invalid or has expired. Ask your administrator for a new setup link.</p>
  <?php else: ?>
    <p class="muted" style="margin:0">Create the first admin account. This link stops working once you save.</p>
    <form method="post" action="/setup">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
      <div class="field"><label>Your name</label><input name="name" value="<?= h($_POST['name'] ?? '') ?>" required autofocus><?= $e('name') ?></div>
      <div class="field"><label>Email (your login)</label><input type="email" name="email" autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>" required><?= $e('email') ?></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="new-password" minlength="10" required><?= $e('password') ?></div>
      <div class="field"><label>Confirm password</label><input type="password" name="confirm" autocomplete="new-password" required><?= $e('confirm') ?></div>
      <button class="btn primary" type="submit">Create admin account</button>
    </form>
  <?php endif; ?>
<?php $title = 'Setup'; $inner = ob_get_clean(); require __DIR__ . '/_authshell.php'; ?>
