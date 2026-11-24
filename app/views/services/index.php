<div class="page-head"><div><h1><?= e(__('Service planning')) ?></h1><p><?= e(__('Orders of service with timings, songs and the people serving.')) ?></p></div>
<div class="actions"><?php if (can('services.plan')): ?><a class="btn" href="<?= e(url('/services/songs')) ?>"><?= e(__('Songs')) ?></a><a class="btn" href="<?= e(url('/services/ccli')) ?>"><?= e(__('CCLI report')) ?></a><?php endif; ?></div></div>
<div class="split"><div class="card table-wrap"><table><thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Service')) ?></th><th><?= e(__('Items')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
<?php foreach ($plans as $p): ?><tr><td class="nowrap"><?= e(fmt_date($p['service_date'], 'D j M')) ?> <span class="small muted"><?= e(fmt_time($p['start_time'])) ?></span></td><td><a href="<?= e(url('/services/' . $p['id'])) ?>"><?= e($p['title']) ?></a><?= $p['theme'] ? '<div class="small muted">' . e($p['theme']) . '</div>' : '' ?></td><td class="small"><?= (int)$p['items'] ?> · <?= e(__('%d min', (int)$p['minutes'])) ?></td><td><?= badge($p['status'] === 'final' ? 'published' : 'draft') ?></td></tr><?php endforeach; ?>
<?php if (!$plans): ?><tr><td colspan="4" class="empty"><?= e(__('No services planned yet.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<?php if (can('services.plan')): ?><div class="card"><h2 class="h3"><?= e(__('Plan a service')) ?></h2><form method="post" action="<?= e(url('/services')) ?>" class="form"><?= csrf_field() ?>
<label><?= e(__('Title')) ?><input name="title" value="<?= e(__('Sunday Service')) ?>"></label>
<div class="grid-2"><label><?= e(__('Date')) ?><input type="date" name="service_date" value="<?= e(date('Y-m-d', strtotime('next sunday'))) ?>"></label><label><?= e(__('Starts')) ?><input type="time" name="start_time" value="09:30"></label></div>
<label><?= e(__('Theme or sermon series')) ?><input name="theme"></label><?= Campus::field() ?>
<label class="check"><input type="checkbox" name="template" value="1" checked> <?= e(__('Start from a standard order of service')) ?></label>
<button class="btn btn-primary"><?= e(__('Create')) ?></button></form></div><?php endif; ?>
</div>
