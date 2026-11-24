<?php
$u = user();
$route = '/' . trim((string)($GLOBALS['AFFRESO_ROUTE'] ?? ''), '/');
$leads = $u ? led_group_ids() : [];
$isParent = $u && $u['person_id'] && DB::val("SELECT 1 FROM {p}people c JOIN {p}people a ON a.household_id = c.household_id WHERE a.id = ? AND a.household_role = 'adult' AND c.household_role = 'child' AND c.archived_at IS NULL LIMIT 1", [$u['person_id']]);
$unread = $u ? (int)DB::val('SELECT COUNT(*) FROM {p}notifications WHERE user_id = ? AND read_at IS NULL', [$u['id']]) : 0;
$nav = [
    __('My Affreso') => [
        ['/', __('Dashboard'), true],
        ['/me/rosters', __('My rosters'), true],
        ['/me/availability', __('Availability'), true],
        ['/me/training', __('My training'), true],
        ['/me/events', __('Events'), true],
        ['/me/groups', __('My groups'), true],
        ['/me/giving', __('My giving'), true],
        ['/me/children', __('My children'), (bool)$isParent],
        ['/prayer', __('Prayer'), true],
        ['/me/forms', __('Forms & sign-ups'), true],
        ['/library', __('Resource library'), true],
        ['/reimbursements', __('Reimbursements'), (bool)($u['person_id'] ?? false)],
        ['/directory', __('Directory'), can('directory.view')],
    ],
    __('Church') => [
        ['/people', __('Members & families'), can('people.view')],
        ['/care', __('Care & follow-up'), can('care.manage')],
        ['/groups', __('Ministries & groups'), can('groups.view') || $leads],
        ['/volunteers', __('Volunteers'), can('volunteers.manage') || $leads],
        ['/safeguarding', __('Safeguarding'), can('safeguarding.view')],
        ['/rosters', __('Rosters'), can('rosters.view') || can('rosters.manage') || $leads],
        ['/services', __('Service planning'), can('services.plan') || can('rosters.view')],
        ['/checkin', __("Children's check-in"), can('checkin.operate')],
        ['/attendance', __('Attendance'), can('attendance.record')],
        ['/forms', __('Forms'), can('forms.manage')],
        ['/events', __('Events & calendar'), can('events.manage')],
        ['/rooms', __('Rooms & bookings'), true],
        ['/courses', __('Courses & training'), can('courses.create') || can('courses.approve') || can('courses.assign') || can('training.reports')],
        ['/comms', __('Communications'), can('comms.send')],
        ['/giving', __('Giving'), can('giving.view')],
        ['/campaigns', __('Campaigns & pledges'), can('campaigns.manage')],
        ['/incidents', __('Incident reports'), can('incidents.manage')],
        ['/reports', __('Reports & insights'), can('reports.view') || can('training.reports')],
    ],
    __('Administration') => [
        ['/admin', __('Administration'), can('admin.users') || can('admin.roles') || can('admin.settings') || can('audit.view') || can('privacy.manage')],
    ],
];
$isActive = fn(string $p) => $p === '/' ? $route === '/' : ($route === $p || str_starts_with($route, $p . '/'));
?><!doctype html>
<html lang="<?= e(current_language()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(setting('church_name', 'Affreso')) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Crect width=%2732%27 height=%2732%27 rx=%277%27 fill=%27%23b9832f%27/%3E%3Ctext x=%2716%27 y=%2723%27 font-size=%2720%27 text-anchor=%27middle%27 fill=%27white%27 font-family=%27Georgia%27%3EA%3C/text%3E%3C/svg%3E">
<link rel="manifest" href="<?= e(url('/manifest')) ?>">
<meta name="theme-color" content="#1d4e57">
<link rel="apple-touch-icon" href="<?= e(base_path() . '/assets/icons/apple-touch-icon.png') ?>">
<meta name="base-path" content="<?= e(base_path()) ?>/">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= e(__('Skip to main content')) ?></a>
<div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="<?= e(__('Main navigation')) ?>">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="logo-mark">A</span><span><strong><?= e(setting('church_name', 'Affreso')) ?></strong><small>Affreso</small></span></a>
    <nav>
      <?php foreach ($nav as $section => $items): $visible = array_filter($items, fn($i) => $i[2]); if (!$visible) continue; ?>
        <div class="nav-section"><?= e($section) ?></div>
        <?php foreach ($visible as [$p, $label]): ?>
          <a href="<?= e(url($p)) ?>" class="<?= $isActive($p) ? 'active' : '' ?>"<?= $isActive($p) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="main-col">
    <header class="topbar">
      <button class="menu-btn" type="button" data-toggle="sidebar" aria-controls="sidebar" aria-label="<?= e(__('Open menu')) ?>">☰</button>
      <form class="topsearch" method="get" action="<?= e(base_path() . '/index.php') ?>" role="search">
        <?php if (can('people.view')): ?><input type="hidden" name="r" value="/people"><input type="search" name="q" placeholder="<?= e(__('Search people…')) ?>" aria-label="<?= e(__('Search people')) ?>"><?php endif; ?>
      </form>
      <div class="topbar-right">
        <?php if (Campus::multi()): ?>
          <?php if (Campus::restriction()): ?><span class="badge badge-blue"><?= e(Campus::name(Campus::restriction())) ?></span>
          <?php else: ?><form method="post" action="<?= e(url('/campus')) ?>" class="campus-switch"><?= csrf_field() ?><select name="campus" aria-label="<?= e(__('Campus')) ?>" data-autosubmit><?= options(Campus::all(), Campus::current(), __('All campuses')) ?></select><noscript><button class="btn btn-sm"><?= e(__('Go')) ?></button></noscript></form><?php endif; ?>
        <?php endif; ?>
        <a class="notif" href="<?= e(url('/notifications')) ?>" aria-label="<?= e($unread ? __('%d unread notifications', $unread) : __('Notifications')) ?>">🔔<?php if ($unread): ?><span class="count"><?= $unread ?></span><?php endif; ?></a>
        <details class="usermenu">
          <summary><?= e(person_name($u) ?: $u['email']) ?></summary>
          <div class="menu">
            <a href="<?= e(url('/me/profile')) ?>"><?= e(__('My profile')) ?></a>
            <a href="<?= e(url('/account')) ?>"><?= e(__('Account & security')) ?></a>
            <a href="<?= e(url('/me/privacy')) ?>"><?= e(__('Privacy & consent')) ?></a>
            <a href="<?= e(url('/incidents/new')) ?>"><?= e(__('Report an incident')) ?></a>
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= e(__('Sign out')) ?></button></form>
          </div>
        </details>
      </div>
    </header>
    <?php partial('partials/demo_banner'); ?>
    <main id="main" class="content" tabindex="-1">
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
    <footer class="footer">Affreso <?= e(AFFRESO_VERSION) ?> · <a href="<?= e(url('/privacy')) ?>"><?= e(__('Privacy')) ?></a></footer>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
