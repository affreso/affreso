<div class="page-head"><div><h1><?= e(__('Ministries & groups')) ?></h1><p><?= e(__('Ministry teams, small groups and ministry areas.')) ?></p></div>
<?php if (can('groups.manage')): ?><div class="actions"><a class="btn btn-primary" href="<?= e(url('/groups/new')) ?>">+ <?= e(__('New group')) ?></a></div><?php endif; ?></div>
<div class="tabs"><a href="<?= e(url('/groups')) ?>" class="<?= $type === '' ? 'active' : '' ?>"><?= e(__('All')) ?></a><?php foreach (GroupsController::types() as $k => $l): ?><a href="<?= e(url('/groups', ['type' => $k])) ?>" class="<?= $type === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="grid-3">
<?php foreach ($rows as $g): ?>
  <a class="card stat" href="<?= e(url('/groups/' . $g['id'])) ?>">
    <span class="badge badge-blue"><?= e(GroupsController::types()[$g['group_type']]) ?></span><?= (int)$g['child_related'] ? ' <span class="badge badge-gold">' . e(__('Child-safe')) . '</span>' : '' ?>
    <h2 class="h3"><?= e($g['name']) ?></h2>
    <div class="small muted"><?= e(__('%d people', (int)$g['members'])) ?><?= $g['meeting_schedule'] ? ' · ' . e($g['meeting_schedule']) : '' ?></div>
    <?php if ($g['leaders']): ?><div class="small"><?= e(__('Led by %s', $g['leaders'])) ?></div><?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= e(__('No groups yet.')) ?></p></div><?php endif; ?>
