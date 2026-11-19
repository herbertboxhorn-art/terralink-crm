<?php ob_start(); ?>
<h1>Enter your code</h1>
<p class="muted" style="margin:0">Open your authenticator app and type the 6-digit code for <b><?= h(setting('company_name', 'TerraLink')) ?> CRM</b> (<?= h($u['email']) ?>).</p>
<?php if ($error): ?><div class="flash bad" style="margin:16px 0 0"><?= h($error) ?></div><?php endif; ?>
<?php if ($f = flash()): ?><div class="flash <?= h($f['type']) ?>" style="margin:16px 0 0"><?= h($f['msg']) ?></div><?php endif; ?>
<form method="post" action="/login/2fa" autocomplete="off">
  <?= csrf_field() ?>
  <div class="field"><label for="code">Code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9A-Za-z\- ]{6,12}" maxlength="12" required autofocus style="font-size:22px;letter-spacing:.2em;text-align:center"></div>
  <div class="field check"><label><input type="checkbox" name="trust" value="1"> Trust this device for <?= TWOFA_TRUST_DAYS ?> days</label></div>
  <button class="btn primary" type="submit">Verify</button>
</form>
<p class="alt">No phone? Type one of your recovery codes instead (like ABCDE-12345).<br>Lost both? Ask an admin to reset your two-step sign-in. · <a href="/login">Start over</a></p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_authshell.php'; ?>
