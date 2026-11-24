<?php $sevBadge = ['low' => 'grey', 'medium' => 'amber', 'high' => 'red', 'critical' => 'red']; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/incidents')) ?>"><?= e(__('Incident reports')) ?></a></div><h1><?= e($i['summary']) ?></h1>
<p><?= e(IncidentsController::types()[$i['incident_type']]) ?> · <?= e(fmt_datetime($i['occurred_at'])) ?><?= $i['location'] ? ' · ' . e($i['location']) : '' ?> · <span class="badge badge-<?= $sevBadge[$i['severity']] ?>"><?= e(__(ucfirst($i['severity']))) ?></span></p></div></div>
<?php if ((int)$i['child_involved'] && $manage): ?><div class="callout small"><?= e(__('Child involved: check mandatory reporting duties, your state Reportable Conduct Scheme (if the concern is about a worker or volunteer) and your denomination\'s safe ministry procedures. Record every notification below.')) ?></div><?php endif; ?>
<div class="split"><div>
<div class="card"><div class="lock-note">🔒 <?= e(__('Encrypted. Every view is recorded in the audit log.')) ?></div>
  <h2 class="h3"><?= e(__('What happened')) ?></h2><p class="lesson-body"><?= e($i['details']) ?></p>
  <?php foreach (['people' => __('People involved'), 'injury' => __('Injuries or harm'), 'action' => __('Immediate action')] as $k => $l): if ($i[$k] === '') continue; ?><h2 class="h3"><?= e($l) ?></h2><p class="lesson-body"><?= e($i[$k]) ?></p><?php endforeach; ?>
  <p class="small muted"><?= e(__('Reported by %s on %s', person_name($reporter) ?: ($reporter['email'] ?? '?'), fmt_datetime($i['created_at']))) ?></p>
</div>
<div class="card"><h2 class="h3"><?= e(__('Follow-up notes')) ?></h2>
  <ul class="list-plain"><?php foreach ($updates as $u): ?><li><span class="small muted"><?= e(fmt_datetime($u['created_at'])) ?> · <?= e(person_name($u) ?: $u['email']) ?></span><div><?= nl2br(e($u['note'])) ?></div></li><?php endforeach; ?></ul>
  <?php if (!$manage): ?><form method="post" action="<?= e(url('/incidents/' . $i['id'])) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Add information')) ?><textarea name="note" required></textarea></label><button class="btn btn-sm"><?= e(__('Add')) ?></button></form><?php endif; ?>
</div></div>
<div><?php if ($manage): ?>
<form method="post" action="<?= e(url('/incidents/' . $i['id'])) ?>" class="card form"><?= csrf_field() ?><h2 class="h3"><?= e(__('Manage')) ?></h2>
  <label><?= e(__('Status')) ?><select name="status"><?= options(['reported' => __('Reported'), 'reviewing' => __('Under review'), 'actioned' => __('Actioned'), 'closed' => __('Closed')], $i['status']) ?></select></label>
  <label><?= e(__('Severity')) ?><select name="severity"><?= options(['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'critical' => __('Critical')], $i['severity']) ?></select></label>
  <label><?= e(__('Assigned to')) ?><select name="assigned_to"><?= options($staff, $i['assigned_to'], __('Unassigned')) ?></select></label>
  <label class="check"><input type="checkbox" name="reportable_conduct" value="1" <?= (int)$i['reportable_conduct'] ? 'checked' : '' ?>> <?= e(__('Reportable conduct allegation (about a worker/volunteer)')) ?></label>
  <fieldset><legend><?= e(__('Notifications made')) ?></legend><div class="grid-2">
    <label><?= e(__('Police')) ?><input type="date" name="police_notified_on" value="<?= e($i['police_notified_on']) ?>"></label>
    <label><?= e(__('Child protection')) ?><input type="date" name="child_protection_notified_on" value="<?= e($i['child_protection_notified_on']) ?>"></label>
    <label><?= e(__('Regulator / ombudsman')) ?><input type="date" name="regulator_notified_on" value="<?= e($i['regulator_notified_on']) ?>"></label>
    <label><?= e(__('Insurer')) ?><input type="date" name="insurer_notified_on" value="<?= e($i['insurer_notified_on']) ?>"></label>
  </div></fieldset>
  <label><?= e(__('Outcome')) ?><textarea name="outcome"><?= e($i['outcome']) ?></textarea></label>
  <label><?= e(__('Add a follow-up note')) ?><textarea name="note"></textarea></label>
  <button class="btn btn-primary"><?= e(__('Save')) ?></button>
</form>
<?php else: ?><div class="card"><p><?= badge($i['status'] === 'closed' ? 'closed' : 'in_progress') ?> <?= e(__('The responsible people have your report.')) ?></p></div><?php endif; ?></div>
</div>
