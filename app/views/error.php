<div class="empty-state">
  <div class="big-code"><?= (int)$code ?></div>
  <h1><?= e(__($title)) ?></h1>
  <?php if ($message): ?><p class="muted"><?= e(__($message)) ?></p><?php endif; ?>
  <p><a class="btn" href="<?= e(url('/')) ?>"><?= e(__('Back to dashboard')) ?></a></p>
</div>
