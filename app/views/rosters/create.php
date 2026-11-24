<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/rosters')) ?>"><?= e(__('Rosters')) ?></a></div><h1><?= e(__('New roster')) ?></h1></div></div>
<div class="card">
<form method="get" action="<?= e(base_path() . '/index.php') ?>" class="form-row"><input type="hidden" name="r" value="/rosters/new">
  <label><?= e(__('Team')) ?><select name="group" required><?= options($groups, $gid, '—') ?></select></label><button class="btn"><?= e(__('Choose')) ?></button>
</form>
</div>
<?php if ($gid): ?>
<form method="post" action="<?= e(url('/rosters')) ?>" class="card form"><?= csrf_field() ?>
  <input type="hidden" name="group_id" value="<?= (int)$gid ?>">
  <div class="grid-2">
    <label><?= e(__('Title')) ?><input name="title" value="<?= e($groups[$gid] . ' — ' . __('Sunday Service')) ?>"></label>
    <label><?= e(__('Date')) ?><input type="date" name="service_date" required value="<?= e(date('Y-m-d', strtotime('next sunday'))) ?>"></label>
    <label><?= e(__('Start time')) ?><input type="time" name="start_time" value="09:00"></label>
    <label><?= e(__('End time')) ?><input type="time" name="end_time" value="11:30"></label>
    <label><?= e(__('Repeat weekly for')) ?><select name="repeat_weeks"><?= options(array_combine(range(1, 12), array_map(fn($n) => $n === 1 ? __('Just this date') : __('%d weeks', $n), range(1, 12))), 1) ?></select></label>
  </div>
  <label><?= e(__('Notes for volunteers')) ?><textarea name="notes" placeholder="<?= e(__('e.g. Please arrive 30 minutes early for sound check.')) ?>"></textarea></label>
  <h2 class="h3"><?= e(__('How many people for each position?')) ?></h2>
  <?php if (!$positions): ?><p class="empty"><?= e(__('This team has no positions yet.')) ?> <a href="<?= e(url('/groups/' . $gid)) ?>#positions"><?= e(__('Add positions')) ?></a></p><?php endif; ?>
  <div class="grid-4"><?php foreach ($positions as $po): ?><label><?= e($po['name']) ?><input type="number" min="0" max="20" name="qty_<?= (int)$po['id'] ?>" value="1"></label><?php endforeach; ?></div>
  <button class="btn btn-primary"><?= e(__('Create roster')) ?></button>
</form>
<?php endif; ?>
