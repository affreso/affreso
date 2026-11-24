<form method="post" action="<?= e(url('/people/' . $child['id'] . '/child-profile')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Allergies')) ?><textarea name="allergies" rows="2"><?= e($info['allergies']) ?></textarea></label>
  <label><?= e(__('Medical conditions & medication')) ?><textarea name="medical" rows="2"><?= e($info['medical']) ?></textarea></label>
  <label><?= e(__('Additional needs / how we can help')) ?><textarea name="needs" rows="2"><?= e($info['needs']) ?></textarea></label>
  <label><?= e(__('Emergency contact (if parents unavailable)')) ?><input name="emergency" value="<?= e($info['emergency']) ?>"></label>
  <label class="check"><input type="checkbox" name="photo_permission" value="1" <?= $info['photo_permission'] ? 'checked' : '' ?>> <?= e(__('Photos of this child may be used')) ?></label>
  <button class="btn btn-sm btn-primary"><?= e(__('Save care information')) ?></button>
</form>
