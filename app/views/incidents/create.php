<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/incidents')) ?>"><?= e(__('Incident reports')) ?></a></div><h1><?= e(__('Report an incident')) ?></h1></div></div>
<div class="alert alert-error"><strong><?= e(__('If anyone is in immediate danger, call 000 first.')) ?></strong> <?= e(__('If a child tells you about harm: listen, stay calm, do not investigate or promise secrecy, and record their words as closely as you can.')) ?></div>
<form method="post" action="<?= e(url('/incidents')) ?>" class="form"><?= csrf_field() ?>
<div class="split"><div class="card">
  <label><?= e(__('Short summary')) ?><input name="summary" required maxlength="255"></label>
  <label><?= e(__('What happened? (facts, in order)')) ?><textarea name="details" rows="7" required></textarea></label>
  <label><?= e(__('People involved and witnesses')) ?><textarea name="people" rows="3"></textarea></label>
  <label><?= e(__('Injuries or harm (if any)')) ?><textarea name="injury" rows="2"></textarea></label>
  <label><?= e(__('What was done immediately?')) ?><textarea name="action" rows="3"></textarea></label>
</div><div class="card">
  <label><?= e(__('Type')) ?><select name="incident_type"><?= options(IncidentsController::types()) ?></select></label>
  <label><?= e(__('When did it happen?')) ?><input type="datetime-local" name="occurred_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></label>
  <label><?= e(__('Where?')) ?><input name="location"></label>
  <?= Campus::field() ?>
  <label><?= e(__('How serious?')) ?><select name="severity"><?= options(['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'critical' => __('Critical')]) ?></select></label>
  <label class="check"><input type="checkbox" name="child_involved" value="1"> <?= e(__('A child or young person was involved')) ?></label>
  <p class="small muted"><?= e(__('Your report is encrypted and only seen by the people responsible for incidents and safeguarding.')) ?></p>
  <button class="btn btn-primary"><?= e(__('Submit report')) ?></button>
</div></div></form>
