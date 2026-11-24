<?php $v = fn($k) => e($c[$k] ?? ''); ?>
<label><?= e(__('Title')) ?><input name="title" required value="<?= $v('title') ?>"></label>
<label><?= e(__('Summary')) ?><textarea name="summary"><?= $v('summary') ?></textarea></label>
<div class="grid-2">
  <label><?= e(__('Category')) ?><input name="category" value="<?= $v('category') ?>" list="course-cats"><datalist id="course-cats"><option value="Induction"><option value="Safeguarding"><option value="Bible study"><option value="Discipleship"><option value="Evangelism"><option value="Leadership"><option value="Welcoming newcomers"></datalist></label>
  <label><?= e(__('Prerequisite course')) ?><select name="prerequisite_id"><?= options($courses, $c['prerequisite_id'] ?? '', __('— None —')) ?></select></label>
  <label><?= e(__('Quiz pass mark (%)')) ?><input type="number" min="0" max="100" name="pass_mark" value="<?= e($c['pass_mark'] ?? 80) ?>"></label>
  <label><?= e(__('Must be renewed every (months)')) ?><input type="number" min="1" max="120" name="validity_months" value="<?= $v('validity_months') ?>"><span class="help"><?= e(__('Leave blank if it never expires. Expired training stops people being rostered until renewed.')) ?></span></label>
</div>
<label class="check"><input type="checkbox" name="certificate_enabled" value="1" <?= !isset($c['certificate_enabled']) || (int)$c['certificate_enabled'] ? 'checked' : '' ?>> <?= e(__('Issue a certificate on completion')) ?></label>
<label class="check"><input type="checkbox" name="self_enrol" value="1" <?= !empty($c['self_enrol']) ? 'checked' : '' ?>> <?= e(__('Anyone can enrol themselves')) ?></label>
