<?php $full = $ev['capacity'] && $taken >= (int)$ev['capacity']; $open = (int)$ev['registration_enabled'] && $ev['status'] === 'scheduled' && strtotime($ev['end_at']) > time() && (!$ev['registration_closes'] || strtotime($ev['registration_closes']) > time()); ?>
<p><a href="<?= e(url('/public/events')) ?>">‹ <?= e(__('All events')) ?></a></p>
<div class="split">
<div class="card"><h1><?= e($ev['title']) ?> <?= $ev['status'] === 'cancelled' ? badge('cancelled') : '' ?></h1><p class="muted"><?= e(fmt_datetime($ev['start_at'])) ?> – <?= e(fmt_time($ev['end_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p><?php if ($ev['description']): ?><p><?= nl2br(e($ev['description'])) ?></p><?php endif; ?></div>
<?php if ($form && $ev['status'] === 'scheduled' && strtotime($ev['end_at']) > time()): ?>
<div class="card"><h2><?= e(__('Register')) ?></h2>
<?php if (Forms::isPaid($form)): ?><p><strong><?= e(__('Cost: %s', Forms::priceText($form))) ?></strong><?= (int)$form['instalments'] > 1 ? ' <span class="small muted">' . e(__('or up to %d monthly payments', (int)$form['instalments'])) . '</span>' : '' ?></p><?php endif; ?>
<p><a class="btn btn-primary" href="<?= e(url('/f/' . $form['slug'])) ?>"><?= e(Forms::isPaid($form) ? __('Register and pay') : __('Register')) ?></a></p></div>
<?php elseif ($open): ?>
<div class="card"><h2><?= e($full ? __('Join the waiting list') : __('Register')) ?></h2>
<?php if ($ev['capacity']): ?><p class="small muted"><?= e(__('%d places left', max(0, (int)$ev['capacity'] - $taken))) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('/public/events/' . $ev['id'] . '/register')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required autocomplete="name"></label>
  <label><?= e(__('Email')) ?><input type="email" name="email" required autocomplete="email"></label>
  <label><?= e(__('Mobile (optional)')) ?><input type="tel" name="phone" autocomplete="tel"></label>
  <label><?= e(__('Extra guests')) ?><input type="number" name="guests" min="0" max="10" value="0"></label>
  <label><?= e(__('Dietary or access needs (optional)')) ?><input name="notes"></label>
  <div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <label class="check"><input type="checkbox" name="privacy_ack" value="1" required> <?= e(__('I understand my details will be used to manage this event, as described in the')) ?>&nbsp;<a href="<?= e(url('/privacy')) ?>" target="_blank"><?= e(__('privacy policy')) ?></a>.</label>
  <button class="btn btn-primary"><?= e(__('Register')) ?></button>
</form></div>
<?php endif; ?>
</div>
