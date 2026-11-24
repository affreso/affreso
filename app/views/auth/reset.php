<h1><?= e(__('Choose a new password')) ?></h1>
<form method="post" action="<?= e(url('/password/reset')) ?>" class="form">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label><?= e(__('New password (at least 10 characters)')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label><?= e(__('Confirm password')) ?><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
  <p><button class="btn btn-primary"><?= e(__('Save password')) ?></button></p>
</form>
