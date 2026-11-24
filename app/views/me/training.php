<div class="page-head"><div><h1><?= e(__('My training')) ?></h1><p><?= e(__('Courses assigned to you and others you can take.')) ?></p></div></div>
<div class="grid-2">
<?php foreach ($mine as $t): $pct = $t['lessons'] ? (int)round($t['done'] / $t['lessons'] * 100) : 0; if ($t['status'] === 'completed') { $pct = 100; } ?>
<div class="card">
  <div class="card-head"><h2 class="h3"><?= e($t['title']) ?></h2><?= badge($t['status']) ?></div>
  <p class="small muted"><?= e($t['summary']) ?></p>
  <div class="progress" aria-label="<?= e(__('%d%% complete', $pct)) ?>"><span style="width:<?= $pct ?>%"></span></div>
  <p class="small"><?= e(__('%d of %d lessons', (int)$t['done'], (int)$t['lessons'])) ?><?= $t['due_date'] && $t['status'] !== 'completed' ? ' · ' . e(__('Due %s', fmt_date($t['due_date']))) : '' ?><?= $t['expires_at'] ? ' · ' . e(__('Valid until %s', fmt_date($t['expires_at']))) : '' ?></p>
  <div class="actions"><a class="btn <?= $t['status'] === 'completed' ? '' : 'btn-primary' ?>" href="<?= e(url('/me/training/' . $t['course_id'])) ?>"><?= e($t['status'] === 'completed' ? __('Review') : ($t['done'] ? __('Continue') : __('Start'))) ?></a>
  <?php if ($t['status'] === 'completed' && $t['certificate_code']): ?><a class="btn" href="<?= e(url('/me/certificate/' . $t['id'])) ?>"><?= e(__('Certificate')) ?></a><?php endif; ?></div>
</div>
<?php endforeach; ?>
</div>
<?php if (!$mine): ?><div class="card"><p class="empty"><?= e(__('No training assigned to you.')) ?></p></div><?php endif; ?>
<?php if ($available): ?><h2><?= e(__('Courses open to everyone')) ?></h2><div class="grid-3"><?php foreach ($available as $c): ?>
<div class="card"><h2 class="h3"><?= e($c['title']) ?></h2><p class="small muted"><?= e($c['summary']) ?></p><form method="post" action="<?= e(url('/me/training/enrol/' . $c['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Enrol')) ?></button></form></div>
<?php endforeach; ?></div><?php endif; ?>
