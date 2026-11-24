<div class="page-head"><div><h1><?= e(__('Events')) ?></h1></div><?php if (can('events.manage')): ?><a class="btn" href="<?= e(url('/events')) ?>"><?= e(__('Manage events')) ?></a><?php endif; ?></div>
<?php foreach ($events as $ev): $full = $ev['capacity'] && (int)$ev['taken'] >= (int)$ev['capacity']; ?>
<div class="card event-card">
  <div class="datebox"><div class="m"><?= e(fmt_date($ev['start_at'], 'M')) ?></div><div class="d"><?= e(fmt_date($ev['start_at'], 'j')) ?></div><div class="m"><?= e(fmt_date($ev['start_at'], 'D')) ?></div></div>
  <div><h2 class="h3" style="margin-top:0"><?= e($ev['title']) ?> <?= !$ev['is_public'] ? '<span class="badge badge-grey">' . e(__('Members only')) . '</span>' : '' ?> <?= $ev['my_status'] ? badge($ev['my_status']) : '' ?></h2>
  <p class="small muted"><?= e(fmt_datetime($ev['start_at'])) ?> – <?= e(fmt_time($ev['end_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p>
  <?php if ($ev['description']): ?><p><?= nl2br(e($ev['description'])) ?></p><?php endif; ?>
  <?php if (isset($forms[(int)$ev['id']])): $lf = $forms[(int)$ev['id']]; ?>
    <?php if (!$ev['my_status']): ?><p><a class="btn btn-primary btn-sm" href="<?= e(url('/f/' . $lf['slug'])) ?>"><?= e(Forms::isPaid($lf) ? __('Register and pay') : __('Register')) ?></a><?= Forms::isPaid($lf) ? ' <span class="small muted">' . e(__('Cost: %s', Forms::priceText($lf))) . '</span>' : '' ?></p>
    <?php else: ?><p class="small muted"><?= e(__('To cancel, please contact the church office.')) ?></p><?php endif; ?>
  <?php elseif ((int)$ev['registration_enabled']): ?>
    <?php if ($ev['my_status']): ?><form method="post" action="<?= e(url('/me/events/' . $ev['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Cancel my registration')) ?></button></form>
    <?php else: ?><form method="post" action="<?= e(url('/me/events/' . $ev['id'] . '/register')) ?>" class="form-row"><?= csrf_field() ?><label><?= e(__('Extra guests')) ?><input type="number" name="guests" min="0" max="10" value="0" style="max-width:90px"></label><button class="btn btn-primary btn-sm"><?= e($full ? __('Join waiting list') : __('Register')) ?></button></form><?php endif; ?>
    <?php if ($ev['capacity']): ?><p class="small muted"><?= e(__('%d of %d places taken', (int)$ev['taken'], (int)$ev['capacity'])) ?></p><?php endif; ?>
  <?php endif; ?></div>
</div>
<?php endforeach; ?>
<?php if (!$events): ?><div class="card"><p class="empty"><?= e(__('No upcoming events.')) ?></p></div><?php endif; ?>
