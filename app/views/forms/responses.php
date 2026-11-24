<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/forms')) ?>"><?= e(__('Forms')) ?></a> › <a href="<?= e(url('/forms/' . $f['id'] . '/edit')) ?>"><?= e($f['title']) ?></a></div><h1><?= e(__('Responses')) ?></h1></div>
<div class="actions"><a class="btn" href="<?= e(url('/forms/' . $f['id'] . '/responses', ['export' => 1])) ?>"><?= e(__('Export CSV')) ?></a></div></div>
<div class="grid-4" style="margin-bottom:18px">
  <div class="stat"><div class="num"><?= count(array_filter($subs, fn($s) => $s['status'] !== 'cancelled')) ?></div><div class="lbl"><?= e(__('Responses')) ?></div></div>
  <?php if ($f['capacity']): ?><div class="stat"><div class="num"><?= max(0, (int)$f['capacity'] - Forms::taken((int)$f['id'])) ?></div><div class="lbl"><?= e(__('Places left')) ?></div></div><?php endif; ?>
  <?php if (Forms::isPaid($f)): ?><div class="stat"><div class="num"><?= e(fmt_money($paid)) ?></div><div class="lbl"><?= e(__('Paid so far')) ?></div></div>
  <div class="stat"><div class="num"><?= count(array_filter($subs, fn($s) => in_array($s['status'], ['awaiting_payment', 'part_paid', 'overdue'], true))) ?></div><div class="lbl"><?= e(__('Still to pay')) ?></div></div><?php endif; ?>
</div>
<div class="split">
<div class="card table-wrap"><table><thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Submitted')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
<?php foreach ($subs as $s): ?><tr><td><a href="<?= e(url('/forms/' . $f['id'] . '/responses/' . $s['id'])) ?>"><?= e($s['name']) ?></a><?= $s['email'] ? '<div class="small muted">' . e($s['email']) . '</div>' : '' ?><?= $s['tickets'] ? '<div class="small">' . e(Forms::ticketSummary($s)) . '</div>' : '' ?></td><td class="small"><?= e(fmt_datetime($s['created_at'])) ?></td><td><?= badge($s['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$subs): ?><tr><td colspan="3" class="empty"><?= e(__('No responses yet.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div>
<?php if ($tallies): ?><div class="card"><h2 class="h3"><?= e(__('Summary')) ?></h2>
<?php foreach ($tallies as $t): ?><p><strong><?= e($t['label']) ?></strong></p><ul class="list-plain small"><?php foreach ($t['counts'] as $label => $n): ?><li><?= e($label) ?>: <strong><?= (int)$n ?></strong></li><?php endforeach; ?><?php if (!$t['counts']): ?><li class="muted"><?= e(__('No answers yet.')) ?></li><?php endif; ?></ul><?php endforeach; ?></div><?php endif; ?>
<?php if ($subs): ?><div class="card"><h2 class="h3"><?= e(__('Privacy')) ?></h2><p class="small muted"><?= e(__('When the event is over, delete responses you no longer need — especially health details.')) ?></p>
<form method="post" action="<?= e(url('/forms/' . $f['id'] . '/responses/delete')) ?>" data-confirm="<?= e(__('Delete all responses to this form? This cannot be undone.')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete all responses')) ?></button></form></div><?php endif; ?>
</div></div>
