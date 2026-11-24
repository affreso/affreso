<?php /** Inputs for one question. Expects $fld (or null for a new one) and $uid. */ $fld = $fld ?? ['type' => 'text', 'label' => '', 'required' => false, 'options' => [], 'help' => '']; ?>
<div data-reveal-group>
  <div class="grid-2">
    <label><?= e(__('Type')) ?><select name="type" data-reveal><?= options(Forms::types(), $fld['type']) ?></select></label>
    <label class="check" style="align-self:end" data-reveal-for="text textarea email phone number date select radio checkboxes yesno"><input type="checkbox" name="required" value="1" <?= !empty($fld['required']) ? 'checked' : '' ?>> <?= e(__('Required')) ?></label>
  </div>
  <label><?= e(__('Question, heading or text')) ?><textarea name="label" rows="2" required maxlength="2000"><?= e($fld['label']) ?></textarea></label>
  <label data-reveal-for="select radio checkboxes"><?= e(__('Options (one per line)')) ?><textarea name="options" rows="4"><?= e(implode("\n", (array)$fld['options'])) ?></textarea></label>
  <label data-reveal-for="text textarea phone number date select radio yesno consent"><?= e(__('Save the answer to a profile field (optional)')) ?><select name="profile"><?= options(array_map(fn($t) => $t[0], Forms::profileTargets()), $fld['profile'] ?? '', __('— Don\'t save —')) ?></select><span class="help"><?= e(__('Only for questions about the person themselves. Staff choose whose profile to update when they review a response.')) ?></span></label>
  <label data-reveal-for="text textarea email phone number date select radio checkboxes yesno consent"><?= e(__('Help text (optional)')) ?><input name="help" maxlength="300" value="<?= e($fld['help'] ?? '') ?>"></label>
</div>
