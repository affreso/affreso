<?php if (Demo::on()): ?>
<div class="demo-roles">
  <h2><?= e(__('Explore the demo')) ?></h2>
  <p class="muted small"><?= e(__('Choose a role to see Affreso the way that person would. No password needed.')) ?></p>
  <?php foreach (Demo::accounts() as $key => [$first, $last, $slug, $label, $desc]): ?>
  <form method="post" action="<?= e(url('/demo/login')) ?>"><?= csrf_field() ?><input type="hidden" name="role" value="<?= e($key) ?>">
    <button class="demo-role" type="submit"><strong><?= e($label) ?></strong> <span class="muted">· <?= e($first . ' ' . $last) ?></span><span class="small"><?= e($desc) ?></span></button>
  </form>
  <?php endforeach; ?>
  <p class="small muted" style="margin-top:14px"><?= e(__('Site owner? Sign in with your own account below.')) ?></p>
</div>
<?php endif; ?>
