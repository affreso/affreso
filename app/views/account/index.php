<div class="page-head"><div><h1><?= e(__('Account & security')) ?></h1><p><?= e($u['email']) ?></p></div></div>
<div class="split">
<div>
  <div class="card">
    <h2><?= e(__('Change password')) ?></h2>
    <form method="post" action="<?= e(url('/account/password')) ?>" class="form">
      <?= csrf_field() ?>
      <label><?= e(__('Current password')) ?><input type="password" name="current" required autocomplete="current-password"></label>
      <div class="grid-2">
        <label><?= e(__('New password')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label><?= e(__('Confirm new password')) ?><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      </div>
      <button class="btn btn-primary"><?= e(__('Change password')) ?></button>
    </form>
  </div>
  <?php if (count(available_languages()) > 1): ?>
  <div class="card"><h2><?= e(__('Language')) ?></h2>
    <form method="post" action="<?= e(url('/account/language')) ?>" class="form-row"><?= csrf_field() ?><label><?= e(__('Show Affreso in')) ?><select name="language"><?= options(['' => __('Church default')] + available_languages(), $u['language'] ?? '') ?></select></label><button class="btn"><?= e(__('Save')) ?></button></form>
  </div>
  <?php endif; ?>
  <div class="card">
    <h2><?= e(__('App & notifications')) ?></h2>
    <p class="muted"><?= e(__('Add Affreso to your phone\'s home screen: in Safari tap Share → Add to Home Screen; in Chrome tap ⋮ → Install app.')) ?></p>
    <?php if (Push::supported()): [$vapid] = Push::keys(); ?>
    <p><button type="button" class="btn" data-push-enable data-key="<?= e($vapid) ?>"><?= e(__('Turn on notifications on this device')) ?></button> <span class="small muted" data-push-status></span></p>
    <p class="small muted"><?= e(__('You will get a notification for roster invitations, reminders and messages. Notifications need HTTPS.')) ?></p>
    <?php endif; ?>
  </div>
  <?php if (CalendarSync::anyConfigured() || $calAccounts): ?><div class="card" id="calendar-sync">
    <h2><?= e(__('Calendar sync')) ?></h2>
    <p class="muted"><?= e(__('Connect your own calendar. Your rosters and events are added to it automatically, and leaders can see when you are busy (times only — never what the appointment is).')) ?></p>
    <?php foreach ($calAccounts as $a): ?>
      <div class="sync-row">
        <div><strong><?= e(CalendarSync::PROVIDERS[$a['provider']]) ?></strong><?= $a['account_email'] ? ' · ' . e($a['account_email']) : '' ?>
          <div class="small <?= $a['last_error'] ? 'text-danger' : 'muted' ?>"><?= e($a['last_error'] ? __('Problem: %s', $a['last_error']) : ($a['last_sync_at'] ? __('Last synced %s', fmt_datetime($a['last_sync_at'])) : __('Waiting for first sync'))) ?></div></div>
        <form method="post" action="<?= e(url('/account/calendar/' . $a['id'])) ?>" class="form"><?= csrf_field() ?>
          <label class="check"><input type="checkbox" name="push_enabled" value="1" <?= $a['push_enabled'] ? 'checked' : '' ?>> <?= e(__('Add my rosters and events to this calendar')) ?></label>
          <label class="check"><input type="checkbox" name="busy_enabled" value="1" <?= $a['busy_enabled'] ? 'checked' : '' ?>> <?= e(__('Let rostering see when I\'m busy')) ?></label>
          <button class="btn btn-sm"><?= e(__('Save')) ?></button>
          <button class="btn btn-sm" formaction="<?= e(url('/account/calendar/' . $a['id'] . '/sync')) ?>"><?= e(__('Sync now')) ?></button>
        </form>
        <form method="post" action="<?= e(url('/account/calendar/' . $a['id'] . '/disconnect')) ?>" class="inline-form" data-confirm="<?= e(__('Disconnect this calendar?')) ?>"><?= csrf_field() ?>
          <label class="check small"><input type="checkbox" name="remove_events" value="1" checked> <?= e(__('Also remove the events Affreso added')) ?></label>
          <button class="btn btn-sm btn-danger"><?= e(__('Disconnect')) ?></button></form>
      </div>
    <?php endforeach; ?>
    <?php foreach (CalendarSync::PROVIDERS as $prov => $label): if (!CalendarSync::configured($prov) || in_array($prov, array_column($calAccounts, 'provider'), true)) continue; ?>
      <form method="post" action="<?= e(url('/account/calendar/' . $prov . '/connect')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn"><?= e(__('Connect %s', $label)) ?></button></form>
    <?php endforeach; ?>
  </div><?php endif; ?>
  <div class="card">
    <h2><?= e(__('Calendar subscription')) ?></h2>
    <p class="muted"><?= e(__('Add your rosters, registered events and the church calendar to Google Calendar, Apple Calendar or Outlook by subscribing to this private link.')) ?></p>
    <input readonly value="<?= e(url('/ical/' . $u['ical_token'], [], true)) ?>" aria-label="<?= e(__('Calendar link')) ?>">
    <p class="small muted"><?= e(__('Keep this link private. If it is shared by mistake, replace it:')) ?></p>
    <form method="post" action="<?= e(url('/account/ical')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Replace calendar link')) ?></button></form>
  </div>
</div>
<div>
  <div class="card">
    <h2 class="h3"><?= e(__('Two-step verification')) ?></h2>
    <p><?= (int)$u['mfa_enabled'] ? badge('active') . ' ' . e(__('On')) : badge('disabled') . ' ' . e(__('Off')) ?></p>
    <a class="btn btn-sm" href="<?= e(url('/account/mfa')) ?>"><?= e(__('Manage')) ?></a>
  </div>
  <div class="card">
    <h2 class="h3"><?= e(__('Your roles')) ?></h2>
    <p><?= $roles ? e(implode(', ', $roles)) : '<span class="muted">' . e(__('None')) . '</span>' ?></p>
  </div>
  <div class="card">
    <h2 class="h3"><?= e(__('Recent sign-in activity')) ?></h2>
    <ul class="list-plain small">
      <?php foreach ($logins as $l): ?><li><?= e($l['action'] === 'login.success' ? __('Signed in') : __('Failed attempt')) ?> · <?= e(fmt_datetime($l['created_at'])) ?> · <?= e($l['ip']) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
</div>
