<h1><?= e(__('Two-step verification')) ?></h1>
<p class="muted"><?= e(__('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')) ?></p>
<form method="post" action="<?= e(url('/login/mfa')) ?>" class="form">
  <?= csrf_field() ?>
  <label><?= e(__('Code')) ?><input name="code" required inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="20"></label>
  <p><button class="btn btn-primary" type="submit"><?= e(__('Verify')) ?></button></p>
</form>
<p class="small"><a href="<?= e(url('/login')) ?>"><?= e(__('Start again')) ?></a></p>
