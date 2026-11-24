<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/admin')) ?>"><?= e(__('Administration')) ?></a></div><h1><?= e(__('Roles & permissions')) ?></h1><p><?= e(__('People only see what their roles allow. Ministry leaders automatically get rostering and onboarding rights for the teams they lead.')) ?></p></div></div>
<div class="split">
<div>
<?php foreach ($roles as $r): $locked = $r['slug'] === 'super_admin'; ?>
<details class="card" <?= $edit === (int)$r['id'] ? 'open' : '' ?>>
  <summary><strong><?= e($r['name']) ?></strong> <span class="small muted">· <?= e(__('%d users', (int)$r['users'])) ?> · <?= e(__('%d permissions', count($grants[$r['id']] ?? []))) ?></span><div class="small muted"><?= e($r['description']) ?></div></summary>
  <form method="post" action="<?= e(url('/admin/roles/' . $r['id'])) ?>" class="form" style="margin-top:12px"><?= csrf_field() ?>
    <?php if (!$locked): ?><div class="grid-2"><label><?= e(__('Name')) ?><input name="name" value="<?= e($r['name']) ?>"></label><label><?= e(__('Description')) ?><input name="description" value="<?= e($r['description']) ?>"></label></div><?php endif; ?>
    <?php foreach (permission_catalogue() as $group => $perms): ?><fieldset><legend><?= e(__($group)) ?></legend><div class="grid-2">
      <?php foreach ($perms as $key => $label): ?><label class="check"><input type="checkbox" name="perms[]" value="<?= e($key) ?>" <?= isset($grants[$r['id']][$key]) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>> <span><?= e(__($label)) ?><?= in_array($key, sensitive_permissions(), true) ? ' <span class="badge badge-gold">' . e(__('sensitive')) . '</span>' : '' ?></span></label><?php endforeach; ?>
    </div></fieldset><?php endforeach; ?>
    <?php if (!$locked): ?><button class="btn btn-primary"><?= e(__('Save permissions')) ?></button><?php if (!(int)$r['is_system']): ?> <button class="btn btn-danger" name="delete" value="1" data-confirm="<?= e(__('Delete this role?')) ?>"><?= e(__('Delete role')) ?></button><?php endif; ?><?php else: ?><p class="small muted"><?= e(__('The Super Administrator role always has every permission.')) ?></p><?php endif; ?>
  </form>
</details>
<?php endforeach; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('New role')) ?></h2><form method="post" action="<?= e(url('/admin/roles')) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Name')) ?><input name="name" required placeholder="<?= e(__('e.g. Youth Pastor')) ?>"></label><label><?= e(__('Description')) ?><input name="description"></label><button class="btn btn-primary"><?= e(__('Create')) ?></button></form>
<p class="small muted"><?= e(__('Holders of permissions marked "sensitive" must use two-step verification when that setting is on.')) ?></p></div>
</div>
