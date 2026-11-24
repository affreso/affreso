<h1><?= e(__('Reset your password')) ?></h1>
<p class="muted"><?= e(__("Enter your email and we'll send you a link to choose a new password.")) ?></p>
<form method="post" action="<?= e(url('/password/forgot')) ?>" class="form">
  <?= csrf_field() ?>
  <label><?= e(__('Email')) ?><input type="email" name="email" required autofocus></label>
  <p><button class="btn btn-primary"><?= e(__('Send reset link')) ?></button></p>
</form>
<p class="small"><a href="<?= e(url('/login')) ?>"><?= e(__('Back to sign in')) ?></a></p>
