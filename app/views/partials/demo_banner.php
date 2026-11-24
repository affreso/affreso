<?php if (Demo::on()): ?>
<aside class="demo-banner" aria-label="<?= e(__('Demo site notice')) ?>">
  <strong><?= e(__('Demo site')) ?></strong> · <?= e(__('All people and data are fictional and reset every night. Email, SMS and payments are switched off.')) ?>
  <?php if (user()): ?><form method="post" action="<?= e(url('/logout')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn-link"><?= e(__('Try another role')) ?></button></form><?php else: ?><a href="<?= e(url('/login')) ?>"><?= e(__('Sign in as any role')) ?></a><?php endif; ?>
</aside>
<?php endif; ?>
