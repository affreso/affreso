<?php $v = fn($k) => e($p[$k] ?? ''); ?>
<div class="page-head"><div><h1><?= e(__('My profile')) ?></h1><p><?= e(__('Keep your details up to date so your church can stay in touch.')) ?></p></div></div>
<form method="post" action="<?= e(url('/me/profile')) ?>" class="form"><?= csrf_field() ?>
<div class="split">
<div class="card">
  <div class="grid-2">
    <label><?= e(__('First name')) ?><input name="first_name" required value="<?= $v('first_name') ?>"></label>
    <label><?= e(__('Last name')) ?><input name="last_name" required value="<?= $v('last_name') ?>"></label>
    <label><?= e(__('Preferred name')) ?><input name="preferred_name" value="<?= $v('preferred_name') ?>"></label>
    <label><?= e(__('Date of birth')) ?><input type="date" name="birthdate" value="<?= $v('birthdate') ?>"></label>
    <label><?= e(__('Email')) ?><input type="email" name="email" value="<?= $v('email') ?>"></label>
    <label><?= e(__('Mobile')) ?><input type="tel" name="mobile" value="<?= $v('mobile') ?>"></label>
    <label><?= e(__('Gender')) ?><select name="gender"><?= options(['female' => __('Female'), 'male' => __('Male'), 'other' => __('Prefer to self-describe'), 'undisclosed' => __('Prefer not to say')], $p['gender'], '—') ?></select></label>
  </div>
  <label><?= e(__('Street address')) ?><input name="address_line" value="<?= $v('address_line') ?>"></label>
  <div class="grid-2"><label><?= e(__('Suburb')) ?><input name="suburb" value="<?= $v('suburb') ?>"></label><label><?= e(__('State')) ?><select name="state"><?= options(au_states(), $p['state'], '—') ?></select></label><label><?= e(__('Postcode')) ?><input name="postcode" value="<?= $v('postcode') ?>"></label></div>
</div>
<div>
  <div class="card"><h2 class="h3"><?= e(__('Preferences')) ?></h2>
    <label class="check"><input type="checkbox" name="directory_visible" value="1" <?= (int)$p['directory_visible'] ? 'checked' : '' ?>> <?= e(__('Show me in the church directory')) ?></label>
    <label class="check"><input type="checkbox" name="notify_email" value="1" <?= (int)$p['notify_email'] ? 'checked' : '' ?>> <?= e(__('Email me reminders and news')) ?></label>
    <label class="check"><input type="checkbox" name="notify_sms" value="1" <?= (int)$p['notify_sms'] ? 'checked' : '' ?>> <?= e(__('Text me roster reminders')) ?></label>
  </div>
  <?php if ($household): ?><div class="card"><h2 class="h3"><?= e(__('My household')) ?></h2><ul class="list-plain"><?php foreach ($household as $h): ?><li><?= e(person_name($h)) ?></li><?php endforeach; ?></ul><p class="small muted"><?= e(__('Contact the church office to update other household members.')) ?></p></div><?php endif; ?>
  <button class="btn btn-primary"><?= e(__('Save my details')) ?></button>
</div>
</div>
</form>
