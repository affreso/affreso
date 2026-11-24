<div class="page-head"><div><h1><?= e(__('Volunteer with us')) ?></h1><p><?= e(__('Choose where you would like to serve. A ministry leader will review your application, and any required training and checks will be arranged with you.')) ?></p></div></div>
<?php if ($existing): ?><div class="card"><h2 class="h3"><?= e(__('Your applications')) ?></h2><ul class="list-plain"><?php foreach ($existing as $a): ?><li><?= e(fmt_date($a['created_at'])) ?> <?= badge($a['status']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/me/volunteer')) ?>" class="card form"><?= csrf_field() ?>
  <h2><?= e(__('Ministries')) ?></h2>
  <?php $last = null; foreach ($positions as $po): if ($last !== $po['group_name']): ?><?= $last !== null ? '</fieldset>' : '' ?><fieldset><legend><?= e($po['group_name']) ?></legend><?php $last = $po['group_name']; endif; ?>
    <label class="check"><input type="checkbox" name="position_ids[]" value="<?= (int)$po['id'] ?>"> <?= e($po['name']) ?><?= (int)$po['requires_wwcc'] ? ' <span class="badge badge-gold">' . e(__('Needs a Working With Children Check')) . '</span>' : '' ?></label>
  <?php endforeach; ?><?= $last !== null ? '</fieldset>' : '' ?>
  <label><?= e(__('Skills and gifts')) ?><textarea name="skills"></textarea></label>
  <label><?= e(__('When are you usually available?')) ?><textarea name="availability" placeholder="<?= e(__('e.g. 9am service, twice a month')) ?>"></textarea></label>
  <label><?= e(__('Relevant experience')) ?><textarea name="experience"></textarea></label>
  <button class="btn btn-primary"><?= e(__('Submit application')) ?></button>
</form>
