<?php $active = array_filter($pledges, fn($p) => $p['status'] === 'active'); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/campaigns')) ?>"><?= e(__('Campaigns & pledges')) ?></a></div><h1><?= e($c['name']) ?> <?= badge($c['status']) ?></h1>
<p class="muted"><?= e($c['fund']) ?> · <?= e(fmt_date($c['starts_on'])) ?> – <?= e(fmt_date($c['ends_on'])) ?></p></div>
<div class="actions"><?php if ($c['is_public']): ?><a class="btn" href="<?= e(url('/campaign/' . $c['id'])) ?>" target="_blank" rel="noopener"><?= e(__('Public page')) ?></a><?php endif; ?>
<a class="btn" href="<?= e(url('/campaigns/' . $c['id'], ['export' => 1])) ?>"><?= e(__('Export CSV')) ?></a>
<form class="inline-form" method="post" action="<?= e(url('/campaigns/' . $c['id'] . '/remind')) ?>" data-confirm="<?= e(__('Email every pledger their progress?')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Email progress to pledgers')) ?></button></form></div></div>
<div class="card"><?php partial('campaigns/_progress', ['goal' => $c['goal_cents'], 'raised' => $raised, 'pledged' => $pledged]); ?></div>
<div class="grid-4" style="margin-bottom:18px">
  <div class="stat"><div class="num"><?= e(fmt_money($raised)) ?></div><div class="lbl"><?= e(__('Given so far')) ?></div></div>
  <div class="stat"><div class="num"><?= e(fmt_money($pledged)) ?></div><div class="lbl"><?= e(__('Pledged by %d households or people', count($active))) ?></div></div>
  <div class="stat"><div class="num"><?= $pledged ? (int)round($fromPledgers / $pledged * 100) : 0 ?>%</div><div class="lbl"><?= e(__('Of pledges fulfilled')) ?></div></div>
  <div class="stat"><div class="num"><?= e(fmt_money(max(0, (int)$c['goal_cents'] - $raised))) ?></div><div class="lbl"><?= e(__('Still to raise')) ?></div></div>
</div>
<div class="split" style="grid-template-columns:3fr 2fr">
<div class="card table-wrap"><h2 class="h3"><?= e(__('Pledges')) ?></h2>
<table><thead><tr><th><?= e(__('Name')) ?></th><th class="right"><?= e(__('Pledged')) ?></th><th class="right"><?= e(__('Given')) ?></th><th><?= e(__('Progress')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
<?php foreach ($pledges as $p): $pct = $p['total_cents'] ? min(100, (int)round($p['given'] / $p['total_cents'] * 100)) : 0; $behind = $p['status'] === 'active' && $p['given'] + 100 < $p['expected']; ?>
<tr class="<?= $p['status'] === 'cancelled' ? 'muted' : '' ?>"><td><?= $p['person_id'] ? '<a href="' . e(url('/people/' . $p['person_id'])) . '">' . e(person_name($p)) . '</a>' : e($p['name']) ?><?= $p['anonymous'] ? ' <span class="small muted">' . e(__('(anonymous)')) . '</span>' : '' ?>
  <div class="small muted"><?= e(CampaignsController::freqLabel($p['frequency'])) ?> · <?= e(fmt_date($p['created_at'])) ?><?= $p['notes'] ? ' · ' . e($p['notes']) : '' ?></div></td>
<td class="right"><?= e(fmt_money((int)$p['total_cents'])) ?></td><td class="right"><?= e(fmt_money((int)$p['given'])) ?></td>
<td><div class="progress"><span style="width:<?= $pct ?>%"></span></div><span class="small"><?= $p['status'] === 'cancelled' ? badge('cancelled') : ($pct >= 100 ? '<span class="badge badge-green">' . e(__('Fulfilled')) . '</span>' : ($behind ? '<span class="badge badge-amber">' . e(__('Behind')) . '</span>' : e($pct . '%'))) ?></span></td>
<td class="actions-cell"><?php if ($p['status'] === 'active'): ?><form class="inline-form" method="post" action="<?= e(url('/campaigns/' . $c['id'] . '/pledges/' . $p['id'])) ?>" data-confirm="<?= e(__('Cancel this pledge?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-sm"><?= e(__('Cancel')) ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$pledges): ?><tr><td colspan="5" class="empty"><?= e(__('No pledges yet.')) ?></td></tr><?php endif; ?>
</tbody></table>
<p class="small muted"><?= e(__('"Behind" means less has been given than an even spread over the campaign would expect by now. Gifts from anyone in the household count.')) ?></p></div>
<div>
<div class="card"><h2 class="h3"><?= e(__('Record a pledge card')) ?></h2>
<form method="post" action="<?= e(url('/campaigns/' . $c['id'] . '/pledges')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Person')) ?><select name="person_id"><?= options($people, null, __('— Not in the database —')) ?></select></label>
  <div class="grid-2"><label><?= e(__('Or name')) ?><input name="name" maxlength="160"></label><label><?= e(__('Email')) ?><input type="email" name="email"></label></div>
  <div class="grid-2"><label><?= e(__('Total pledged')) ?><input name="total" inputmode="decimal" required></label>
  <label><?= e(__('How often')) ?><select name="frequency"><?php foreach (CampaignsController::FREQS as $fq): ?><option value="<?= $fq ?>"><?= e(CampaignsController::freqLabel($fq)) ?></option><?php endforeach; ?></select></label></div>
  <label><?= e(__('Notes')) ?><input name="notes" maxlength="255"></label>
  <label class="check"><input type="checkbox" name="anonymous" value="1"> <?= e(__('Keep anonymous in any public list')) ?></label>
  <button class="btn btn-primary"><?= e(__('Record pledge')) ?></button>
</form></div>
<div class="card"><h2 class="h3"><?= e(__('Campaign settings')) ?></h2>
<form method="post" action="<?= e(url('/campaigns/' . $c['id'])) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required value="<?= e($c['name']) ?>"></label>
  <label><?= e(__('Fund')) ?><select name="fund_id"><?= options($funds, $c['fund_id']) ?></select></label>
  <div class="grid-2"><label><?= e(__('Starts')) ?><input type="date" name="starts_on" value="<?= e($c['starts_on']) ?>"></label><label><?= e(__('Ends')) ?><input type="date" name="ends_on" value="<?= e($c['ends_on']) ?>"></label></div>
  <label><?= e(__('Goal')) ?><input name="goal" inputmode="decimal" value="<?= e(number_format($c['goal_cents'] / 100, 2, '.', '')) ?>"></label>
  <label><?= e(__('Description')) ?><textarea name="description" rows="3"><?= e((string)$c['description']) ?></textarea></label>
  <label><?= e(__('Status')) ?><select name="status"><?= options(['active' => __('Active'), 'closed' => __('Closed')], $c['status']) ?></select></label>
  <label class="check"><input type="checkbox" name="is_public" value="1" <?= $c['is_public'] ? 'checked' : '' ?>> <?= e(__('Show a public page where people can pledge')) ?></label>
  <button class="btn btn-sm btn-primary"><?= e(__('Save')) ?></button>
</form></div>
</div></div>
