<h1><?= e(__('Upcoming events')) ?></h1>
<p class="muted"><?= e(__('Subscribe to our calendar:')) ?> <a href="<?= e(url('/ical/church', [], true)) ?>"><?= e(__('iCal feed')) ?></a></p>
<?php foreach ($events as $ev): ?>
<div class="card event-card">
  <div class="datebox"><div class="m"><?= e(fmt_date($ev['start_at'], 'M')) ?></div><div class="d"><?= e(fmt_date($ev['start_at'], 'j')) ?></div><div class="m"><?= e(fmt_date($ev['start_at'], 'D')) ?></div></div>
  <div><h2 style="margin-top:0"><a href="<?= e(url('/public/events/' . $ev['id'])) ?>"><?= e($ev['title']) ?></a></h2><p class="muted"><?= e(fmt_datetime($ev['start_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p><?php if ($ev['description']): ?><p><?= e(mb_strimwidth($ev['description'], 0, 220, '…')) ?></p><?php endif; ?>
  <?php if ((int)$ev['registration_enabled']): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/public/events/' . $ev['id'])) ?>"><?= e(__('Register')) ?></a><?php endif; ?></div>
</div>
<?php endforeach; ?>
<?php if (!$events): ?><div class="card"><p class="empty"><?= e(__('No public events are scheduled right now.')) ?></p></div><?php endif; ?>
