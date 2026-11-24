<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin')) ?>"><?= e(__('Administration')) ?></a></div><h1><?= e(__('Users')) ?></h1></div></div>
<div class="split">
<div>
<form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="r" value="/admin/users"><label><?= e(__('Search')) ?><input type="search" name="q" value="<?= e($q) ?>"></label><label><?= e(__('Role')) ?><select name="role"><?= options($roles, $role, __('Any')) ?></select></label><button class="btn"><?= e(__('Filter')) ?></button></form>
<div class="card table-wrap"><table><thead><tr><th><?= e(__('User')) ?></th><th><?= e(__('Roles')) ?></th><th><?= e(__('2-step')) ?></th><th><?= e(__('Last sign in')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?><tr><td><a href="<?= e(url('/admin/users/' . $u['id'])) ?>"><?= e(person_name($u) ?: $u['email']) ?></a><div class="small muted"><?= e($u['email']) ?></div></td><td class="small"><?= e($u['roles']) ?></td><td><?= (int)$u['mfa_enabled'] ? '✓' : '—' ?></td><td class="small"><?= e($u['last_login_at'] ? fmt_datetime($u['last_login_at']) : __('never')) ?></td><td><?= badge($u['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
<div class="card"><h2 class="h3"><?= e(__('Create an account')) ?></h2>
<form method="post" action="<?= e(url('/admin/users')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Email')) ?><input type="email" name="email" required></label>
  <label><?= e(__('Link to existing person')) ?><select name="person_id"><?= options(people_options(), null, __('— Create a new person —')) ?></select></label>
  <div class="grid-2"><label><?= e(__('First name')) ?><input name="first_name"></label><label><?= e(__('Last name')) ?><input name="last_name"></label></div>
  <fieldset><legend><?= e(__('Roles')) ?></legend><?php foreach ($roles as $rid => $rn): ?><label class="check"><input type="checkbox" name="role_ids[]" value="<?= (int)$rid ?>"> <?= e($rn) ?></label><?php endforeach; ?></fieldset>
  <button class="btn btn-primary"><?= e(__('Create & send invitation')) ?></button>
</form>
<p class="small muted"><?= e(__('Tip: you can also invite anyone from their profile page. Give people only the roles they need.')) ?></p></div>
</div>
