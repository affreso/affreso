<?php $cid = (int)$c['id']; $types = ['text' => __('Text'), 'video' => __('Video (YouTube / Vimeo)'), 'pdf' => __('PDF / document'), 'scripture' => __('Bible passage')]; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/courses')) ?>"><?= e(__('Courses & training')) ?></a></div>
<h1><?= e($c['title']) ?> <?= badge($c['status']) ?></h1><p><?= e($c['category']) ?> · <?= e(__('%d enrolled, %d completed', (int)$stats['enrolled'], (int)$stats['completed'])) ?></p></div>
<div class="actions">
  <?php if (can('training.reports') || can('courses.approve') || can('courses.assign')): ?><a class="btn" href="<?= e(url('/courses/' . $cid . '/progress')) ?>"><?= e(__('Progress')) ?></a><?php endif; ?>
  <?php if ($canEdit && $c['status'] === 'draft'): ?><form method="post" action="<?= e(url('/courses/' . $cid . '/submit')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= e(__('Submit for review')) ?></button></form><?php endif; ?>
  <?php if (can('courses.approve') && $c['status'] === 'published'): ?><form method="post" action="<?= e(url('/courses/' . $cid)) ?>"><?= csrf_field() ?><button class="btn" name="action" value="unpublish"><?= e(__('Return to draft')) ?></button> <button class="btn btn-danger" name="action" value="archive" data-confirm="<?= e(__('Archive this course?')) ?>"><?= e(__('Archive')) ?></button></form><?php endif; ?>
</div></div>
<div class="callout small"><?= e(__('Workflow: Draft → Review → Published. Only published courses can be taken or assigned. Course editors only see the courses they are assigned to and cannot see member records.')) ?></div>
<div class="split">
<div>
  <?php if ($c['status'] === 'review' && can('courses.approve')): ?>
  <div class="card" style="border-left:4px solid var(--gold)"><h2><?= e(__('Review this course')) ?></h2>
    <form method="post" action="<?= e(url('/courses/' . $cid . '/review')) ?>" class="form"><?= csrf_field() ?>
      <label><?= e(__('Comments for the author')) ?><textarea name="comment"></textarea></label>
      <button class="btn btn-primary" name="decision" value="approved"><?= e(__('Approve & publish')) ?></button> <button class="btn" name="decision" value="changes"><?= e(__('Request changes')) ?></button>
    </form></div>
  <?php endif; ?>
  <div class="card" id="lessons"><h2><?= e(__('Lessons')) ?></h2>
  <?php foreach ($lessons as $i => $l): ?>
    <details class="slot" style="display:block"><summary><strong><?= $i + 1 ?>. <?= e($l['title']) ?></strong> <span class="badge badge-blue"><?= e($types[$l['lesson_type']]) ?></span><?= $l['original_name'] ? ' <span class="small muted">📎 ' . e($l['original_name']) . '</span>' : '' ?></summary>
      <?php if ($canEdit): ?>
      <form method="post" action="<?= e(url('/lessons/' . $l['id'])) ?>" enctype="multipart/form-data" class="form" style="margin-top:10px"><?= csrf_field() ?><?php partial('courses/lesson_fields', ['l' => $l, 'types' => $types]); ?><button class="btn btn-sm btn-primary"><?= e(__('Save lesson')) ?></button></form>
      <form method="post" action="<?= e(url('/lessons/' . $l['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this lesson?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete')) ?></button></form>
      <?php else: ?><div class="lesson-body small"><?= e(mb_substr((string)$l['body'], 0, 600)) ?></div><?php endif; ?>
    </details>
  <?php endforeach; ?>
  <?php if (!$lessons): ?><p class="empty"><?= e(__('No lessons yet.')) ?></p><?php endif; ?>
  <?php if ($canEdit): ?><h2 class="h3"><?= e(__('Add a lesson')) ?></h2><form method="post" action="<?= e(url('/courses/' . $cid . '/lessons')) ?>" enctype="multipart/form-data" class="form"><?= csrf_field() ?><?php partial('courses/lesson_fields', ['l' => ['lesson_type' => 'text'], 'types' => $types]); ?><button class="btn btn-sm"><?= e(__('Add lesson')) ?></button></form><?php endif; ?>
  </div>
  <div class="card" id="quiz"><h2><?= e(__('Quiz')) ?></h2><p class="small muted"><?= e(__('Pass mark %d%%. Leave empty for courses without a quiz.', (int)$c['pass_mark'])) ?></p>
    <ol><?php foreach ($questions as $q): $opts = json_decode($q['options'], true) ?: []; ?><li><strong><?= e($q['question']) ?></strong><ul class="small"><?php foreach ($opts as $oi => $o): ?><li><?= (int)$q['correct_index'] === $oi ? '✅ ' : '' ?><?= e($o) ?></li><?php endforeach; ?></ul>
    <?php if ($canEdit): ?><form method="post" action="<?= e(url('/questions/' . $q['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn-link small"><?= e(__('remove')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ol>
    <?php if ($canEdit): ?><form method="post" action="<?= e(url('/courses/' . $cid . '/questions')) ?>" class="form"><?= csrf_field() ?>
      <label><?= e(__('Question')) ?><input name="question" required></label>
      <label><?= e(__('Options (one per line)')) ?><textarea name="options" required></textarea></label>
      <label><?= e(__('Number of the correct option')) ?><input type="number" name="correct" min="1" value="1"></label>
      <button class="btn btn-sm"><?= e(__('Add question')) ?></button></form><?php endif; ?>
  </div>
</div>
<div>
  <?php if ($c['status'] === 'published' && can('courses.assign')): ?>
  <div class="card"><h2 class="h3"><?= e(__('Assign training')) ?></h2>
  <form method="post" action="<?= e(url('/courses/' . $cid . '/assign')) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Whole group or team')) ?><select name="group_id"><?= options($groups, null, '—') ?></select></label>
    <label><?= e(__('And/or individuals')) ?><select name="person_ids[]" multiple size="6"><?= options(people_options()) ?></select></label>
    <label><?= e(__('Due date')) ?><input type="date" name="due_date"></label>
    <label class="check"><input type="checkbox" name="reassign" value="1"> <?= e(__('Ask people who already completed it to retake it')) ?></label>
    <button class="btn btn-primary"><?= e(__('Assign')) ?></button></form></div>
  <?php endif; ?>
  <div class="card"><h2 class="h3"><?= e(__('Course settings')) ?></h2>
  <?php if ($canEdit): ?><form method="post" action="<?= e(url('/courses/' . $cid)) ?>" class="form"><?= csrf_field() ?><?php partial('courses/fields', ['c' => $c, 'courses' => $courses]); ?><button class="btn btn-sm btn-primary"><?= e(__('Save')) ?></button></form>
  <?php else: ?><p class="small"><?= nl2br(e($c['summary'])) ?></p><p class="small muted"><?= e(__('Editing is locked while the course is in review or published.')) ?></p><?php endif; ?>
  </div>
  <div class="card"><h2 class="h3"><?= e(__('Editors')) ?></h2>
    <ul class="list-plain small"><?php foreach ($editors as $ed): ?><li><?= e(person_name($ed) ?: $ed['email']) ?></li><?php endforeach; ?></ul>
    <?php if (can('courses.approve')): ?><form method="post" action="<?= e(url('/courses/' . $cid . '/editors')) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Assigned course creators')) ?><select name="user_ids[]" multiple size="4"><?= options($creators, array_column($editors, 'id')) ?></select></label><button class="btn btn-sm"><?= e(__('Save editors')) ?></button></form><?php endif; ?>
  </div>
  <div class="card"><h2 class="h3"><?= e(__('Review history')) ?></h2><ul class="list-plain small"><?php foreach ($reviews as $r): ?><li><?= badge($r['decision']) ?> <?= e(person_name($r)) ?> · <?= e(fmt_datetime($r['created_at'])) ?><?= $r['comment'] ? '<div>' . nl2br(e($r['comment'])) . '</div>' : '' ?></li><?php endforeach; ?></ul><?php if (!$reviews): ?><p class="empty"><?= e(__('Not yet reviewed.')) ?></p><?php endif; ?></div>
</div>
</div>
