<div class="page-head"><div><h1><?= e(__('My children')) ?></h1><p><?= e(__('Keep allergy, medical and pick-up details up to date so our children\'s team can care for your family. This information is encrypted and only seen by authorised leaders.')) ?></p></div></div>
<?php foreach ($kids ? $pcSessions : [] as $ps): $st = $pcState[$ps['id']] ?? []; $code = $st['code'] ?? null; ?>
<div class="card callout" id="precheck-<?= (int)$ps['id'] ?>">
  <h2 class="h3" style="margin-top:0"><?= e(__('Check in before you arrive: %s', $ps['title'])) ?><?= $ps['start_time'] ? ' · ' . e(fmt_time($ps['start_time'])) : '' ?></h2>
  <?php if ($code): ?><p><?= e(__('Your arrival code')) ?></p><div class="kiosk-code" style="text-align:left;font-size:44px"><?= e($code) ?></div>
    <p class="small muted"><?= e(__('At the kiosk, enter your mobile number. At the welcome desk, show this code. Your labels will print and you\'ll get the pick-up code.')) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('/me/children/precheck')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="session_id" value="<?= (int)$ps['id'] ?>">
    <div class="checks-inline" role="group" aria-label="<?= e(__('Children')) ?>">
    <?php foreach ($kids as $k): $ks = $st[$k['id']] ?? 'none'; ?>
      <label class="check"><input type="checkbox" name="child_ids[]" value="<?= (int)$k['id'] ?>" <?= $ks !== 'none' ? 'checked disabled' : 'checked' ?>> <?= e($k['preferred_name'] ?: $k['first_name']) ?> <?= $ks === 'in' ? '<span class="badge badge-green">' . e(__('Checked in')) . '</span>' : ($ks === 'pre' ? '<span class="badge badge-amber">' . e(__('Pre-checked in')) . '</span>' : '') ?></label>
    <?php endforeach; ?></div>
    <?php $anyLeft = in_array('none', array_map(fn($k) => $st[$k['id']] ?? 'none', $kids), true); ?>
    <?php if ($pcAdults && $anyLeft): ?><label><?= e(__('Dropping off')) ?><select name="adult_id"><?= options(array_column(array_map(fn($a) => ['id' => $a['id'], 'n' => person_name($a)], $pcAdults), 'n', 'id'), person_id()) ?></select></label><?php endif; ?>
    <?php if ($anyLeft): ?><button class="btn btn-primary"><?= e(__('Pre-check in')) ?></button><?php endif; ?>
  </form>
  <?php if ($code): ?><form method="post" action="<?= e(url('/me/children/precheck/cancel')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="session_id" value="<?= (int)$ps['id'] ?>"><button class="btn btn-sm"><?= e(__('Cancel pre-check-in')) ?></button></form><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$kids): ?><div class="card"><p class="empty"><?= e(__('No children are linked to your household. Contact the church office to add them.')) ?></p></div><?php endif; ?>
<?php foreach ($kids as $k): ?>
<div class="card"><h2 style="margin-top:0"><?= e(person_name($k)) ?></h2>
<div class="split"><div>
<?php partial('checkin/care_form', ['child' => $k, 'info' => $k['info']]); ?>
</div><div>
<?php partial('checkin/collectors', ['child' => $k, 'collectors' => $k['collectors']]); ?>
<?php if ($k['history']): ?><h2 class="h3"><?= e(__('Recent check-ins')) ?></h2><ul class="list-plain small"><?php foreach ($k['history'] as $h): ?><li><?= e(fmt_datetime($h['checked_in_at'])) ?> · <?= e($h['title']) ?><?= $h['collected_by'] ? ' · ' . e(__('collected by %s', $h['collected_by'])) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
</div></div></div>
<?php endforeach; ?>
