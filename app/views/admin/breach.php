<?php $v = fn($k) => e($b[$k] ?? ''); $days = (int)floor((strtotime((string)$b['assessment_due']) - time()) / 86400); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin/privacy', ['tab' => 'breaches'])) ?>"><?= e(__('Data-breach register')) ?></a></div><h1><?= e($b['summary']) ?></h1><p><?= e(__('Discovered %s', fmt_datetime($b['discovered_at']))) ?></p></div></div>
<?php if ($b['stage'] !== 'closed' && $b['likely_serious_harm'] === 'unknown'): ?><div class="alert <?= $days < 7 ? 'alert-error' : 'alert-warning' ?>"><?= e(__('Assessment due by %s (%d days). If serious harm is likely, notify affected individuals and the OAIC as soon as practicable.', fmt_date($b['assessment_due']), $days)) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/breaches/' . $b['id'])) ?>" class="form"><?= csrf_field() ?>
<div class="split"><div class="card">
  <label><?= e(__('Summary')) ?><input name="summary" value="<?= $v('summary') ?>"></label>
  <label><?= e(__('What happened')) ?><textarea name="details"><?= $v('details') ?></textarea></label>
  <label><?= e(__('Information involved')) ?><textarea name="data_involved"><?= $v('data_involved') ?></textarea></label>
  <label><?= e(__('Containment and remediation actions')) ?><textarea name="actions_taken"><?= $v('actions_taken') ?></textarea></label>
  <label><?= e(__('Review and lessons learned')) ?><textarea name="lessons_learned"><?= $v('lessons_learned') ?></textarea></label>
</div><div class="card">
  <label><?= e(__('Stage')) ?><select name="stage"><?= options(['contain' => __('1. Contain'), 'assess' => __('2. Assess'), 'notify' => __('3. Notify'), 'review' => __('4. Review'), 'closed' => __('Closed')], $b['stage']) ?></select></label>
  <label><?= e(__('Is serious harm likely?')) ?><select name="likely_serious_harm"><?= options(['unknown' => __('Not yet assessed'), 'yes' => __('Yes — eligible data breach'), 'no' => __('No')], $b['likely_serious_harm']) ?></select></label>
  <label><?= e(__('People affected')) ?><input type="number" name="people_affected" value="<?= $v('people_affected') ?>"></label>
  <label><?= e(__('OAIC notified on')) ?><input type="date" name="oaic_notified_on" value="<?= $v('oaic_notified_on') ?>"></label>
  <label><?= e(__('Individuals notified on')) ?><input type="date" name="individuals_notified_on" value="<?= $v('individuals_notified_on') ?>"></label>
  <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  <p class="small muted"><?= e(__('Guidance: oaic.gov.au → Notifiable data breaches. Your obligations depend on whether your church is covered by the Privacy Act; record your assessment either way.')) ?></p>
</div></div></form>
