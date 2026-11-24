<label><?= e(__('Title')) ?><input name="title" required value="<?= e($d['title'] ?? '') ?>"></label>
<div class="grid-2"><label><?= e(__('Category')) ?><input name="category" value="<?= e($d['category'] ?? '') ?>" list="doc-cats"><datalist id="doc-cats"><option value="Policy"><option value="Handbook"><option value="Bible study"><option value="Training"><option value="Procedure"><option value="Teaching"><option value="Forms"></datalist></label>
<label><?= e(__('Who can see it?')) ?><select name="visibility"><?= options(LibraryController::visibilities(), $d['visibility'] ?? 'members') ?></select></label></div>
<label><?= e(__('Groups (if "Specific groups")')) ?><select name="group_ids[]" multiple size="4"><?= options($groups, array_filter(explode(',', (string)($d['group_ids'] ?? '')))) ?></select></label>
<label><?= e(__('Description')) ?><textarea name="description" rows="2"><?= e($d['description'] ?? '') ?></textarea></label>
<label class="check"><input type="checkbox" name="requires_ack" value="1" <?= !empty($d['requires_ack']) ? 'checked' : '' ?>> <?= e(__('People must read and sign this (policy acknowledgement)')) ?></label>
<label><?= e(__('Re-sign every (months, optional)')) ?><input type="number" name="ack_renew_months" min="1" max="120" value="<?= e($d['ack_renew_months'] ?? '') ?>"></label>
<p class="help"><?= e(__('Require signing for serving positions in Ministries & groups → position settings.')) ?></p>
