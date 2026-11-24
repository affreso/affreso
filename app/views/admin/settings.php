<?php
$tabs = ['general' => __('General'), 'email' => __('Email'), 'sms' => __('SMS'), 'payments' => __('Online giving'), 'security' => __('Security'), 'privacy' => __('Privacy & retention'), 'fields' => __('Custom fields & funds'), 'campuses' => __('Campuses'), 'integrations' => __('Integrations'), 'cron' => __('Scheduled tasks')];
$text = function (string $k, string $label, string $help = '', string $type = 'text') use ($v) {
    echo '<label>' . e($label) . '<input type="' . e($type) . '" name="' . e($k) . '" value="' . e($v[$k] ?? '') . '">' . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</label>';
};
$secret = function (string $k, string $label, string $help = '') use ($v) {
    $set = ($v[$k] ?? '') === '__set__';
    echo '<label>' . e($label) . '<input type="password" name="' . e($k) . '" autocomplete="new-password" placeholder="' . e($set ? __('•••••••• saved — leave blank to keep') : '') . '">' . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</label>';
    if ($set) {
        echo '<label class="check small"><input type="checkbox" name="clear_' . e($k) . '" value="1"> ' . e(__('Remove saved value')) . '</label>';
    }
};
$check = function (string $k, string $label) use ($v) {
    echo '<label class="check"><input type="checkbox" name="' . e($k) . '" value="1"' . (($v[$k] ?? '') === '1' ? ' checked' : '') . '> ' . e($label) . '</label>';
};
?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin')) ?>"><?= e(__('Administration')) ?></a></div><h1><?= e(__('Settings')) ?></h1></div></div>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('/admin/settings', ['tab' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>

<?php if ($tab === 'fields'): ?>
<div class="split">
<div class="card"><h2><?= e(__('Custom profile fields')) ?></h2>
  <table><tbody><?php foreach ($fields as $f): ?><tr><td><?= e($f['label']) ?></td><td class="small muted"><?= e($f['field_type']) ?> <?= e($f['options']) ?></td><td class="actions-cell"><form method="post" action="<?= e(url('/admin/fields/' . $f['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this field and all its values?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete')) ?></button></form></td></tr><?php endforeach; ?></tbody></table>
  <?php if (!$fields): ?><p class="empty"><?= e(__('No custom fields yet.')) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/fields')) ?>" class="form" style="margin-top:12px"><?= csrf_field() ?>
    <div class="grid-2"><label><?= e(__('Label')) ?><input name="label" required placeholder="<?= e(__('e.g. Baptism date')) ?>"></label><label><?= e(__('Type')) ?><select name="field_type"><?= options(['text' => __('Text'), 'date' => __('Date'), 'select' => __('Choice list'), 'checkbox' => __('Yes / no')]) ?></select></label></div>
    <label><?= e(__('Choices (comma separated, for choice lists)')) ?><input name="options"></label>
    <button class="btn btn-sm btn-primary"><?= e(__('Add field')) ?></button></form>
  <p class="small muted"><?= e(__('Do not create custom fields for sensitive information such as health conditions unless you have a clear need, consent and restricted access.')) ?></p>
</div>
<div class="card"><h2><?= e(__('Giving funds')) ?></h2>
  <?php foreach ($funds as $f): ?><form method="post" action="<?= e(url('/admin/funds')) ?>" class="form-row"><?= csrf_field() ?><input type="hidden" name="fund_id" value="<?= (int)$f['id'] ?>"><label><input name="name" value="<?= e($f['name']) ?>" aria-label="<?= e(__('Fund name')) ?>"></label><label class="check"><input type="checkbox" name="active" value="1" <?= (int)$f['active'] ? 'checked' : '' ?>> <?= e(__('Active')) ?></label><button class="btn btn-sm"><?= e(__('Save')) ?></button></form><?php endforeach; ?>
  <form method="post" action="<?= e(url('/admin/funds')) ?>" class="form-row" style="margin-top:10px"><?= csrf_field() ?><label><?= e(__('New fund')) ?><input name="name" required></label><button class="btn btn-sm btn-primary"><?= e(__('Add')) ?></button></form>
</div>
</div>

<?php elseif ($tab === 'campuses'): ?>
<div class="split"><div class="card"><h2><?= e(__('Campuses')) ?></h2>
  <p class="small muted"><?= e(__('With more than one campus, people, groups, events, rosters and rooms can belong to a campus, staff can switch campus from the top bar, and user accounts can be limited to one campus.')) ?></p>
  <?php foreach ($campuses as $c): ?><form method="post" action="<?= e(url('/admin/campuses')) ?>" class="form-row" style="margin-bottom:8px"><?= csrf_field() ?><input type="hidden" name="campus_id" value="<?= (int)$c['id'] ?>"><label><?= e(__('Name')) ?><input name="name" value="<?= e($c['name']) ?>"></label><label><?= e(__('Address')) ?><input name="address" value="<?= e($c['address']) ?>"></label><label class="check"><input type="checkbox" name="active" value="1" <?= (int)$c['active'] ? 'checked' : '' ?>> <?= e(__('Active')) ?></label><span class="small muted"><?= e(__('%d people', (int)$c['people'])) ?></span><button class="btn btn-sm"><?= e(__('Save')) ?></button></form><?php endforeach; ?>
</div><div class="card"><h2 class="h3"><?= e(__('Add a campus')) ?></h2><form method="post" action="<?= e(url('/admin/campuses')) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Name')) ?><input name="name" required></label><label><?= e(__('Address')) ?><input name="address"></label><button class="btn btn-primary"><?= e(__('Add campus')) ?></button></form></div></div>

<?php elseif ($tab === 'cron'): ?>
<div class="card">
  <h2><?= e(__('Scheduled tasks (cron)')) ?></h2>
  <p><?= e(__('Affreso sends queued emails and SMS, roster reminders, WWCC and training expiry alerts through a scheduled task. Set it to run every 5–15 minutes in your hosting control panel (cPanel → Cron Jobs).')) ?></p>
  <p><strong><?= e(__('Command (recommended):')) ?></strong></p>
  <input readonly value="php <?= e(BASE_DIR) ?>/cron.php >/dev/null 2>&1">
  <p><strong><?= e(__('Or, if your host only allows a web address:')) ?></strong></p>
  <input readonly value="<?= e(base_url() . '/cron.php?key=' . setting('cron_key')) ?>">
  <p class="small muted"><?= e(__('Last run: %s', $cronRan ? fmt_datetime((string)$cronRan) : __('never'))) ?></p>
  <form method="post" action="<?= e(url('/admin/settings')) ?>"><?= csrf_field() ?><input type="hidden" name="tab" value="cron"><input type="hidden" name="regenerate_cron_key" value="1"><button class="btn btn-sm" data-confirm="<?= e(__('The old web address will stop working. Continue?')) ?>"><?= e(__('Generate a new cron key')) ?></button></form>
</div>

<?php else: ?>
<div class="split"><div class="card">
<form method="post" action="<?= e(url('/admin/settings')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
<?php if ($tab === 'general'): ?>
  <h2><?= e(__('Church details')) ?></h2>
  <?php $text('church_name', __('Church name')); $text('church_abn', __('ABN'), __('Shown on giving receipts.')); ?>
  <label><?= e(__('State / territory')) ?><select name="church_state"><?= options(au_states() + ['OTHER' => __('Outside Australia')], $v['church_state']) ?></select></label>
  <?php $text('church_email', __('Office email'), '', 'email'); $text('church_phone', __('Office phone')); ?>
  <label><?= e(__('Address')) ?><textarea name="church_address"><?= e($v['church_address']) ?></textarea></label>
  <div class="grid-2"><label><?= e(__('Interface language')) ?><select name="language"><?= options($langs, $v['language']) ?></select><span class="help"><?= e(__('Add translations by placing a file in /lang (see docs/TRANSLATING.md).')) ?></span></label>
  <label><?= e(__('Currency')) ?><select name="currency"><?= options(['AUD' => 'AUD', 'NZD' => 'NZD', 'USD' => 'USD', 'GBP' => 'GBP', 'CAD' => 'CAD', 'EUR' => 'EUR'], $v['currency']) ?></select></label></div>
  <label><?= e(__('Who receives new connect cards?')) ?><select name="connect_followup_user"><?= options($staff, $v['connect_followup_user'], __('Everyone with care access')) ?></select></label>
<?php elseif ($tab === 'email'): ?>
  <h2><?= e(__('Email (SMTP)')) ?></h2>
  <p class="small muted"><?= e(__('Use your church email provider (Microsoft 365, Google Workspace) or a sending service (Postmark, Mailgun, Amazon SES). Without SMTP, Affreso tries PHP mail(), which often lands in spam.')) ?></p>
  <div class="grid-2"><?php $text('mail_from_name', __('From name')); $text('mail_from_email', __('From email'), '', 'email'); $text('mail_reply_to', __('Reply-to email (optional)'), '', 'email'); ?></div>
  <div class="grid-2"><?php $text('smtp_host', __('SMTP server'), __('e.g. smtp.office365.com, smtp.gmail.com')); $text('smtp_port', __('Port'), __('587 for STARTTLS, 465 for SSL')); ?>
  <label><?= e(__('Security')) ?><select name="smtp_security"><?= options(['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => __('None (not recommended)')], $v['smtp_security']) ?></select></label>
  <?php $text('smtp_user', __('Username')); ?></div>
  <?php $secret('smtp_pass', __('Password / app password'), __('Stored encrypted.')); ?>
<?php elseif ($tab === 'sms'): ?>
  <h2><?= e(__('SMS')) ?></h2>
  <label><?= e(__('Provider')) ?><select name="sms_provider"><?= options(['none' => __('Off'), 'clicksend' => 'ClickSend', 'twilio' => 'Twilio'], $v['sms_provider']) ?></select></label>
  <div class="grid-2"><?php $text('sms_username', __('ClickSend username / Twilio Account SID')); $secret('sms_api_key', __('ClickSend API key / Twilio auth token')); $text('sms_from', __('Sender ID or number'), __('ClickSend: alphanumeric sender ID or number. Twilio: your number (+61…) or Messaging Service SID (MG…).')); $text('sms_default_country_code', __('Default country code'), __('61 for Australia')); ?></div>
<?php elseif ($tab === 'payments'): ?>
  <h2><?= e(__('Online giving (Stripe)')) ?></h2>
  <ol class="small steps"><li><?= e(__('Create a Stripe account for the church and copy the API keys (Developers → API keys).')) ?></li><li><?= e(__('Add a webhook endpoint pointing to the address below, for the events checkout.session.completed, checkout.session.async_payment_succeeded and charge.refunded, then paste its signing secret here.')) ?></li></ol>
  <input readonly value="<?= e(url('/stripe/webhook', [], true)) ?>">
  <?php $text('stripe_publishable_key', __('Publishable key (pk_…)')); $secret('stripe_secret_key', __('Secret key (sk_… or rk_…)')); $secret('stripe_webhook_secret', __('Webhook signing secret (whsec_…)')); ?>
  <label><?= e(__('Statement on receipts')) ?><textarea name="giving_dgr_statement"><?= e($v['giving_dgr_statement']) ?></textarea><span class="help"><?= e(__('Only state that gifts are tax deductible if the church or fund has Deductible Gift Recipient (DGR) status. Check with the ATO / ACNC.')) ?></span></label>
<?php elseif ($tab === 'security'): ?>
  <h2><?= e(__('Security')) ?></h2>
  <?php $check('mfa_required_for_staff', __('Require two-step verification for anyone with sensitive permissions (recommended)')); $check('pastoral_access_logging', __('Record every view of confidential pastoral notes in the audit log')); $check('swap_requires_approval', __('Roster swaps need ministry leader approval')); ?>
  <div class="grid-2"><?php $text('session_timeout_minutes', __('Sign out after inactivity (minutes)'), '', 'number'); $text('roster_reminder_days', __('Send roster reminders (days before)'), '', 'number'); $text('wwcc_reminder_days', __('Warn about expiring WWCCs (days before)'), '', 'number'); ?></div>
<?php elseif ($tab === 'integrations'): ?>
  <h2><?= e(__('Bank file (ABA) for paying reimbursements')) ?></h2>
  <p class="small muted"><?= e(__('Find these in your online banking under "direct entry" or "bulk payments". The APCA / User ID is issued by your bank (often 000000 for self-generated files — check with your bank).')) ?></p>
  <div class="grid-2"><?php $text('aba_bank', __('Bank code (3 letters, e.g. CBA, WBC, NAB, ANZ)')); $text('aba_user_name', __('User name (as registered with the bank)')); $text('aba_account_name', __('Church account name')); $text('aba_apca_id', __('APCA / User ID (6 digits)')); $text('aba_bsb', 'BSB'); $text('aba_account', __('Account number')); ?></div>
  <?php $check('aba_balance', __('Add a balancing debit line (some banks require this)')); ?>
  <h2><?= e(__('Xero / MYOB')) ?></h2>
  <div class="grid-2"><?php $text('xero_expense_account', __('Expense account code for reimbursements'), __('e.g. 400')); $text('xero_income_account', __('Income account code for donations'), __('e.g. 200')); ?></div>
  <h2>Mailchimp</h2>
  <p class="small muted"><?= e(__('Keeps a Mailchimp audience in step with people who have consented to contact and email. Unsubscribes in Mailchimp are respected.')) ?></p>
  <div class="grid-2"><?php $secret('mailchimp_api_key', __('API key'), __('Mailchimp → Profile → Extras → API keys')); $text('mailchimp_list_id', __('Audience ID'), __('Audience → Settings → Audience name and defaults')); ?></div>
  <h2><?= e(__('Calendar sync (Google and Microsoft)')) ?></h2>
  <p class="small muted"><?= e(__('Lets people connect their own calendar from Account & security. Register an app with Google Cloud and/or Microsoft Entra and enter its details here. Use this redirect address:')) ?> <code><?= e(CalendarSync::redirectUri()) ?></code></p>
  <div class="grid-2"><?php $text('google_client_id', __('Google client ID'), __('Google Cloud → APIs & Services → Credentials → OAuth client (Web application). Enable the Google Calendar API.')); $secret('google_client_secret', __('Google client secret')); ?></div>
  <div class="grid-2"><?php $text('ms_client_id', __('Microsoft application (client) ID'), __('Microsoft Entra → App registrations → New registration (Web). Add the delegated permission Calendars.ReadWrite.')); $secret('ms_client_secret', __('Microsoft client secret')); $text('ms_tenant', __('Microsoft tenant'), __('Leave blank to allow any Microsoft account (personal or work).')); ?></div>
  <h2 id="labels"><?= e(__('Check-in label printing')) ?></h2>
  <p class="small muted"><?= e(__('Labels can always be printed from the browser. For silent printing straight to a label printer (no print dialog), create a free PrintNode account at printnode.com, install the PrintNode client on the computer the label printer is connected to, and enter the API key here.')) ?></p>
  <div class="grid-2"><?php $secret('printnode_api_key', __('PrintNode API key'), __('PrintNode → Account → API keys')); ?>
  <label><?= e(__('Default label printer')) ?><?php if ($printers): ?><select name="label_printer_id"><?= options($printers, $v['label_printer_id'], __('— None (print from the browser) —')) ?></select>
    <?php else: ?><input name="label_printer_id" value="<?= e($v['label_printer_id']) ?>" inputmode="numeric"><?php endif; ?>
    <span class="help"><?= e($printerError ? __('Problem: %s', $printerError) : ($printers ? __('Kiosks can use a different printer (Children\'s check-in → Kiosks).') : __('Save the API key to choose from your printers.'))) ?></span></label></div>
  <div class="grid-2">
    <label><?= e(__('What phone notifications show')) ?><select name="push_content"><?= options(['title' => __('The notification title (encrypted end to end)'), 'none' => __('Nothing — "You have a new notification" only')], $v['push_content'] ?: 'title') ?></select></label>
    <?php $text('event_series_horizon', __('Schedule repeating events this many months ahead'), __('1–24. More dates are added automatically each night.'), 'number'); ?>
  </div>
<?php elseif ($tab === 'privacy'): ?>
  <h2><?= e(__('Privacy & retention')) ?></h2>
  <div class="grid-2"><?php $text('privacy_officer', __('Privacy officer')); $text('privacy_contact', __('Privacy contact (email or phone)')); ?></div>
  <div class="grid-2"><?php $text('retention_visitor_months', __('Review inactive visitor records after (months)'), '', 'number'); $text('retention_archived_years', __('Review archived records for de-identification after (years)'), '', 'number'); $text('retention_audit_years', __('Keep audit log for (years)'), '', 'number'); $text('retention_messages_months', __('Keep sent message content for (months)'), '', 'number'); ?></div>
  <label><?= e(__('Privacy policy (shown at /privacy — leave blank to use the template)')) ?><textarea name="privacy_policy_text" rows="12"><?= e($v['privacy_policy_text']) ?></textarea></label>
<?php endif; ?>
  <button class="btn btn-primary"><?= e(__('Save settings')) ?></button>
</form>
</div>
<div>
<?php if ($tab === 'email'): ?><div class="card"><h2 class="h3"><?= e(__('Test')) ?></h2><form method="post" action="<?= e(url('/admin/settings/test-email')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Send a test email to me')) ?></button></form><p class="small muted"><?= e(__('Save your settings first.')) ?></p></div>
<?php elseif ($tab === 'sms'): ?><div class="card"><h2 class="h3"><?= e(__('Test')) ?></h2><form method="post" action="<?= e(url('/admin/settings/test-sms')) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Mobile number')) ?><input name="to" required></label><button class="btn"><?= e(__('Send test SMS')) ?></button></form></div>
<?php elseif ($tab === 'integrations'): ?><div class="card"><h2 class="h3"><?= e(__('Label printing')) ?></h2>
  <p class="small"><?= e(__('Label font: %s', $customFont ? __('your uploaded font') : 'DejaVu Sans')) ?></p>
  <p class="small muted"><?= e(__('The built-in font covers Latin, Greek, Cyrillic, Arabic and Persian names (including Vietnamese). For Chinese, Korean or Japanese names, upload a font such as Noto Sans CJK (free from fonts.google.com).')) ?></p>
  <form method="post" action="<?= e(url('/admin/label-font')) ?>" enctype="multipart/form-data" class="form"><?= csrf_field() ?><label><?= e(__('Font file (.ttf, .otf or .ttc)')) ?><input type="file" name="font" accept=".ttf,.otf,.ttc"></label><button class="btn btn-sm"><?= e(__('Upload font')) ?></button></form>
  <?php if ($customFont): ?><form method="post" action="<?= e(url('/admin/label-font')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><button class="btn btn-sm"><?= e(__('Use the built-in font')) ?></button></form><?php endif; ?>
  <p style="margin-top:12px"><a class="btn btn-sm" href="<?= e(url('/admin/test-label', ['download' => 1])) ?>" target="_blank" rel="noopener"><?= e(__('Preview a test label (PDF)')) ?></a></p>
  <?php if ($printers): ?><form method="post" action="<?= e(url('/admin/test-label')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Print a test label')) ?></button></form><?php endif; ?>
</div><div class="card"><h2 class="h3">Mailchimp</h2><form method="post" action="<?= e(url('/admin/mailchimp/sync')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Sync now')) ?></button></form><p class="small muted"><?= e(__('Also runs nightly. Last sync: %s', setting('mailchimp_last_sync') ? fmt_datetime((string)setting('mailchimp_last_sync')) : __('never'))) ?></p></div>
<?php elseif ($tab === 'privacy'): ?><div class="card small"><h2 class="h3"><?= e(__('Australian requirements — verify before going live')) ?></h2><p><?= e(__('Many churches with annual turnover under $3 million are not bound by the Privacy Act, but most choose to follow the Australian Privacy Principles, and some state laws, denominational policies and funding agreements require it. Child-safe recordkeeping rules (for example under state Child Safe Standards and Reportable Conduct schemes) often require long retention of safeguarding records. Confirm your obligations with your denomination or a lawyer.')) ?></p></div>
<?php endif; ?>
</div></div>
<?php endif; ?>
