<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/groups/' . $g['id'])) ?>"><?= e($g['name']) ?></a></div><h1><?= e(__('Attendance for %s', fmt_date($m['meeting_date']))) ?></h1></div></div>
<form method="post" action="<?= e(url('/groups/' . $g['id'] . '/meetings/' . $m['id'])) ?>" class="card form"><?= csrf_field() ?>
  <label><?= e(__('Topic')) ?><input name="topic" value="<?= e($m['topic']) ?>"></label>
  <label class="check"><input type="checkbox" data-check-all="present[]"> <strong><?= e(__('Select all')) ?></strong></label>
  <?php foreach ($members as $p): ?><label class="check"><input type="checkbox" name="present[]" value="<?= (int)$p['id'] ?>" <?= $p['present'] ? 'checked' : '' ?>> <?= e(person_name($p)) ?></label><?php endforeach; ?>
  <label><?= e(__('Notes')) ?><textarea name="notes"><?= e($m['notes']) ?></textarea></label>
  <button class="btn btn-primary"><?= e(__('Save attendance')) ?></button>
</form>
