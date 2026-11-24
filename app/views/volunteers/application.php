<?php
$steps = ['submitted' => 1, 'reviewing' => 2, 'training' => 3, 'approved' => 6, 'declined' => 0];
$cur = $steps[$a['status']] ?? 1;
$allTrainingDone = true; foreach ($positions as $po) { if ($po['gaps']) { $allTrainingDone = false; } }
if ($a['status'] === 'training' && $allTrainingDone) { $cur = 5; }
$labels = [1 => __('Volunteer applies'), 2 => __('Leader reviews application'), 3 => __('Training is assigned'), 4 => __('Volunteer completes training'), 5 => __('Ministry leader approves'), 6 => __('Rostering begins')];
?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/volunteers')) ?>"><?= e(__('Volunteers')) ?></a></div>
<h1><?= e(person_name($person)) ?> <?= badge($a['status']) ?></h1><p><?= e(__('Applied %s', fmt_date($a['created_at']))) ?></p></div>
<?php if (can('people.view')): ?><a class="btn" href="<?= e(url('/people/' . $person['id'])) ?>"><?= e(__('Open profile')) ?></a><?php endif; ?></div>

<div class="card">
  <ol class="grid-3 list-plain">
  <?php foreach ($labels as $n => $l): ?><li><?= $n < $cur || $cur === 6 ? badge('completed') : ($n === $cur ? badge('in_progress') : badge('draft')) ?> <strong><?= $n ?>.</strong> <?= e($l) ?></li><?php endforeach; ?>
  </ol>
</div>

<div class="split">
<div>
  <div class="card"><h2><?= e(__('Positions requested')) ?></h2>
  <?php foreach ($positions as $po): ?>
    <div class="slot" style="grid-template-columns:1fr auto">
      <div><strong><?= e($po['group_name']) ?> — <?= e($po['name']) ?></strong>
        <div class="small muted"><?= e(__('Requires')) ?>: <?= e(implode(', ', array_column($po['courses'], 'title')) ?: __('no courses')) ?><?= (int)$po['requires_wwcc'] ? ', WWCC' : '' ?></div>
        <?php if ($po['gaps']): ?><ul class="warn-list"><?php foreach ($po['gaps'] as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul><?php else: ?><span class="badge badge-green"><?= e(__('All requirements met')) ?></span><?php endif; ?>
      </div>
      <div><?= $po['approved'] ? badge('approved') : '' ?></div>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="card"><h2><?= e(__('Application')) ?></h2>
    <dl class="kv"><dt><?= e(__('Skills')) ?></dt><dd><?= nl2br(e($a['skills'] ?: '—')) ?></dd><dt><?= e(__('Availability')) ?></dt><dd><?= nl2br(e($a['availability'] ?: '—')) ?></dd><dt><?= e(__('Experience')) ?></dt><dd><?= nl2br(e($a['experience'] ?: '—')) ?></dd></dl>
    <?php if ($a['reviewer_notes']): ?><h2 class="h3"><?= e(__('Reviewer notes')) ?></h2><p class="small"><?= nl2br(e($a['reviewer_notes'])) ?></p><?php endif; ?>
  </div>
</div>
<div>
  <div class="card"><h2 class="h3"><?= e(__('Next step')) ?></h2>
  <form method="post" action="<?= e(url('/volunteers/applications/' . $a['id'])) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Notes (optional)')) ?><textarea name="reviewer_notes"></textarea></label>
    <?php if ($a['status'] === 'submitted'): ?><button class="btn btn-primary" name="action" value="review"><?= e(__('Start review')) ?></button><?php endif; ?>
    <?php if (in_array($a['status'], ['submitted', 'reviewing'], true)): ?><button class="btn btn-primary" name="action" value="training"><?= e(__('Suitable — assign training')) ?></button><?php endif; ?>
    <?php if (in_array($a['status'], ['reviewing', 'training', 'submitted'], true)): ?><button class="btn btn-gold" name="action" value="approve"><?= e(__('Approve eligible positions')) ?></button><?php endif; ?>
    <?php if ($a['status'] !== 'declined' && $a['status'] !== 'approved'): ?>
      <label class="check"><input type="checkbox" name="notify" value="1"> <?= e(__('Let them know (gentle message)')) ?></label>
      <button class="btn btn-danger" name="action" value="decline"><?= e(__('Decline')) ?></button>
    <?php endif; ?>
    <button class="btn" name="action" value=""><?= e(__('Save note only')) ?></button>
  </form></div>
  <div class="card"><h2 class="h3"><?= e(__('Safeguarding')) ?></h2>
    <?php if ($check): ?><p><?= badge('verified') ?> <?= e($check['check_type'] . ' ' . $check['jurisdiction']) ?> · <?= e(__('Expires %s', fmt_date($check['expires_on']))) ?></p><?php else: ?><p><?= badge('pending') ?> <?= e(__('No verified check on file.')) ?></p><?php endif; ?>
    <?php if (can('safeguarding.manage')): ?><a class="btn btn-sm" href="<?= e(url('/safeguarding', ['person' => $person['id']])) ?>"><?= e(__('Record a check')) ?></a><?php endif; ?>
  </div>
  <div class="card"><h2 class="h3"><?= e(__('Training progress')) ?></h2>
    <ul class="list-plain small"><?php foreach ($training as $t): ?><li><?= e($t['title']) ?> <?= badge($t['status']) ?></li><?php endforeach; ?></ul>
    <?php if (!$training): ?><p class="empty"><?= e(__('None assigned yet.')) ?></p><?php endif; ?>
  </div>
</div>
</div>
