<div class="page-head">
  <div><h1><?= e(__('Members & families')) ?></h1><p><?= e(__('%d people', $pg['total'])) ?></p></div>
  <div class="actions">
    <a class="btn" href="<?= e(url('/households')) ?>"><?= e(__('Households')) ?></a>
    <?php if (can('people.export')): ?><a class="btn" href="<?= e(url('/people/import')) ?>"><?= e(__('Import')) ?></a><a class="btn" href="<?= e(url('/people/export')) ?>"><?= e(__('Export CSV')) ?></a><?php endif; ?>
    <?php if (can('people.edit')): ?><a class="btn btn-primary" href="<?= e(url('/people/new')) ?>">+ <?= e(__('Add person')) ?></a><?php endif; ?>
  </div>
</div>
<form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>">
  <input type="hidden" name="r" value="/people">
  <label><?= e(__('Search')) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Name, email or mobile')) ?>"></label>
  <label><?= e(__('Status')) ?><select name="status"><?= options(array_map('__', PeopleController::STATUSES), $status, __('Any')) ?></select></label>
  <label><?= e(__('Group')) ?><select name="group"><?= options(array_column($groups, 'name', 'id'), $group, __('Any')) ?></select></label>
  <label class="check"><input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> <?= e(__('Archived')) ?></label>
  <button class="btn"><?= e(__('Filter')) ?></button>
</form>
<div class="card table-wrap">
<table>
  <thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Household')) ?></th><th><?= e(__('Email')) ?></th><th><?= e(__('Mobile')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="<?= e(url('/people/' . $r['id'])) ?>"><strong><?= e(person_name($r)) ?></strong></a><?= $r['household_role'] === 'child' ? ' <span class="badge badge-blue">' . e(__('Child')) . '</span>' : '' ?></td>
      <td><?= badge($r['membership_status']) ?></td>
      <td><?= e($r['household']) ?></td>
      <td><?= e($r['email']) ?></td>
      <td class="nowrap"><span dir="ltr"><?= e($r['mobile']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= e(__('No people match your filters.')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
<?php partial('partials/pagination', ['pg' => $pg]); ?>
</div>
