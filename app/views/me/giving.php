<div class="page-head"><div><h1><?= e(__('My giving')) ?></h1><p><?= e(__('Thank you for your generosity.')) ?></p></div><?php if (Stripe::enabled()): ?><a class="btn btn-gold" href="<?= e(url('/give')) ?>"><?= e(__('Give online')) ?></a><?php endif; ?></div>
<?php if ($recurring): ?><div class="card"><h2><?= e(__('Regular gifts')) ?></h2><ul class="list-plain"><?php foreach ($recurring as $g): ?><li><?= e(fmt_money((int)$g['amount_cents'])) ?> · <?= e(['week' => __('every week'), 'fortnight' => __('every fortnight'), 'month' => __('every month')][$g['frequency']]) ?> · <?= e($g['fund']) ?> <?= badge($g['status'] === 'past_due' ? 'failed' : $g['status']) ?>
<form class="inline-form" method="post" action="<?= e(url('/giving/recurring/' . $g['id'] . '/cancel')) ?>" data-confirm="<?= e(__('Cancel this regular gift?')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Cancel')) ?></button></form></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($pledges || $campaigns): ?><div class="card"><h2><?= e(__('Pledges')) ?></h2>
<?php foreach ($pledges as $pl): $pct = $pl['total_cents'] ? min(100, (int)round($pl['given'] / $pl['total_cents'] * 100)) : 0; ?>
  <div class="sync-row"><div><strong><?= e($pl['campaign']) ?></strong> · <?= e(__('%s pledged (%s)', fmt_money((int)$pl['total_cents']), CampaignsController::freqLabel($pl['frequency']))) ?></div>
  <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e(__('Pledge progress')) ?>"><span style="width:<?= $pct ?>%"></span></div>
  <div class="small muted"><?= e(__('%s given so far (%d%%) · until %s. Gifts from your household count.', fmt_money((int)$pl['given']), $pct, fmt_date($pl['ends_on']))) ?></div></div>
<?php endforeach; ?>
<?php foreach ($campaigns as $c): if (in_array($c['id'], array_column($pledges, 'cid'))) continue; ?><p><a href="<?= e(url('/campaign/' . $c['id'])) ?>"><?= e(__('Make a pledge to %s', $c['name'])) ?></a></p><?php endforeach; ?>
</div><?php endif; ?>
<div class="grid-3"><div class="stat"><div class="num"><?= e(fmt_money($fyTotal)) ?></div><div class="lbl"><?= e(__('Financial year %d–%d', $fy, $fy + 1)) ?></div></div>
<div class="stat"><div class="num"><?= e($givingNumber) ?></div><div class="lbl"><?= e(__('Your giving number — use it as the description for bank transfers')) ?></div></div>
<a class="stat" href="<?= e(url('/giving/statement/' . person_id(), ['fy' => $fy - 1])) ?>" target="_blank"><div class="num">📄</div><div class="lbl"><?= e(__('Statement for %d–%d', $fy - 1, $fy)) ?></div></a></div>
<div class="card table-wrap" style="margin-top:18px"><table><thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Fund')) ?></th><th><?= e(__('Amount')) ?></th><th><?= e(__('Method')) ?></th><th><?= e(__('Receipt')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $d): ?><tr><td><?= e(fmt_date($d['received_on'] ?: $d['created_at'])) ?></td><td><?= e($d['fund']) ?></td><td><?= e(fmt_money((int)$d['amount_cents'])) ?></td><td><?= e(strtoupper($d['method'])) ?></td><td><?= e($d['receipt_no']) ?> <?= badge($d['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= e(__('No giving recorded.')) ?></td></tr><?php endif; ?>
</tbody></table><p class="small muted"><?= e(setting('giving_dgr_statement')) ?></p></div>
