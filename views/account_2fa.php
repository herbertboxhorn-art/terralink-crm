<?php $on = twofa_enabled($u); $req = twofa_required($u); ?>
<div class="pagehead"><div><a class="crumb" href="/account">← My account</a><h1>Two-step sign-in</h1>
  <div class="sub">After your password, the CRM asks for a 6-digit code from an app on your phone, so a stolen password alone can't get in.<?= $req ? ' <b>Required for your account.</b>' : '' ?></div></div></div>
<?php if ($err): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>

<?php if ($codes): ?>
<div class="card" style="max-width:720px;border-color:var(--orange)">
  <h2>Save your recovery codes now</h2>
  <p class="muted">Each code works once if you lose your phone. They won't be shown again. Print them or store them in your password manager.</p>
  <pre class="mono" style="font-size:17px;line-height:1.9;columns:2;background:var(--bg);padding:14px 18px;border-radius:8px;margin:10px 0"><?= h(implode("\n", $codes)) ?></pre>
  <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(<?= h(json_encode(implode("\n", $codes))) ?>).then(()=>this.textContent='Copied ✓')">Copy</button>
    <button class="btn" type="button" onclick="window.print()">Print</button>
    <a class="btn primary" href="/">I've saved them — continue</a>
  </div>
</div>
<?php endif; ?>

<?php if (!$on): ?>
<div class="grid g2" style="max-width:980px">
  <div class="card">
    <h2>1. Scan with your authenticator app</h2>
    <p class="muted" style="margin:6px 0 12px">Google Authenticator, Microsoft Authenticator, 1Password, Authy, Bitwarden… any of them work.</p>
    <div id="qr" data-uri="<?= h(totp_uri($secret, $u['email'])) ?>" style="background:#fff;padding:12px;border-radius:10px;width:fit-content"></div>
    <details style="margin-top:12px"><summary class="muted" style="cursor:pointer">Can't scan? Enter this key by hand</summary>
      <p class="mono" style="font-size:16px;letter-spacing:.08em;margin:8px 0 0;overflow-wrap:anywhere"><?= h(trim(chunk_split($secret, 4, ' '))) ?></p>
      <p class="muted" style="font-size:13px;margin:4px 0 0">Account: <?= h($u['email']) ?> · time-based · 6 digits</p></details>
  </div>
  <div class="card">
    <h2>2. Type the code it shows</h2>
    <form method="post" class="form" style="grid-template-columns:1fr;margin-top:10px" autocomplete="off"><?= csrf_field() ?>
      <input type="hidden" name="action" value="enable">
      <div class="field"><label>6-digit code</label><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required style="font-size:22px;letter-spacing:.2em;text-align:center"></div>
      <div class="field check"><label><input type="checkbox" name="trust" value="1"> Trust this device for <?= TWOFA_TRUST_DAYS ?> days</label></div>
      <button class="btn primary" type="submit">Turn on two-step sign-in</button>
    </form>
    <p class="muted" style="font-size:13px;margin:12px 0 0">You'll get 8 recovery codes next, for when your phone is lost or dead.</p>
  </div>
</div>
<script src="/assets/qrcode.js?v=<?= APP_VERSION ?>"></script>
<script>(function(){var el=document.getElementById('qr');if(!el||typeof qrcode!=='function')return;var q=qrcode(0,'M');q.addData(el.dataset.uri);q.make();el.innerHTML=q.createSvgTag({cellSize:5,margin:2,scalable:false});})();</script>
<?php else: ?>
<div class="grid g2" style="max-width:980px">
  <div class="card">
    <h2>Status</h2>
    <dl class="kv" style="margin-top:8px">
      <dt>Two-step sign-in</dt><dd><span class="pill active">On</span> since <?= h(fdate($u['totp_enabled_at'])) ?></dd>
      <dt>Recovery codes left</dt><dd><b<?= twofa_recovery_left($u) <= 2 ? ' style="color:var(--bad)"' : '' ?>><?= twofa_recovery_left($u) ?></b> of <?= TWOFA_RECOVERY_CODES ?></dd>
      <dt>Trusted devices</dt><dd><?= count($devices) ?: 'None' ?></dd>
    </dl>
    <?php if ($devices): ?><ul class="list" style="margin-top:8px"><?php foreach ($devices as $d): ?>
      <li><div class="grow" style="font-size:13px"><?= h(mb_strimwidth((string)$d['label'], 0, 70, '…')) ?><span class="meta">Added <?= h(fdate($d['created_at'])) ?> · until <?= h(fdate($d['expires_at'])) ?><?= $d['last_used_at'] ? ' · used ' . h(ago($d['last_used_at'])) : '' ?></span></div></li>
    <?php endforeach; ?></ul>
    <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="forget"><button class="btn sm" type="submit">Forget all trusted devices</button></form><?php endif; ?>
  </div>
  <div class="card">
    <h2>Change</h2>
    <form method="post" class="form" style="grid-template-columns:1fr;margin-top:10px" autocomplete="off"><?= csrf_field() ?>
      <div class="field"><label>Current code (or a recovery code) to confirm</label><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required></div>
      <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" type="submit" name="action" value="recovery">New recovery codes</button>
        <?php if (!$req): ?><button class="btn ghost" type="submit" name="action" value="disable" style="color:var(--bad)" onclick="return confirm('Turn off two-step sign-in?')">Turn off</button><?php endif; ?>
      </div>
    </form>
    <p class="muted" style="font-size:13px;margin:12px 0 0">New phone? Turn it off and on again here<?= $req ? ' (or ask an admin to reset it, since it\'s required for you)' : '' ?>, or sign in with a recovery code.</p>
  </div>
</div>
<?php endif; ?>
