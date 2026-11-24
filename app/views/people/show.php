<?php $pid = (int)$p['id']; $age = age_on($p['birthdate']); ?>
<div class="page-head">
  <div>
    <div class="breadcrumb"><a href="<?= e(url('/people')) ?>"><?= e(__('Members & families')) ?></a></div>
    <h1><?= e(person_name($p)) ?> <?= badge($p['archived_at'] ? 'archived' : $p['membership_status']) ?></h1>
    <p><?= e(trim($p['first_name'] . ' ' . $p['last_name'])) ?><?= $age !== null ? ' · ' . e(__('Age %d', $age)) : '' ?><?= $p['household_role'] === 'child' ? ' · ' . e(__('Child')) : '' ?></p>
  </div>
  <div class="actions">
    <?php if (can('people.edit')): ?><a class="btn btn-primary" href="<?= e(url('/people/' . $pid . '/edit')) ?>"><?= e(__('Edit')) ?></a><?php endif; ?>
    <?php if (can('privacy.manage')): ?><a class="btn" href="<?= e(url('/people/' . $pid . '/export')) ?>"><?= e(__('Export data (access request)')) ?></a><?php endif; ?>
  </div>
</div>

<div class="split">
<div>
  <div class="card">
    <h2><?= e(__('Contact')) ?></h2>
    <dl class="kv">
      <dt><?= e(__('Email')) ?></dt><dd><?= $p['email'] ? '<a href="mailto:' . e($p['email']) . '">' . e($p['email']) . '</a>' : '—' ?></dd>
      <dt><?= e(__('Mobile')) ?></dt><dd><span dir="ltr"><?= e($p['mobile'] ?: '—') ?></span></dd>
      <dt><?= e(__('Address')) ?></dt><dd><?= e(trim(implode(', ', array_filter([$p['address_line'], $p['suburb'], trim(($p['state'] ?? '') . ' ' . ($p['postcode'] ?? ''))])))) ?: '—' ?></dd>
      <dt><?= e(__('Date of birth')) ?></dt><dd><?= e(fmt_date($p['birthdate']) ?: '—') ?></dd>
      <dt><?= e(__('First visit')) ?></dt><dd><?= e(fmt_date($p['first_visit']) ?: '—') ?></dd>
      <dt><?= e(__('Member since')) ?></dt><dd><?= e(fmt_date($p['joined_on']) ?: '—') ?></dd>
      <?php foreach ($fields as $f): if (($f['value'] ?? '') === '') continue; ?>
        <dt><?= e($f['label']) ?></dt><dd><?= e($f['field_type'] === 'checkbox' ? ($f['value'] ? __('Yes') : __('No')) : $f['value']) ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php if ($p['general_notes']): ?><h2 class="h3"><?= e(__('Notes')) ?></h2><p><?= nl2br(e($p['general_notes'])) ?></p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= e(__('Serving')) ?></h2><?php if (can('volunteers.manage')): ?><a class="small" href="<?= e(url('/volunteers', ['person' => $pid])) ?>"><?= e(__('Manage positions')) ?></a><?php endif; ?></div>
    <?php if (!$positions): ?><p class="empty"><?= e(__('Not approved for any volunteer positions.')) ?></p><?php endif; ?>
    <ul class="list-plain">
    <?php foreach ($positions as $po): $gaps = Volunteering::gaps($pid, (int)$po['position_id']); ?>
      <li><strong><?= e($po['name']) ?></strong> · <?= e($po['group_name']) ?> <?= $po['approved_at'] ? badge('approved') : badge('pending') ?> <?= $po['preference'] === 'preferred' ? '<span class="badge badge-gold">' . e(__('Preferred')) . '</span>' : '' ?>
        <?php if ($gaps): ?><ul class="warn-list"><?php foreach ($gaps as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul><?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
    <?php if ($upcoming): ?><h2 class="h3"><?= e(__('Upcoming rosters')) ?></h2><ul class="list-plain small">
      <?php foreach ($upcoming as $u): ?><li><?= e(fmt_date($u['service_date'], 'D j M')) ?> — <?= e($u['position']) ?> · <a href="<?= e(url('/rosters/' . $u['roster_id'])) ?>"><?= e($u['title']) ?></a> <?= badge($u['status']) ?></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </div>

  <div class="card">
    <h2><?= e(__('Training')) ?></h2>
    <?php if (!$training): ?><p class="empty"><?= e(__('No courses assigned.')) ?></p><?php endif; ?>
    <table><tbody>
    <?php foreach ($training as $t): ?>
      <tr><td><?= e($t['title']) ?></td><td><?= badge($t['status']) ?></td><td class="small muted"><?= $t['completed_at'] ? e(__('Completed %s', fmt_date($t['completed_at']))) : ($t['due_date'] ? e(__('Due %s', fmt_date($t['due_date']))) : '') ?><?= $t['expires_at'] ? ' · ' . e(__('Expires %s', fmt_date($t['expires_at']))) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>

  <?php if (can('safeguarding.view')): ?>
  <div class="card">
    <div class="card-head"><h2><?= e(__('Safeguarding checks')) ?></h2><a class="small" href="<?= e(url('/safeguarding', ['person' => $pid])) ?>"><?= e(__('Manage')) ?></a></div>
    <?php if (!$checks): ?><p class="empty"><?= e(__('No checks recorded.')) ?></p><?php endif; ?>
    <ul class="list-plain"><?php foreach ($checks as $c): ?><li><?= e($c['check_type']) ?> <?= e($c['jurisdiction']) ?> <?= badge($c['expires_on'] && $c['expires_on'] < date('Y-m-d') ? 'expired' : $c['status']) ?> <span class="muted small"><?= $c['expires_on'] ? e(__('Expires %s', fmt_date($c['expires_on']))) : '' ?></span></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <?php if ($p['household_role'] === 'child' && (can('children.medical') || can('checkin.operate'))): ?>
  <div class="card" id="children">
    <h2><?= e(__("Children's ministry")) ?></h2>
    <?php if (can('children.medical')): $info = CheckinController::careInfo($pid); ?>
      <div class="lock-note">🔒 <?= e(__('Care information is encrypted and only visible to people with children\'s medical access. Parents can update it from My Affreso → My children.')) ?></div>
      <div class="split"><div><?php partial('checkin/care_form', ['child' => $p, 'info' => $info]); ?></div>
      <div><?php partial('checkin/collectors', ['child' => $p, 'collectors' => DB::all('SELECT * FROM {p}authorised_collectors WHERE child_id = ?', [$pid])]); ?></div></div>
    <?php else: ?>
      <p><?= CheckinController::hasAlert($pid) ? '<span class="badge badge-red">⚠ ' . e(__('Care alert on file')) . '</span>' : e(__('No care alerts on file.')) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (can('pastoral.view')): ?>
  <div class="card" id="pastoral">
    <h2><?= e(__('Confidential pastoral notes')) ?></h2>
    <div class="lock-note">🔒 <?= e(__('Encrypted and visible only to people with pastoral access. Every view is recorded in the audit log.')) ?></div>
    <?php if (can('pastoral.edit')): ?>
    <form method="post" action="<?= e(url('/people/' . $pid . '/notes')) ?>" class="form">
      <?= csrf_field() ?>
      <div class="form-row">
        <label><?= e(__('Category')) ?><select name="category"><?= options(['general' => __('General'), 'visit' => __('Visit'), 'counselling' => __('Counselling'), 'prayer' => __('Prayer'), 'hospital' => __('Hospital'), 'other' => __('Other')]) ?></select></label>
      </div>
      <label><?= e(__('Note')) ?><textarea name="body" required></textarea></label>
      <button class="btn btn-primary btn-sm"><?= e(__('Save confidential note')) ?></button>
    </form>
    <?php endif; ?>
    <ul class="list-plain">
      <?php foreach ($notes as $n): ?><li><span class="badge badge-grey"><?= e(__(ucfirst($n['category']))) ?></span> <span class="small muted"><?= e(fmt_datetime($n['created_at'])) ?> · <?= e(person_name($n)) ?></span><div><?= nl2br(e($n['body'])) ?></div></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<div>
  <div class="card">
    <div class="card-head"><h2 class="h3"><?= e(__('Household')) ?></h2><?php if ($household): ?><a class="small" href="<?= e(url('/households/' . $household['id'])) ?>"><?= e(__('Open')) ?></a><?php endif; ?></div>
    <?php if ($household): ?>
      <p><strong><?= e($household['name']) ?></strong></p>
      <ul class="list-plain"><?php foreach ($family as $f): ?><li><a href="<?= e(url('/people/' . $f['id'])) ?>"><?= e(person_name($f)) ?></a> <span class="muted small"><?= e(__(ucfirst($f['household_role']))) ?></span></li><?php endforeach; ?></ul>
      <?php if (can('people.edit')): ?><a class="btn btn-sm" href="<?= e(url('/people/new', ['household' => $household['id']])) ?>">+ <?= e(__('Add family member')) ?></a><?php endif; ?>
    <?php else: ?><p class="empty"><?= e(__('Not linked to a household.')) ?></p><?php endif; ?>
  </div>

  <div class="card">
    <h2 class="h3"><?= e(__('Groups & ministries')) ?></h2>
    <?php if (!$groups): ?><p class="empty"><?= e(__('Not in any groups.')) ?></p><?php endif; ?>
    <ul class="list-plain"><?php foreach ($groups as $g): ?><li><a href="<?= e(url('/groups/' . $g['id'])) ?>"><?= e($g['name']) ?></a> <?= $g['member_role'] !== 'member' ? '<span class="badge badge-gold">' . e(__(ucfirst($g['member_role']))) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  </div>

  <?php if (can('care.manage')): ?>
  <div class="card">
    <h2 class="h3"><?= e(__('Care & follow-up')) ?></h2>
    <ul class="list-plain small"><?php foreach ($care as $c): ?><li><?= badge($c['status']) ?> <?= e($c['title']) ?><?= $c['due_date'] ? ' · ' . e(fmt_date($c['due_date'])) : '' ?></li><?php endforeach; ?></ul>
    <form method="post" action="<?= e(url('/care')) ?>" class="form">
      <?= csrf_field() ?><input type="hidden" name="person_id" value="<?= $pid ?>">
      <label><?= e(__('New follow-up')) ?><input name="title" required placeholder="<?= e(__('e.g. Call to welcome')) ?>"></label>
      <div class="form-row"><label><?= e(__('Type')) ?><select name="task_type"><?= options(CareController::types()) ?></select></label><label><?= e(__('Due')) ?><input type="date" name="due_date"></label></div>
      <p><button class="btn btn-sm"><?= e(__('Add follow-up')) ?></button></p>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2 class="h3"><?= e(__('Privacy & consent')) ?></h2>
    <p class="small"><?= (int)$p['consent_contact'] ? badge('approved') : badge('pending') ?> <?= e(__('Contact & record keeping')) ?><br><?= (int)$p['consent_photo'] ? badge('approved') : badge('declined') ?> <?= e(__('Photos')) ?><br><span class="muted"><?= e(__('Directory: %s', (int)$p['directory_visible'] ? __('listed') : __('hidden'))) ?></span></p>
    <?php if (can('people.edit')): ?>
    <details><summary class="small"><?= e(__('Update consent')) ?></summary>
    <form method="post" action="<?= e(url('/people/' . $pid . '/consent')) ?>" class="form">
      <?= csrf_field() ?>
      <label class="check"><input type="checkbox" name="consent_contact" value="1" <?= (int)$p['consent_contact'] ? 'checked' : '' ?>> <?= e(__('Contact & record keeping')) ?></label>
      <label class="check"><input type="checkbox" name="consent_photo" value="1" <?= (int)$p['consent_photo'] ? 'checked' : '' ?>> <?= e(__('Photos')) ?></label>
      <label><?= e(__('How was consent given?')) ?><input name="method" placeholder="<?= e(__('e.g. paper form, verbal, email')) ?>"></label>
      <button class="btn btn-sm"><?= e(__('Save consent')) ?></button>
    </form></details>
    <?php endif; ?>
    <?php if ($consents): ?><ul class="list-plain small muted"><?php foreach ($consents as $c): ?><li><?= e(ucfirst($c['consent_type'])) ?>: <?= $c['granted'] ? e(__('granted')) : e(__('withdrawn')) ?> · <?= e(fmt_date($c['created_at'])) ?> <?= $c['method'] ? '(' . e($c['method']) . ')' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>

  <?php if ($giving): ?>
  <div class="card"><h2 class="h3"><?= e(__('Recent giving')) ?></h2><ul class="list-plain small"><?php foreach ($giving as $g): ?><li><?= e(fmt_money((int)$g['amount_cents'])) ?> · <?= e($g['fund']) ?> · <?= e(fmt_date($g['received_on'] ?: $g['created_at'])) ?> <?= badge($g['status']) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card">
    <h2 class="h3"><?= e(__('Login account')) ?></h2>
    <?php if ($account): ?>
      <p class="small"><?= badge($account['status']) ?> <?= e($account['email']) ?><br><?= e(__('Last sign in: %s', $account['last_login_at'] ? fmt_datetime($account['last_login_at']) : __('never'))) ?><br><?= e(__('Two-step verification: %s', (int)$account['mfa_enabled'] ? __('on') : __('off'))) ?></p>
      <?php if (can('admin.users')): ?><a class="btn btn-sm" href="<?= e(url('/admin/users/' . $account['id'])) ?>"><?= e(__('Manage account')) ?></a><?php endif; ?>
    <?php elseif (can('admin.users') && !$p['archived_at']): ?>
      <form method="post" action="<?= e(url('/people/' . $pid . '/invite')) ?>" class="form"><?= csrf_field() ?>
        <label><?= e(__('Starting role')) ?><select name="role"><?= options(['member' => __('Member'), 'volunteer' => __('Volunteer')]) ?></select></label>
        <button class="btn btn-sm btn-primary"><?= e(__('Invite to Affreso')) ?></button></form>
    <?php else: ?><p class="empty"><?= e(__('No login account.')) ?></p><?php endif; ?>
  </div>

  <?php if (can('people.delete')): ?>
  <div class="card">
    <h2 class="h3"><?= e(__('Archive or erase')) ?></h2>
    <?php if ($p['archived_at']): ?>
      <form method="post" action="<?= e(url('/people/' . $pid . '/restore')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Restore record')) ?></button></form>
      <details><summary class="small"><?= e(__('Permanently de-identify')) ?></summary>
      <form method="post" action="<?= e(url('/people/' . $pid . '/erase')) ?>" class="form" data-confirm="<?= e(__('This cannot be undone. Continue?')) ?>"><?= csrf_field() ?>
        <p class="small muted"><?= e(__('Removes names, contact details, notes and memberships. Financial totals and anonymous statistics are kept.')) ?></p>
        <label class="check"><input type="checkbox" name="confirm_retention" value="1"> <?= e(__('I have checked our record-retention obligations (including child-safety records)')) ?></label>
        <label><?= e(__('Type the surname to confirm')) ?><input name="confirm_name" required></label>
        <button class="btn btn-sm btn-danger"><?= e(__('Erase personal information')) ?></button>
      </form></details>
    <?php else: ?>
      <form method="post" action="<?= e(url('/people/' . $pid . '/archive')) ?>" data-confirm="<?= e(__('Archive this person? Their login will be disabled.')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Archive')) ?></button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</div>
