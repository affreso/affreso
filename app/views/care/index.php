<div class="page-head"><div><h1><?= e(__('Care & follow-up')) ?></h1><p><?= e(__('Newcomer welcome journeys, pastoral contact requests, baptism and membership enquiries, and prayer requests.')) ?></p></div></div>
<div class="split">
<div>
<form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="r" value="/care">
  <label><?= e(__('Show')) ?><select name="scope"><?= options(['mine' => __('Mine & unassigned'), 'all' => __('Everyone\'s')], $scope) ?></select></label>
  <label><?= e(__('Status')) ?><select name="status"><?= options(['active' => __('Not done'), 'open' => __('Open'), 'in_progress' => __('In progress'), 'done' => __('Done'), 'all' => __('All')], $status) ?></select></label>
  <label><?= e(__('Type')) ?><select name="type"><?= options(CareController::types(), $type, __('Any')) ?></select></label>
  <button class="btn"><?= e(__('Filter')) ?></button>
</form>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= e(__('Nothing to follow up. 🙏')) ?></p></div><?php endif; ?>
<?php foreach ($rows as $t): $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done'; ?>
<div class="card">
  <div class="card-head"><div><strong><?= e($t['title']) ?></strong> <?= badge($t['status']) ?> <span class="badge badge-blue"><?= e(CareController::types()[$t['task_type']] ?? $t['task_type']) ?></span><?= (int)$t['is_confidential'] ? ' <span class="badge badge-grey">🔒 ' . e(__('Confidential')) . '</span>' : '' ?></div>
  <span class="small <?= $overdue ? 'badge badge-red' : 'muted' ?>"><?= $t['due_date'] ? e(__('Due %s', fmt_date($t['due_date']))) : '' ?></span></div>
  <?php if ($t['person_id']): ?><p class="small"><?= e(__('Person')) ?>: <a href="<?= e(url('/people/' . $t['person_id'])) ?>"><?= e(person_name($t)) ?></a></p><?php endif; ?>
  <?php if ($t['details']): ?><p class="small"><?= nl2br(e($t['details'])) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('/care/' . $t['id'])) ?>" class="form-row"><?= csrf_field() ?>
    <label><?= e(__('Status')) ?><select name="status"><?= options(['open' => __('Open'), 'in_progress' => __('In progress'), 'done' => __('Done')], $t['status']) ?></select></label>
    <label><?= e(__('Assigned to')) ?><select name="assigned_to"><?= options($staff, $t['assigned_to'], __('Unassigned')) ?></select></label>
    <label><?= e(__('Add a note')) ?><input name="add_note"></label>
    <button class="btn btn-sm"><?= e(__('Update')) ?></button>
  </form>
</div>
<?php endforeach; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('New follow-up')) ?></h2>
<form method="post" action="<?= e(url('/care')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Title')) ?><input name="title" required></label>
  <label><?= e(__('Type')) ?><select name="task_type"><?= options(CareController::types()) ?></select></label>
  <label><?= e(__('Person')) ?><select name="person_id"><?= options(people_options(), null, __('— None —')) ?></select></label>
  <label><?= e(__('Assign to')) ?><select name="assigned_to"><?= options($staff, Auth::id()) ?></select></label>
  <label><?= e(__('Due date')) ?><input type="date" name="due_date"></label>
  <label><?= e(__('Details')) ?><textarea name="details"></textarea></label>
  <?php if (can('pastoral.view')): ?><label class="check"><input type="checkbox" name="is_confidential" value="1"> <?= e(__('Confidential (only visible to pastoral staff)')) ?></label><?php endif; ?>
  <button class="btn btn-primary"><?= e(__('Add')) ?></button>
</form></div>
</div>
