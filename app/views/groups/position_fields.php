<div class="grid-2">
  <label><?= e(__('Position name')) ?><input name="name" required value="<?= e($po['name'] ?? '') ?>"></label>
  <label><?= e(__('Max times per 30 days (burnout guard)')) ?><input type="number" min="1" max="31" name="max_per_month" value="<?= (int)($po['max_per_month'] ?? 3) ?>"></label>
  <label><?= e(__('Minimum age')) ?><input type="number" min="0" max="99" name="min_age" value="<?= e($po['min_age'] ?? '') ?>"></label>
  <label><?= e(__('Description')) ?><input name="description" value="<?= e($po['description'] ?? '') ?>"></label>
</div>
<label class="check"><input type="checkbox" name="requires_wwcc" value="1" <?= !empty($po['requires_wwcc']) ? 'checked' : '' ?>> <?= e(__('Requires a verified, current Working With Children Check')) ?></label>
<fieldset><legend><?= e(__('Required training')) ?></legend>
  <?php foreach ($courses as $cid => $ct): ?><label class="check"><input type="checkbox" name="course_ids[]" value="<?= (int)$cid ?>" <?= in_array((int)$cid, $po['courses'] ?? [], true) ? 'checked' : '' ?>> <?= e($ct) ?></label><?php endforeach; ?>
  <?php if (!$courses): ?><p class="small muted"><?= e(__('Publish a course to require it here.')) ?></p><?php endif; ?>
</fieldset>
<?php $policies = array_column(DB::all('SELECT id, title FROM {p}documents WHERE requires_ack = 1 AND archived = 0 ORDER BY title'), 'title', 'id'); $mine = !empty($po['id']) ? array_map('intval', DB::col('SELECT document_id FROM {p}position_documents WHERE position_id = ?', [$po['id']])) : []; ?>
<?php if ($policies): ?><fieldset><legend><?= e(__('Policies that must be signed')) ?></legend>
  <?php foreach ($policies as $did => $dt): ?><label class="check"><input type="checkbox" name="document_ids[]" value="<?= (int)$did ?>" <?= in_array((int)$did, $mine, true) ? 'checked' : '' ?>> <?= e($dt) ?></label><?php endforeach; ?>
</fieldset><?php endif; ?>
