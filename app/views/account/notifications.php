<div class="page-head"><h1><?= e(__('Notifications')) ?></h1>
<form method="post" action="<?= e(url('/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Mark all as read')) ?></button></form></div>
<div class="card">
<?php if (!$rows): ?><p class="empty"><?= e(__('No notifications yet.')) ?></p><?php endif; ?>
<ul class="list-plain">
<?php foreach ($rows as $n): ?>
  <li><?= $n['read_at'] ? '' : '<span class="badge badge-gold">' . e(__('New')) . '</span> ' ?>
    <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>"><?= e($n['title']) ?></a><?php else: ?><?= e($n['title']) ?><?php endif; ?>
    <span class="muted small"> · <?= e(fmt_datetime($n['created_at'])) ?></span></li>
<?php endforeach; ?>
</ul></div>
