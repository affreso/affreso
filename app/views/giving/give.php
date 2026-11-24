<div class="card" style="max-width:560px;margin:0 auto">
<h1><?= e(__('Give to %s', setting('church_name'))) ?></h1>
<?php if (!$enabled): ?><p><?= e(__('Online giving is not available yet. Please speak to the church office about other ways to give.')) ?></p><?php else: ?>
<p class="muted"><?= e(__('Your gift is processed securely by Stripe. We never see or store your card details.')) ?></p>
<form method="post" action="<?= e(url('/give')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Amount (%s)', setting('currency', 'AUD'))) ?><input name="amount" required inputmode="decimal" placeholder="50.00"></label>
  <label><?= e(__('How often?')) ?><select name="frequency"><?= options(['once' => __('One-off gift'), 'week' => __('Every week'), 'fortnight' => __('Every fortnight'), 'month' => __('Every month')]) ?></select></label>
  <label><?= e(__('Fund')) ?><select name="fund_id"><?= options($funds, input_int('fund')) ?></select></label>
  <label><?= e(__('Your name')) ?><input name="name" value="<?= e($me ? person_name($me) : '') ?>"></label>
  <label><?= e(__('Email for your receipt')) ?><input type="email" name="email" value="<?= e($me['email'] ?? '') ?>"></label>
  <button class="btn btn-gold"><?= e(__('Continue to secure payment')) ?></button>
</form>
<p class="small muted"><?= e(__('Regular gifts can be cancelled at any time from My Affreso → My giving, or by contacting the church office.')) ?> <?= e(setting('giving_dgr_statement')) ?></p>
<?php endif; ?>
</div>
