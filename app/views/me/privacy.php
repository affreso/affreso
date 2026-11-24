<div class="page-head"><div><h1><?= e(__('Privacy & consent')) ?></h1><p><?= e(__('You are in control of how your information is used.')) ?> <a href="<?= e(url('/privacy')) ?>"><?= e(__('Read our privacy policy')) ?></a></p></div></div>
<div class="split">
<div class="card"><h2><?= e(__('Your choices')) ?></h2>
<form method="post" action="<?= e(url('/me/privacy')) ?>" class="form"><?= csrf_field() ?>
  <label class="check"><input type="checkbox" name="consent_contact" value="1" <?= (int)$p['consent_contact'] ? 'checked' : '' ?>> <?= e(__('I agree to the church keeping my details and contacting me about church life')) ?></label>
  <label class="check"><input type="checkbox" name="consent_photo" value="1" <?= (int)$p['consent_photo'] ? 'checked' : '' ?>> <?= e(__('Photos of me may be used in church publications and social media')) ?></label>
  <label class="check"><input type="checkbox" name="directory_visible" value="1" <?= (int)$p['directory_visible'] ? 'checked' : '' ?>> <?= e(__('List my name, email and mobile in the members-only directory')) ?></label>
  <label class="check"><input type="checkbox" name="notify_email" value="1" <?= (int)$p['notify_email'] ? 'checked' : '' ?>> <?= e(__('Email notifications')) ?></label>
  <label class="check"><input type="checkbox" name="notify_sms" value="1" <?= (int)$p['notify_sms'] ? 'checked' : '' ?>> <?= e(__('SMS notifications')) ?></label>
  <button class="btn btn-primary"><?= e(__('Save choices')) ?></button>
</form>
<?php if ($log): ?><h2 class="h3"><?= e(__('History')) ?></h2><ul class="list-plain small muted"><?php foreach ($log as $l): ?><li><?= e(ucfirst($l['consent_type'])) ?>: <?= $l['granted'] ? e(__('granted')) : e(__('withdrawn')) ?> · <?= e(fmt_datetime($l['created_at'])) ?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<div>
<div class="card"><h2 class="h3"><?= e(__('Your information')) ?></h2><p class="small"><?= e(__('Download a copy of the information we hold about you.')) ?></p><a class="btn" href="<?= e(url('/me/export')) ?>"><?= e(__('Download my data')) ?></a></div>
<div class="card"><h2 class="h3"><?= e(__('Make a request')) ?></h2>
<form method="post" action="<?= e(url('/me/privacy')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Request type')) ?><select name="request"><?= options(['access' => __('Access to all my information'), 'correction' => __('Correct my information'), 'deletion' => __('Delete my information'), 'complaint' => __('Privacy complaint')]) ?></select></label>
  <label><?= e(__('Details')) ?><textarea name="details"></textarea></label>
  <button class="btn"><?= e(__('Send to privacy officer')) ?></button>
</form>
<p class="small muted"><?= e(__('Privacy officer: %s (%s)', setting('privacy_officer'), setting('privacy_contact'))) ?></p></div>
</div>
</div>
