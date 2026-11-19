<?php $id = (int)$qt['id']; $e = fn($k) => isset($errors[$k]) ? '<div class="err">' . h($errors[$k]) . '</div>' : ''; ?>
<div class="pagehead"><div><a class="crumb" href="/quotes/<?= $id ?>">← <?= h($qt['number']) ?></a><h1>Email quote</h1>
  <div class="sub"><?= h($qt['title']) ?> · <?= h($qt['company']) ?> · <?= h(money($tot['total'])) ?> CAD</div></div></div>
<div class="card" style="max-width:760px">
  <form method="post" class="form" style="grid-template-columns:1fr"><?= csrf_field() ?>
    <div class="field"><label for="to">To <b>*</b></label><input id="to" name="to" value="<?= h($v['to']) ?>" placeholder="client@example.com" required><?= $e('to') ?>
      <?php if (!$qt['ct_email']): ?><small class="muted">No contact email on this quote. Pick an "Attention" contact on the quote, or type the address.</small><?php endif; ?></div>
    <div class="field"><label for="subject">Subject <b>*</b></label><input id="subject" name="subject" value="<?= h($v['subject']) ?>" required><?= $e('subject') ?></div>
    <div class="field"><label for="message">Message <b>*</b></label><textarea id="message" name="message" rows="11" required><?= h($v['message']) ?></textarea><?= $e('message') ?></div>
    <div class="field check"><label><input type="checkbox" name="cc_me" value="1"<?= $v['cc_me'] ? ' checked' : '' ?>> Send me a copy (<?= h(user()['email']) ?>)</label></div>
    <p class="muted" style="margin:0;font-size:13px">📎 <a href="/quotes/<?= $id ?>/pdf" target="_blank">The quote PDF</a> is attached. It's sent from noreply@terralinksystems.com, and replies go to <?= h(user()['email']) ?>.<?= $qt['status'] === 'draft' ? ' The quote will be marked <b>Sent</b>.' : '' ?></p>
    <div class="formbar"><button class="btn primary" type="submit"><svg class="i"><use href="/assets/icons.svg#send"/></svg>Send</button><a class="btn" href="/quotes/<?= $id ?>">Cancel</a></div>
  </form>
</div>
