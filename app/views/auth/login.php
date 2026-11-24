<?php partial('partials/demo_roles'); ?>
<h1><?= e(__('Sign in')) ?></h1>
<form method="post" action="<?= e(url('/login')) ?>" class="form">
  <?= csrf_field() ?>
  <label><?= e(__('Email')) ?><input type="email" name="email" required autocomplete="username" autofocus></label>
  <label><?= e(__('Password')) ?><input type="password" name="password" required autocomplete="current-password"></label>
  <p><button class="btn btn-primary" type="submit"><?= e(__('Sign in')) ?></button></p>
</form>
<p class="small"><a href="<?= e(url('/password/forgot')) ?>"><?= e(__('Forgotten your password?')) ?></a></p>
<p class="small muted"><?= e(__('New here?')) ?> <a href="<?= e(url('/connect')) ?>"><?= e(__('Fill in a connect card')) ?></a> · <a href="<?= e(url('/public/events')) ?>"><?= e(__('See upcoming events')) ?></a></p>
