<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin/users')) ?>"><?= e(__('Users')) ?></a></div><h1><?= e($title) ?> <?= badge($u['status']) ?></h1><p><?= e($u['email']) ?> · <?= e(__('Last sign in: %s', $u['last_login_at'] ? fmt_datetime($u['last_login_at']) : __('never'))) ?></p></div>
<?php if ($u['person_id'] && can('people.view')): ?><a class="btn" href="<?= e(url('/people/' . $u['person_id'])) ?>"><?= e(__('Person record')) ?></a><?php endif; ?></div>
<div class="split">
<div>
<form method="post" action="<?= e(url('/admin/users/' . $u['id'])) ?>" class="card form"><?= csrf_field() ?>
  <h2><?= e(__('Access')) ?></h2>
  <label><?= e(__('Status')) ?><select name="status"><?= options(['active' => __('Active'), 'disabled' => __('Disabled')], $u['status']) ?></select></label>
  <?php if (Campus::multi()): ?><label><?= e(__('Limit to campus')) ?><select name="campus_id"><?= options(Campus::all(), $u['campus_id'] ?? null, __('All campuses')) ?></select></label><?php endif; ?>
  <fieldset><legend><?= e(__('Roles')) ?></legend><?php foreach ($roles as $r): ?><label class="check"><input type="checkbox" name="role_ids[]" value="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $mine, true) ? 'checked' : '' ?>> <strong><?= e($r['name']) ?></strong> <span class="small muted"><?= e($r['description']) ?></span></label><?php endforeach; ?></fieldset>
  <button class="btn btn-primary"><?= e(__('Save')) ?></button>
</form>
<div class="card"><h2 class="h3"><?= e(__('Effective permissions')) ?></h2><p class="small"><?php foreach ($perms as $p): ?><span class="badge badge-grey"><?= e($p) ?></span> <?php endforeach; ?><?= $perms ? '' : e(__('None')) ?></p></div>
</div>
<div>
<div class="card"><h2 class="h3"><?= e(__('Security')) ?></h2>
  <p class="small"><?= e(__('Two-step verification: %s', (int)$u['mfa_enabled'] ? __('on') : __('off'))) ?></p>
  <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/send-reset')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Email a password link')) ?></button></form>
  <?php if ((int)$u['mfa_enabled']): ?><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/reset-mfa')) ?>" data-confirm="<?= e(__('Only do this after confirming the person\'s identity. Continue?')) ?>" style="margin-top:8px"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Reset two-step verification')) ?></button></form><?php endif; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('Recent activity')) ?></h2><ul class="list-plain small"><?php foreach ($activity as $a): ?><li><code><?= e($a['action']) ?></code> <?= e(fmt_datetime($a['created_at'])) ?></li><?php endforeach; ?></ul><?php if (can('audit.view')): ?><a class="small" href="<?= e(url('/admin/audit', ['user' => $u['id']])) ?>"><?= e(__('Full audit trail')) ?></a><?php endif; ?></div>
</div>
</div>
