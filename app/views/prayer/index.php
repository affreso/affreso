<div class="page-head"><div><h1><?= e(__('Prayer')) ?></h1><p><?= e(__('"Carry each other\'s burdens" — Galatians 6:2')) ?></p></div></div>
<div class="split"><div>
<div class="tabs"><a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('/prayer')) ?>"><?= e(__('Praying for')) ?></a><a class="<?= $status === 'answered' ? 'active' : '' ?>" href="<?= e(url('/prayer', ['status' => 'answered'])) ?>"><?= e(__('Answered')) ?></a></div>
<?php foreach ($rows as $r): ?>
<div class="card">
  <div class="card-head"><div><strong><?= e($r['name'] ?: person_name($r)) ?></strong> <span class="badge badge-<?= $r['visibility'] === 'pastors' ? 'gold' : ($r['visibility'] === 'team' ? 'blue' : 'grey') ?>"><?= e(['church' => __('Church'), 'team' => __('Prayer team'), 'pastors' => __('Pastors')][$r['visibility']]) ?></span></div><span class="small muted"><?= e(fmt_date($r['created_at'])) ?></span></div>
  <p class="lesson-body"><?= e($r['request']) ?></p>
  <?php if ($r['answer'] !== ''): ?><div class="callout small"><strong><?= e(__('Update')) ?>:</strong> <?= nl2br(e($r['answer'])) ?></div><?php endif; ?>
  <div class="actions">
    <?php if ($r['status'] === 'open'): ?><form method="post" action="<?= e(url('/prayer/' . $r['id'] . '/prayed')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $r['prayed_today'] ? '' : 'btn-primary' ?>" <?= $r['prayed_today'] ? 'disabled' : '' ?>>🙏 <?= e($r['prayed_today'] ? __('Prayed today') : __('I prayed')) ?></button></form><?php endif; ?>
    <span class="small muted"><?= e(__('%d prayers', (int)$r['prayed_count'])) ?></span>
    <?php if ($r['mine'] || can('prayer.team') || can('pastoral.view')): ?>
    <details><summary class="small"><?= e(__('Update')) ?></summary><form method="post" action="<?= e(url('/prayer/' . $r['id'])) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Update or answer to prayer')) ?><textarea name="answer"></textarea></label><select name="status" aria-label="<?= e(__('Status')) ?>"><?= options(['open' => __('Still praying'), 'answered' => __('Answered'), 'archived' => __('Archive')], $r['status']) ?></select> <button class="btn btn-sm"><?= e(__('Save')) ?></button></form></details>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= e(__('No prayer requests here.')) ?></p></div><?php endif; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('Share a prayer request')) ?></h2>
<form method="post" action="<?= e(url('/prayer')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Your request')) ?><textarea name="request" rows="5" required></textarea></label>
  <label><?= e(__('Who can see it?')) ?><select name="visibility"><?= options(PrayerController::levels(), 'team') ?></select></label>
  <label class="check"><input type="checkbox" name="anonymous" value="1"> <?= e(__('Don\'t show my name')) ?></label>
  <button class="btn btn-primary"><?= e(__('Share')) ?></button>
</form>
<p class="small muted"><?= e(__('Prayer requests are stored encrypted.')) ?> <?= e(__('Public link for visitors:')) ?> <a href="<?= e(url('/pray')) ?>"><?= e(url('/pray', [], true)) ?></a></p></div>
</div>
