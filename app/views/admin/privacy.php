<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin')) ?>"><?= e(__('Administration')) ?></a></div><h1><?= e(__('Privacy & compliance')) ?></h1><p><?= e(__('Tools to support the Australian Privacy Principles, consent, retention and breach response. Legal requirements vary — verify yours before relying on them.')) ?></p></div></div>
<div class="tabs"><?php foreach (['requests' => __('Privacy requests'), 'breaches' => __('Data-breach register'), 'retention' => __('Retention review'), 'consent' => __('Consent overview')] as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('/admin/privacy', ['tab' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>

<?php if ($tab === 'requests'): ?>
<div class="split">
<div class="card table-wrap"><p class="small muted"><?= e(__('Respond to access and correction requests within a reasonable period — 30 days is the usual benchmark. Use "Export data" on the person\'s profile to provide their information.')) ?></p>
<table><thead><tr><th><?= e(__('Received')) ?></th><th><?= e(__('Person')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Due')) ?></th><th><?= e(__('Status / outcome')) ?></th></tr></thead><tbody>
<?php foreach ($requests as $r): $overdue = $r['status'] !== 'closed' && $r['due_on'] < date('Y-m-d'); ?><tr><td class="small"><?= e(fmt_date($r['received_on'])) ?></td><td><?= $r['person_id'] ? '<a href="' . e(url('/people/' . $r['person_id'])) . '">' . e(person_name($r)) . '</a>' : '—' ?><div class="small muted"><?= e(mb_strimwidth((string)$r['details'], 0, 120, '…')) ?></div></td><td><?= e(__(ucfirst($r['request_type']))) ?></td><td class="small <?= $overdue ? 'badge badge-red' : '' ?>"><?= e(fmt_date($r['due_on'])) ?></td>
<td><form method="post" action="<?= e(url('/admin/privacy/requests/' . $r['id'])) ?>" class="form"><?= csrf_field() ?><select name="status" aria-label="<?= e(__('Status')) ?>"><?= options(['open' => __('Open'), 'in_progress' => __('In progress'), 'closed' => __('Closed')], $r['status']) ?></select><input name="outcome" value="<?= e($r['outcome']) ?>" placeholder="<?= e(__('Outcome')) ?>" aria-label="<?= e(__('Outcome')) ?>"><button class="btn btn-sm"><?= e(__('Save')) ?></button></form></td></tr><?php endforeach; ?>
<?php if (!$requests): ?><tr><td colspan="5" class="empty"><?= e(__('No privacy requests.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="card"><h2 class="h3"><?= e(__('Log a request')) ?></h2><form method="post" action="<?= e(url('/admin/privacy/requests')) ?>" class="form"><?= csrf_field() ?>
<label><?= e(__('Person')) ?><select name="person_id"><?= options(people_options(), null, '—') ?></select></label>
<label><?= e(__('Type')) ?><select name="request_type"><?= options(['access' => __('Access'), 'correction' => __('Correction'), 'deletion' => __('Deletion'), 'complaint' => __('Complaint')]) ?></select></label>
<label><?= e(__('Received')) ?><input type="date" name="received_on" value="<?= e(date('Y-m-d')) ?>"></label><label><?= e(__('Details')) ?><textarea name="details"></textarea></label><button class="btn btn-primary"><?= e(__('Log request')) ?></button></form></div>
</div>

<?php elseif ($tab === 'breaches'): ?>
<div class="split">
<div class="card table-wrap"><table><thead><tr><th><?= e(__('Discovered')) ?></th><th><?= e(__('Summary')) ?></th><th><?= e(__('Stage')) ?></th><th><?= e(__('Assess by')) ?></th></tr></thead><tbody>
<?php foreach ($breaches as $b): ?><tr><td class="small"><?= e(fmt_datetime($b['discovered_at'])) ?></td><td><a href="<?= e(url('/admin/breaches/' . $b['id'])) ?>"><?= e($b['summary']) ?></a></td><td><?= badge($b['stage'] === 'closed' ? 'closed' : 'in_progress') ?> <?= e(__(ucfirst($b['stage']))) ?></td><td class="small"><?= e(fmt_date($b['assessment_due'])) ?></td></tr><?php endforeach; ?>
<?php if (!$breaches): ?><tr><td colspan="4" class="empty"><?= e(__('No breaches recorded.')) ?></td></tr><?php endif; ?></tbody></table></div>
<div class="card"><h2 class="h3"><?= e(__('Record a suspected breach')) ?></h2><div class="callout small"><?= e(__('1. Contain  2. Assess (within 30 days)  3. Notify the OAIC and affected people if serious harm is likely  4. Review and prevent.')) ?></div>
<form method="post" action="<?= e(url('/admin/breaches')) ?>" class="form"><?= csrf_field() ?><label><?= e(__('What happened?')) ?><input name="summary" required></label><label><?= e(__('Discovered')) ?><input type="datetime-local" name="discovered_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></label><label><?= e(__('Information involved')) ?><textarea name="data_involved"></textarea></label><label><?= e(__('Approx. people affected')) ?><input type="number" name="people_affected"></label><label><?= e(__('Details')) ?><textarea name="details"></textarea></label><button class="btn btn-danger"><?= e(__('Record breach')) ?></button></form></div>
</div>

<?php elseif ($tab === 'retention'): ?>
<form method="post" action="<?= e(url('/admin/retention/run')) ?>" class="card" data-confirm="<?= e(__('Archive the selected records?')) ?>"><?= csrf_field() ?>
<h2><?= e(__('Inactive visitor records')) ?></h2><p class="small muted"><?= e(__('Visitors added more than %d months ago with no recent activity, group membership or sign-in.', (int)setting('retention_visitor_months', 24))) ?></p>
<?php foreach ($candidates['stale_visitors'] as $p): ?><label class="check"><input type="checkbox" name="archive_ids[]" value="<?= (int)$p['id'] ?>" checked> <a href="<?= e(url('/people/' . $p['id'])) ?>"><?= e(person_name($p)) ?></a> <span class="small muted"><?= e(__('added %s', fmt_date($p['created_at']))) ?></span></label><?php endforeach; ?>
<?php if (!$candidates['stale_visitors']): ?><p class="empty"><?= e(__('None.')) ?></p><?php else: ?><button class="btn btn-primary"><?= e(__('Archive selected')) ?></button><?php endif; ?>
</form>
<div class="card"><h2><?= e(__('Archived records past the retention period')) ?></h2><p class="small muted"><?= e(__('Review each record and de-identify it from the person\'s profile if you no longer need it. Records with safeguarding history are flagged — child-safety records often must be kept for decades.')) ?></p>
<ul class="list-plain"><?php foreach ($candidates['old_archived'] as $p): ?><li><a href="<?= e(url('/people/' . $p['id'])) ?>"><?= e(person_name($p)) ?></a> <span class="small muted"><?= e(__('archived %s', fmt_date($p['archived_at']))) ?></span> <?= (int)$p['safeguarding'] ? '<span class="badge badge-gold">' . e(__('safeguarding records — retain')) . '</span>' : '' ?></li><?php endforeach; ?></ul>
<?php if (!$candidates['old_archived']): ?><p class="empty"><?= e(__('None.')) ?></p><?php endif; ?></div>
<p class="small muted"><?= e(__('Automatic clean-up (daily): login attempts after 90 days, notifications after 180 days, sent message content after %d months, audit log after %d years.', (int)setting('retention_messages_months', 24), (int)setting('retention_audit_years', 7))) ?></p>

<?php else: ?>
<div class="grid-4"><div class="stat"><div class="num"><?= (int)$consent['people'] ?></div><div class="lbl"><?= e(__('Active records')) ?></div></div><div class="stat"><div class="num"><?= (int)$consent['contact'] ?></div><div class="lbl"><?= e(__('Consented to contact')) ?></div></div><div class="stat"><div class="num"><?= (int)$consent['photo'] ?></div><div class="lbl"><?= e(__('Photo consent')) ?></div></div><div class="stat"><div class="num"><?= (int)$consent['directory'] ?></div><div class="lbl"><?= e(__('Listed in directory')) ?></div></div></div>
<p class="small muted" style="margin-top:14px"><?= e(__('People without contact consent are automatically excluded from bulk email and SMS.')) ?> <a href="<?= e(url('/people', ['q' => ''])) ?>"><?= e(__('Review records')) ?></a></p>
<?php endif; ?>
