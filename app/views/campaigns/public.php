<div class="form-page">
<h1><?= e($c['name']) ?></h1>
<?php if ($c['description']): ?><div class="lead"><?= nl2br(e($c['description'])) ?></div><?php endif; ?>
<div class="card"><?php partial('campaigns/_progress', ['goal' => $c['goal_cents'], 'raised' => $raised, 'pledged' => $pledged]); ?>
<p class="small muted"><?= e(__('%d people and households have pledged. The campaign runs until %s.', $pledgers, fmt_date($c['ends_on']))) ?></p>
<?php if (Stripe::enabled()): ?><a class="btn btn-gold" href="<?= e(url('/give', ['fund' => $c['fund_id']])) ?>"><?= e(__('Give now')) ?></a><?php endif; ?></div>
<?php foreach ($mine as $m): ?><div class="card callout"><?= e(__('Your pledge: %s (%s). Given so far: %s.', fmt_money((int)$m['total_cents']), CampaignsController::freqLabel($m['frequency']), fmt_money((int)$m['given']))) ?></div><?php endforeach; ?>
<?php if ($open): ?>
<form method="post" action="<?= e(url('/campaign/' . $c['id'] . '/pledge')) ?>" class="card form" id="pledge"><?= csrf_field() ?>
  <h2 class="h3"><?= e(__('Make a pledge')) ?></h2>
  <p class="small muted"><?= e(__('A pledge is a statement of what you intend to give with God\'s help. It isn\'t a contract, and you can change it at any time.')) ?></p>
  <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <?php if (!$me): ?><div class="grid-2"><label><?= e(__('Your name')) ?><input name="name" required maxlength="160" autocomplete="name"></label><label><?= e(__('Email')) ?><input type="email" name="email" required autocomplete="email"></label></div><?php endif; ?>
  <div class="grid-2"><label><?= e(__('Total I intend to give')) ?><input name="total" inputmode="decimal" required placeholder="5000"></label>
  <label><?= e(__('How I plan to give it')) ?><select name="frequency"><?php foreach (CampaignsController::FREQS as $fq): ?><option value="<?= $fq ?>" <?= $fq === 'monthly' ? 'selected' : '' ?>><?= e(CampaignsController::freqLabel($fq)) ?></option><?php endforeach; ?></select></label></div>
  <label class="check"><input type="checkbox" name="anonymous" value="1"> <?= e(__('Please keep my pledge anonymous')) ?></label>
  <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(__('I agree to the church keeping this pledge and emailing me about my progress.')) ?> <a href="<?= e(url('/privacy')) ?>"><?= e(__('Privacy policy')) ?></a></span></label>
  <button class="btn btn-primary"><?= e(__('Pledge')) ?></button>
</form>
<?php else: ?><div class="card"><p><?= e(__('This campaign is no longer taking pledges. Thank you to everyone who gave.')) ?></p></div><?php endif; ?>
</div>
