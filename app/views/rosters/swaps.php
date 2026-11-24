<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/rosters')) ?>"><?= e(__('Rosters')) ?></a></div><h1><?= e(__('Swap requests')) ?></h1></div></div>
<div class="card">
<?php if (!$rows): ?><p class="empty"><?= e(__('No open swap requests.')) ?></p><?php endif; ?>
<table><tbody>
<?php foreach ($rows as $s): ?>
<tr><td><strong><?= e(fmt_date($s['service_date'], 'D j M')) ?></strong> · <a href="<?= e(url('/rosters/' . $s['roster_id'])) ?>"><?= e($s['title']) ?></a> · <?= e($s['position']) ?></td>
<td><?= e($s['f1'] . ' ' . $s['l1']) ?> → <?= $s['replacement_id'] ? e($s['f2'] . ' ' . $s['l2']) : '<span class="muted">' . e(__('looking for cover')) . '</span>' ?><?= $s['reason'] ? '<div class="small muted">' . e($s['reason']) . '</div>' : '' ?></td>
<td class="actions-cell"><form class="inline-form" method="post" action="<?= e(url('/rosters/swaps/' . $s['id'])) ?>"><?= csrf_field() ?><?php if ($s['replacement_id']): ?><button class="btn btn-sm btn-primary" name="decision" value="approve"><?= e(__('Approve')) ?></button><?php endif; ?> <button class="btn btn-sm" name="decision" value="cancel"><?= e(__('Close')) ?></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div>
