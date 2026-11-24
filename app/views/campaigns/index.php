<div class="page-head"><div><h1><?= e($title) ?></h1><p><?= e(__('Building funds, mission appeals and other campaigns. Progress comes from gifts recorded to the campaign\'s fund.')) ?></p></div></div>
<div class="split">
<div><?php foreach ($rows as $c): ?>
<a class="card card-link" href="<?= e(url('/campaigns/' . $c['id'])) ?>"><h2 class="h3" style="margin-top:0"><?= e($c['name']) ?> <?= badge($c['status']) ?></h2>
<p class="small muted"><?= e($c['fund']) ?> · <?= e(fmt_date($c['starts_on'])) ?> – <?= e(fmt_date($c['ends_on'])) ?></p>
<?php partial('campaigns/_progress', ['goal' => $c['goal_cents'], 'raised' => $c['raised'], 'pledged' => $c['pledged']]); ?></a>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= e(__('No campaigns yet.')) ?></p></div><?php endif; ?></div>
<div class="card"><h2 class="h3"><?= e(__('New campaign')) ?></h2>
<?php if (!$funds): ?><p class="small muted"><?= e(__('Add a giving fund first in Settings → Custom fields & funds.')) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('/campaigns')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required maxlength="160" placeholder="<?= e(__('e.g. New Hall Building Fund')) ?>"></label>
  <label><?= e(__('Fund')) ?><select name="fund_id" required><?= options($funds, null, __('— Choose —')) ?></select><span class="help"><?= e(__('Gifts to this fund between the dates count towards the campaign.')) ?></span></label>
  <div class="grid-2"><label><?= e(__('Starts')) ?><input type="date" name="starts_on" required value="<?= e(date('Y-m-d')) ?>"></label><label><?= e(__('Ends')) ?><input type="date" name="ends_on" required value="<?= e(date('Y-m-d', strtotime('+3 years'))) ?>"></label></div>
  <label><?= e(__('Goal')) ?><input name="goal" inputmode="decimal" placeholder="500000"></label>
  <label><?= e(__('Description')) ?><textarea name="description" rows="3"></textarea></label>
  <label class="check"><input type="checkbox" name="is_public" value="1" checked> <?= e(__('Show a public page where people can pledge')) ?></label>
  <button class="btn btn-primary"><?= e(__('Create campaign')) ?></button>
</form></div>
</div>
