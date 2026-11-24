<?php $u = user(); ?>
<div class="page-head"><div><h1><?= e(__('Welcome, %s', person_name($u) ?: __('friend'))) ?></h1><p><?= e(fmt_date(date('Y-m-d'), 'l j F Y')) ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/me/availability')) ?>"><?= e(__('Set availability')) ?></a><a class="btn btn-primary" href="<?= e(url('/me/rosters')) ?>"><?= e(__('My rosters')) ?></a></div></div>

<?php if ($stats): ?>
<div class="grid-4" style="margin-bottom:18px">
  <?php foreach ($stats as [$num, $label, $link, $warn]): ?>
    <a class="stat<?= $warn ? ' warn' : '' ?>" href="<?= e(url_from($link)) ?>"><div class="num"><?= (int)$num ?></div><div class="lbl"><?= e($label) ?></div></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="split">
<div>
  <div class="card">
    <div class="card-head"><h2><?= e(__('Your upcoming serving')) ?></h2><a href="<?= e(url('/me/rosters')) ?>" class="small"><?= e(__('View all')) ?></a></div>
    <?php if (!$myRosters): ?><p class="empty"><?= e(__('You are not rostered on anything coming up.')) ?></p><?php endif; ?>
    <ul class="list-plain">
    <?php foreach ($myRosters as $r): ?>
      <li><strong><?= e(fmt_date($r['service_date'], 'D j M')) ?></strong> <?= e(fmt_time($r['start_time'])) ?> — <?= e($r['position']) ?> · <?= e($r['title']) ?> <?= badge($r['status']) ?>
      <?php if ($r['status'] === 'pending'): ?>
        <form class="inline-form" method="post" action="<?= e(url('/me/rosters/' . $r['id'] . '/respond')) ?>"><?= csrf_field() ?><button name="answer" value="accept" class="btn btn-sm btn-primary"><?= e(__('Accept')) ?></button> <button name="answer" value="decline" class="btn btn-sm"><?= e(__('Decline')) ?></button></form>
      <?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php if ($openSwaps): ?>
  <div class="card">
    <h2><?= e(__('Swap requests you could help with')) ?></h2>
    <ul class="list-plain"><?php foreach ($openSwaps as $s): ?><li><?= e(fmt_date($s['service_date'], 'D j M')) ?> — <?= e($s['position']) ?> (<?= e($s['title']) ?>)
      <form class="inline-form" method="post" action="<?= e(url('/me/swaps/' . $s['id'] . '/take')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__("I'll cover it")) ?></button></form></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-head"><h2><?= e(__('Training to complete')) ?></h2><a href="<?= e(url('/me/training')) ?>" class="small"><?= e(__('My training')) ?></a></div>
    <?php if (!$myTraining): ?><p class="empty"><?= e(__('Nothing outstanding — well done.')) ?></p><?php endif; ?>
    <ul class="list-plain"><?php foreach ($myTraining as $t): ?><li><a href="<?= e(url('/me/training/' . $t['course_id'])) ?>"><?= e($t['title']) ?></a> <?= badge($t['status']) ?> <?php if ($t['due_date']): ?><span class="muted small"><?= e(__('Due %s', fmt_date($t['due_date']))) ?></span><?php endif; ?></li><?php endforeach; ?></ul>
  </div>
</div>
<div>
  <?php foreach ($announcements as $a): ?>
  <div class="card"><span class="badge badge-gold"><?= e(__('Announcement')) ?></span><h2 class="h3"><?= e($a['title']) ?></h2><p><?= nl2br(e($a['body'])) ?></p></div>
  <?php endforeach; ?>
  <div class="card">
    <div class="card-head"><h2 class="h3"><?= e(__('Coming up')) ?></h2><a class="small" href="<?= e(url('/me/events')) ?>"><?= e(__('All events')) ?></a></div>
    <ul class="list-plain"><?php foreach ($events as $ev): ?><li><strong><?= e(fmt_date($ev['start_at'], 'D j M')) ?></strong> <?= e(fmt_time($ev['start_at'])) ?> · <?= e($ev['title']) ?><?php if (!$ev['is_public']): ?> <span class="badge badge-grey"><?= e(__('Internal')) ?></span><?php endif; ?></li><?php endforeach; ?></ul>
    <?php if (!$events): ?><p class="empty"><?= e(__('No upcoming events.')) ?></p><?php endif; ?>
  </div>
</div>
</div>
