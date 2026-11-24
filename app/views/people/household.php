<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/households')) ?>"><?= e(__('Households')) ?></a></div><h1><?= e($h['name']) ?></h1></div>
<?php if (can('people.edit')): ?><a class="btn btn-primary" href="<?= e(url('/people/new', ['household' => $h['id']])) ?>">+ <?= e(__('Add family member')) ?></a><?php endif; ?></div>
<div class="split">
<div class="card"><h2><?= e(__('Members')) ?></h2>
<table><tbody><?php foreach ($members as $m): ?><tr><td><a href="<?= e(url('/people/' . $m['id'])) ?>"><?= e(person_name($m)) ?></a></td><td><?= e(__(ucfirst($m['household_role']))) ?></td><td><?= $m['birthdate'] ? e(__('Age %d', age_on($m['birthdate']))) : '' ?></td><td><?= badge($m['archived_at'] ? 'archived' : $m['membership_status']) ?></td></tr><?php endforeach; ?></tbody></table>
</div>
<div class="card"><h2 class="h3"><?= e(__('Household details')) ?></h2>
<form method="post" action="<?= e(url('/households/' . $h['id'])) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" value="<?= e($h['name']) ?>" required <?= can('people.edit') ? '' : 'disabled' ?>></label>
  <label><?= e(__('Street address')) ?><input name="address_line" value="<?= e($h['address_line']) ?>"></label>
  <label><?= e(__('Suburb')) ?><input name="suburb" value="<?= e($h['suburb']) ?>"></label>
  <div class="grid-2"><label><?= e(__('State')) ?><select name="state"><?= options(au_states(), $h['state'], '—') ?></select></label><label><?= e(__('Postcode')) ?><input name="postcode" value="<?= e($h['postcode']) ?>"></label></div>
  <label><?= e(__('Home phone')) ?><input name="phone" value="<?= e($h['phone']) ?>"></label>
  <label class="check"><input type="checkbox" name="apply_address" value="1"> <?= e(__('Also update the address of everyone in this household')) ?></label>
  <?php if (can('people.edit')): ?><button class="btn btn-primary"><?= e(__('Save')) ?></button><?php endif; ?>
</form></div>
</div>
