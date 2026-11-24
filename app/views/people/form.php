<?php $isNew = empty($p['id']); $v = fn($k) => e($p[$k] ?? ''); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/people')) ?>"><?= e(__('Members & families')) ?></a></div><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url($isNew ? '/people' : '/people/' . $p['id'])) ?>" class="form">
<?= csrf_field() ?>
<div class="split">
<div>
  <div class="card">
    <h2><?= e(__('Personal details')) ?></h2>
    <div class="grid-2">
      <label><?= e(__('First name')) ?> *<input name="first_name" required value="<?= $v('first_name') ?>"></label>
      <label><?= e(__('Last name')) ?> *<input name="last_name" required value="<?= $v('last_name') ?>"></label>
      <label><?= e(__('Preferred name')) ?><input name="preferred_name" value="<?= $v('preferred_name') ?>"></label>
      <label><?= e(__('Date of birth')) ?><input type="date" name="birthdate" value="<?= $v('birthdate') ?>"></label>
      <label><?= e(__('Email')) ?><input type="email" name="email" value="<?= $v('email') ?>"></label>
      <label><?= e(__('Mobile')) ?><input type="tel" name="mobile" value="<?= $v('mobile') ?>"></label>
      <label><?= e(__('Gender')) ?><select name="gender"><?= options(['female' => __('Female'), 'male' => __('Male'), 'other' => __('Prefer to self-describe'), 'undisclosed' => __('Prefer not to say')], $p['gender'] ?? '', '—') ?></select></label>
    </div>
    <h2 class="h3"><?= e(__('Address')) ?></h2>
    <label><?= e(__('Street address')) ?><input name="address_line" value="<?= $v('address_line') ?>"></label>
    <div class="grid-2">
      <label><?= e(__('Suburb')) ?><input name="suburb" value="<?= $v('suburb') ?>"></label>
      <label><?= e(__('State')) ?><select name="state"><?= options(au_states(), $p['state'] ?? setting('church_state'), '—') ?></select></label>
      <label><?= e(__('Postcode')) ?><input name="postcode" value="<?= $v('postcode') ?>" inputmode="numeric"></label>
    </div>
  </div>
  <?php if ($fields): ?>
  <div class="card"><h2><?= e(__('Additional information')) ?></h2><div class="grid-2">
    <?php foreach ($fields as $f): $name = 'cf_' . $f['id']; $val = $f['value'] ?? ''; ?>
      <?php if ($f['field_type'] === 'checkbox'): ?>
        <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e($f['label']) ?></label>
      <?php elseif ($f['field_type'] === 'select'): $opts = array_map('trim', explode(',', (string)$f['options'])); ?>
        <label><?= e($f['label']) ?><select name="<?= e($name) ?>"><?= options(array_combine($opts, $opts), $val, '—') ?></select></label>
      <?php else: ?>
        <label><?= e($f['label']) ?><input type="<?= $f['field_type'] === 'date' ? 'date' : 'text' ?>" name="<?= e($name) ?>" value="<?= e($val) ?>"></label>
      <?php endif; ?>
    <?php endforeach; ?>
  </div></div>
  <?php endif; ?>
  <div class="card"><h2><?= e(__('General notes')) ?></h2>
    <p class="help"><?= e(__('Visible to anyone who can view member records. Do not record confidential pastoral information here — use pastoral notes, which are encrypted and restricted.')) ?></p>
    <textarea name="general_notes" aria-label="<?= e(__('General notes')) ?>"><?= $v('general_notes') ?></textarea>
  </div>
</div>
<div>
  <div class="card">
    <h2 class="h3"><?= e(__('Church relationship')) ?></h2>
    <?= Campus::field(isset($p['campus_id']) ? (int)$p['campus_id'] : null) ?>
    <label><?= e(__('Status')) ?><select name="membership_status"><?= options(array_map('__', PeopleController::STATUSES), $p['membership_status'] ?? 'visitor') ?></select></label>
    <label><?= e(__('First visit')) ?><input type="date" name="first_visit" value="<?= $v('first_visit') ?>"></label>
    <label><?= e(__('Became a member')) ?><input type="date" name="joined_on" value="<?= $v('joined_on') ?>"></label>
    <?php if ($isNew): ?><label class="check"><input type="checkbox" name="create_followup" value="1" checked> <?= e(__('Create a welcome follow-up task if this is a visitor')) ?></label><?php endif; ?>
  </div>
  <div class="card">
    <h2 class="h3"><?= e(__('Household')) ?></h2>
    <label><?= e(__('Existing household')) ?><select name="household_id"><?= options($households, $p['household_id'] ?? '', __('— None —')) ?></select></label>
    <label><?= e(__('…or create a new household')) ?><input name="new_household" placeholder="<?= e(__('e.g. Smith household')) ?>"></label>
    <label><?= e(__('Role in household')) ?><select name="household_role"><?= options(['adult' => __('Adult'), 'child' => __('Child'), 'other' => __('Other')], $p['household_role'] ?? 'adult') ?></select></label>
  </div>
  <div class="card">
    <h2 class="h3"><?= e(__('Preferences & consent')) ?></h2>
    <label class="check"><input type="checkbox" name="directory_visible" value="1" <?= !empty($p['directory_visible']) ? 'checked' : '' ?>> <?= e(__('Show in the church directory')) ?></label>
    <label class="check"><input type="checkbox" name="notify_email" value="1" <?= !empty($p['notify_email']) ? 'checked' : '' ?>> <?= e(__('Email notifications')) ?></label>
    <label class="check"><input type="checkbox" name="notify_sms" value="1" <?= !empty($p['notify_sms']) ? 'checked' : '' ?>> <?= e(__('SMS notifications')) ?></label>
    <?php if ($isNew): ?>
    <label class="check"><input type="checkbox" name="consent_contact" value="1"> <?= e(__('Has consented to the church holding their details and contacting them')) ?></label>
    <label class="check"><input type="checkbox" name="consent_photo" value="1"> <?= e(__('Has consented to photos being used')) ?></label>
    <?php endif; ?>
  </div>
  <button class="btn btn-primary"><?= e($isNew ? __('Add person') : __('Save changes')) ?></button>
  <a class="btn" href="<?= e(url($isNew ? '/people' : '/people/' . $p['id'])) ?>"><?= e(__('Cancel')) ?></a>
</div>
</div>
</form>
