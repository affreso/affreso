<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/people')) ?>"><?= e(__('Members & families')) ?></a></div><h1><?= e(__('Households')) ?></h1></div></div>
<div class="split">
<div class="card table-wrap">
  <form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="r" value="/households"><label><?= e(__('Search')) ?><input type="search" name="q" value="<?= e($q) ?>"></label><button class="btn"><?= e(__('Search')) ?></button></form>
  <table><thead><tr><th><?= e(__('Household')) ?></th><th><?= e(__('Suburb')) ?></th><th><?= e(__('People')) ?></th></tr></thead><tbody>
  <?php foreach ($rows as $h): ?><tr><td><a href="<?= e(url('/households/' . $h['id'])) ?>"><?= e($h['name']) ?></a></td><td><?= e($h['suburb']) ?></td><td><?= (int)$h['members'] ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
<?php if (can('people.edit')): ?>
<div class="card"><h2 class="h3"><?= e(__('New household')) ?></h2>
<form method="post" action="<?= e(url('/households')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required></label>
  <label><?= e(__('Street address')) ?><input name="address_line"></label>
  <label><?= e(__('Suburb')) ?><input name="suburb"></label>
  <div class="grid-2"><label><?= e(__('State')) ?><select name="state"><?= options(au_states(), setting('church_state')) ?></select></label><label><?= e(__('Postcode')) ?><input name="postcode"></label></div>
  <label><?= e(__('Home phone')) ?><input name="phone"></label>
  <button class="btn btn-primary"><?= e(__('Create household')) ?></button>
</form></div>
<?php endif; ?>
</div>
