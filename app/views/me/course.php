<?php $cid = (int)$e['course_id']; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/me/training')) ?>"><?= e(__('My training')) ?></a></div><h1><?= e($e['title']) ?> <?= badge($e['status']) ?></h1><p><?= e($e['summary']) ?></p></div>
<?php if ($e['status'] === 'completed' && $e['certificate_code']): ?><a class="btn btn-gold" href="<?= e(url('/me/certificate/' . $e['id'])) ?>"><?= e(__('View certificate')) ?></a><?php endif; ?></div>
<?php if (!$prereqOk): ?><div class="alert alert-warning"><?= e(__('Please complete "%s" before starting this course.', $prereq)) ?></div><?php else: ?>
<div class="split">
<div class="card">
<?php if ($showQuiz): ?>
  <h2><?= e(__('Quiz')) ?></h2><p class="muted"><?= e(__('Pass mark: %d%%', (int)$e['pass_mark'])) ?><?= $e['attempts'] ? ' · ' . e(__('Attempts so far: %d', (int)$e['attempts'])) : '' ?></p>
  <form method="post" action="<?= e(url('/me/training/' . $cid . '/quiz')) ?>" class="form"><?= csrf_field() ?>
  <?php foreach ($questions as $i => $q): ?><fieldset><legend><?= $i + 1 ?>. <?= e($q['question']) ?></legend>
    <?php foreach (json_decode($q['options'], true) ?: [] as $oi => $opt): ?><label class="check"><input type="radio" name="q[<?= (int)$q['id'] ?>]" value="<?= (int)$oi ?>" required> <?= e($opt) ?></label><?php endforeach; ?></fieldset><?php endforeach; ?>
  <button class="btn btn-primary"><?= e(__('Submit answers')) ?></button></form>
<?php elseif ($current): ?>
  <h2><?= e($current['title']) ?></h2>
  <?php if ($current['lesson_type'] === 'video' && ($embed = Training::embedUrl($current['video_url']))): ?><div class="video-frame"><iframe src="<?= e($embed) ?>" title="<?= e($current['title']) ?>" allowfullscreen loading="lazy"></iframe></div>
  <?php elseif ($current['lesson_type'] === 'video' && $current['video_url']): ?><p><a href="<?= e($current['video_url']) ?>" target="_blank" rel="noopener"><?= e(__('Watch the video')) ?></a></p><?php endif; ?>
  <?php if ($current['lesson_type'] === 'pdf' && $current['file_id']): ?><p><a class="btn" href="<?= e(url('/files/' . $current['file_id'])) ?>" target="_blank"><?= e(__('Open the PDF')) ?></a></p><?php endif; ?>
  <?php if ($current['body']): ?><div class="lesson-body<?= $current['lesson_type'] === 'scripture' ? ' scripture' : '' ?>"><?= e($current['body']) ?></div><?php endif; ?>
  <?php if (!$current['done']): ?><form method="post" action="<?= e(url('/me/training/' . $cid . '/lesson/' . $current['id'])) ?>" style="margin-top:18px"><?= csrf_field() ?><button class="btn btn-primary"><?= e(__('Mark lesson complete')) ?> →</button></form>
  <?php else: ?><p style="margin-top:18px"><?= badge('completed') ?></p><?php endif; ?>
<?php else: ?>
  <?php if ($e['status'] === 'completed'): ?><h2><?= e(__('Course complete 🎉')) ?></h2><p><?= e(__('Score: %s', $e['score'] !== null ? $e['score'] . '%' : __('n/a'))) ?></p>
  <?php elseif ($allDone && $questions): ?><p><a class="btn btn-primary" href="<?= e(url('/me/training/' . $cid, ['quiz' => 1])) ?>"><?= e(__('Take the quiz')) ?></a></p>
  <?php else: ?><p class="empty"><?= e(__('Choose a lesson to begin.')) ?></p><?php endif; ?>
<?php endif; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('Lessons')) ?></h2>
<ul class="lesson-nav"><?php foreach ($lessons as $i => $l): ?><li><a class="<?= $current && (int)$current['id'] === (int)$l['id'] ? 'current' : '' ?>" href="<?= e(url('/me/training/' . $cid, ['lesson' => $l['id']])) ?>"><span><?= $l['done'] ? '✅' : '◯' ?></span> <?= $i + 1 ?>. <?= e($l['title']) ?></a></li><?php endforeach; ?>
<?php if ($questions): ?><li><a class="<?= $showQuiz ? 'current' : '' ?>" href="<?= e(url('/me/training/' . $cid, ['quiz' => 1])) ?>"><span><?= $e['status'] === 'completed' ? '✅' : '📝' ?></span> <?= e(__('Quiz')) ?></a></li><?php endif; ?></ul>
</div>
</div>
<?php endif; ?>
