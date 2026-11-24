<div class="page-head"><div><h1><?= e(__('Availability')) ?></h1><p><?= e(__('Tell us when you are away so you are not rostered on those dates.')) ?></p></div></div>
<div class="split">
<div class="card"><h2><?= e(__('Dates I am unavailable')) ?></h2>
<?php if (!$rows): ?><p class="empty"><?= e(__('No upcoming unavailable dates.')) ?></p><?php endif; ?>
<ul class="list-plain"><?php foreach ($rows as $u): ?><li><strong><?= e(fmt_date($u['start_date'])) ?></strong><?= $u['end_date'] !== $u['start_date'] ? ' – <strong>' . e(fmt_date($u['end_date'])) . '</strong>' : '' ?> <?= e($u['reason']) ?>
<form class="inline-form" method="post" action="<?= e(url('/me/availability/' . $u['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn-link small"><?= e(__('remove')) ?></button></form></li><?php endforeach; ?></ul>
</div>
<div class="card"><h2 class="h3"><?= e(__('Add unavailable dates')) ?></h2>
<form method="post" action="<?= e(url('/me/availability')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('From')) ?><input type="date" name="start_date" required min="<?= e(date('Y-m-d')) ?>"></label>
  <label><?= e(__('To')) ?><input type="date" name="end_date" min="<?= e(date('Y-m-d')) ?>"></label>
  <label><?= e(__('Reason (optional)')) ?><input name="reason" placeholder="<?= e(__('e.g. Holiday')) ?>"></label>
  <button class="btn btn-primary"><?= e(__('Save')) ?></button>
</form></div>
</div>
