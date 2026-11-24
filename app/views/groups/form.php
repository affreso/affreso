<?php if (empty($embedded)): ?><div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/groups')) ?>"><?= e(__('Ministries & groups')) ?></a></div><h1><?= e($title) ?></h1></div></div><div class="card"><?php endif; ?>
<?php $v = fn($k) => e($g[$k] ?? ''); $isNew = empty($g['id']); ?>
<form method="post" action="<?= e(url($isNew ? '/groups' : '/groups/' . $g['id'])) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required value="<?= $v('name') ?>"></label>
  <div class="grid-2">
    <label><?= e(__('Type')) ?><select name="group_type"><?= options(GroupsController::types(), $g['group_type'] ?? 'team') ?></select></label>
    <label><?= e(__('Meets')) ?><input name="meeting_schedule" value="<?= $v('meeting_schedule') ?>" placeholder="<?= e(__('e.g. Tuesdays 7:30pm')) ?>"></label>
    <label><?= e(__('Location')) ?><input name="location" value="<?= $v('location') ?>"></label>
    <?= Campus::field(isset($g['campus_id']) ? (int)$g['campus_id'] : null) ?>
  </div>
  <label><?= e(__('Description')) ?><textarea name="description"><?= $v('description') ?></textarea></label>
  <label class="check"><input type="checkbox" name="is_open" value="1" <?= !empty($g['is_open']) ? 'checked' : '' ?>> <?= e(__('Open — members can join themselves from My groups')) ?></label>
  <label class="check"><input type="checkbox" name="child_related" value="1" <?= !empty($g['child_related']) ? 'checked' : '' ?>> <?= e(__('Child-related ministry (new positions require a Working With Children Check)')) ?></label>
  <button class="btn btn-primary"><?= e($isNew ? __('Create group') : __('Save')) ?></button>
  <?php if (!$isNew): ?><button class="btn btn-danger" name="archive" value="1" data-confirm="<?= e(__('Archive this group?')) ?>"><?= e(__('Archive group')) ?></button><?php endif; ?>
</form>
<?php if (empty($embedded)): ?></div><?php endif; ?>
