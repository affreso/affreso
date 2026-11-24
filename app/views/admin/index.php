<div class="page-head"><div><h1><?= e(__('Administration')) ?></h1><p><?= e(__('Access, settings, integrations, privacy and compliance.')) ?></p></div></div>
<div class="grid-4" style="margin-bottom:18px">
  <div class="stat"><div class="num"><?= (int)$stats['users'] ?></div><div class="lbl"><?= e(__('Active users')) ?></div></div>
  <div class="stat"><div class="num"><?= (int)$stats['mfa'] ?></div><div class="lbl"><?= e(__('Using two-step verification')) ?></div></div>
  <div class="stat"><div class="num"><?= (int)$stats['queued'] ?></div><div class="lbl"><?= e(__('Messages queued')) ?></div></div>
  <div class="stat<?= $stats['failed'] ? ' warn' : '' ?>"><div class="num"><?= (int)$stats['failed'] ?></div><div class="lbl"><?= e(__('Failed messages (7 days)')) ?></div></div>
</div>
<div class="split">
<div class="grid-2">
  <?php foreach ([['/admin/users', 'admin.users', __('Users'), __('Invite people, assign roles, reset access.')], ['/admin/roles', 'admin.roles', __('Roles & permissions'), __('Granular, least-privilege access for every role.')], ['/admin/settings', 'admin.settings', __('Settings & integrations'), __('Church details, email, SMS, Stripe, security.')], ['/admin/settings?tab=fields', 'admin.settings', __('Custom fields & funds'), __('Extra profile fields and giving funds.')], ['/admin/privacy', 'privacy.manage', __('Privacy & compliance'), __('Requests, retention and the data-breach register.')], ['/admin/audit', 'audit.view', __('Audit log'), __('Who did what, and when.')]] as [$l, $perm, $t, $d]): if (!can($perm)) continue; ?>
  <a class="card stat" href="<?= e(url_from($l)) ?>"><h2 class="h3" style="margin-top:0"><?= e($t) ?></h2><p class="small muted"><?= e($d) ?></p></a>
  <?php endforeach; ?>
</div>
<?php if (Demo::on() && !Demo::isDemoUser(Auth::id())): ?><div class="card" style="border-left:4px solid var(--gold)"><h2 class="h3"><?= e(__('Demo site')) ?></h2><p class="small"><?= e(__('Data resets to fresh sample data every night (when the scheduled task runs after midnight). Your own login is kept.')) ?></p><form method="post" action="<?= e(url('/admin/demo/reset')) ?>" data-confirm="<?= e(__('Erase everything and reload fresh sample data now?')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Reset demo now')) ?></button></form></div><?php endif; ?>
<div class="card"><h2 class="h3"><?= e(__('System health')) ?></h2>
  <ul class="checklist"><?php foreach ($health as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></li><?php endforeach; ?></ul>
  <p class="small muted"><?= e(__('Last scheduled task: %s', $cronRan ? fmt_datetime((string)$cronRan) : __('never'))) ?> · PHP <?= e(PHP_VERSION) ?> · Affreso <?= e(AFFRESO_VERSION) ?></p>
  <?php if (can('admin.settings')): ?><form method="post" action="<?= e(url('/admin/jobs/run')) ?>" class="form"><?= csrf_field() ?><label class="check"><input type="checkbox" name="daily" value="1"> <?= e(__('Include daily tasks (reminders, expiry checks)')) ?></label><button class="btn btn-sm"><?= e(__('Run scheduled tasks now')) ?></button></form><?php endif; ?>
</div>
</div>
