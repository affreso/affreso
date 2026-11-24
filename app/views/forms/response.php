<?php $base = '/forms/' . $f['id'] . '/responses/' . $s['id']; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/forms/' . $f['id'] . '/responses')) ?>"><?= e(__('Responses: %s', $f['title'])) ?></a></div><h1><?= e($s['name']) ?> <?= badge($s['status']) ?></h1>
<p class="muted"><?= e(fmt_datetime($s['created_at'])) ?><?= $s['email'] ? ' · ' . e($s['email']) : '' ?><?= $person ? ' · <a href="' . e(url('/people/' . $person['id'])) . '">' . e(__('Open profile')) . '</a>' : '' ?></p></div>
<div class="actions"><button class="btn" data-print><?= e(__('Print')) ?></button></div></div>
<div class="split">
<div>
<div class="card"><dl class="answers">
<?php foreach ($fields as $fld): if ($fld['type'] === 'paragraph') continue; if ($fld['type'] === 'heading'): ?></dl><h2 class="h3" dir="auto"><?= e($fld['label']) ?></h2><dl class="answers"><?php continue; endif; ?>
<dt dir="auto"><?= e($fld['label']) ?></dt><dd dir="auto"><?= nl2br(e(Forms::show($answers[$fld['key']] ?? ''))) ?: '<span class="muted">—</span>' ?></dd>
<?php endforeach; ?>
<?php $known = array_column($fields, 'key'); foreach ($answers as $k => $val): if (in_array($k, $known, true)) continue; ?><dt class="muted"><?= e(__('(Removed question)')) ?></dt><dd dir="auto"><?= nl2br(e(Forms::show($val))) ?></dd><?php endforeach; ?>
</dl></div>

<?php if ($mapped): ?>
<div class="card no-print" id="profile"><h2 class="h3"><?= e(__('Update a profile')) ?></h2>
<p class="small muted"><?= e(__('Some questions on this form are linked to profile fields. Choose whose profile they describe — the person who filled it in, or someone else such as the child going to camp.')) ?></p>
<form method="get" action="<?= e(url($base)) ?>" class="form inline-form"><label><?= e(__('Person')) ?><select name="person" data-autosubmit><?= options($people, $target ?: '', __('— Choose —')) ?></select></label><noscript><button class="btn btn-sm"><?= e(__('Show changes')) ?></button></noscript></form>
<?php if ($target && $changes): ?>
<form method="post" action="<?= e(url($base . '/profile')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="person_id" value="<?= (int)$target ?>">
<div class="table-wrap"><table class="table"><thead><tr><th scope="col"><span class="sr-only"><?= e(__('Select')) ?></span></th><th scope="col"><?= e(__('Profile field')) ?></th><th scope="col"><?= e(__('Now')) ?></th><th scope="col"><?= e(__('From this form')) ?></th></tr></thead><tbody>
<?php $show = fn(string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? fmt_date($v) : $v; foreach ($changes as $c): $cid = 'pc_' . e($c['key']); ?><tr>
<td><input type="checkbox" id="<?= $cid ?>" name="keys[]" value="<?= e($c['key']) ?>" checked></td>
<td><label for="<?= $cid ?>"><?= e($c['field']) ?></label></td>
<td dir="auto"><?= $c['old'] !== '' ? e($show($c['old'])) : '<span class="muted">—</span>' ?></td>
<td dir="auto"><strong><?= e($show($c['new'])) ?></strong></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if (!$s['person_id']): ?><label class="check"><input type="checkbox" name="link" value="1" checked> <?= e(__('Also link this response to that person')) ?></label><?php endif; ?>
<button class="btn btn-primary btn-sm"><?= e(__('Update profile')) ?></button></form>
<?php elseif ($target): ?><p class="small"><?= e(__('Nothing to change — the profile already matches.')) ?><?= $s['profile_applied_at'] ? ' ' . e(__('Last updated from this response on %s.', fmt_datetime($s['profile_applied_at']))) : '' ?></p>
<?php endif; ?>
</div>
<?php endif; ?>
</div>

<div>
<?php if ((int)$s['amount_cents'] || $payments || $s['tickets']): ?>
<div class="card no-print"><h2 class="h3"><?= e(__('Payment')) ?></h2>
<?php if ($s['tickets']): ?><p><strong><?= e(Forms::ticketSummary($s)) ?></strong><?= (int)$s['places'] > 1 ? ' · ' . e(__('%d places', (int)$s['places'])) : '' ?></p><?php endif; ?>
<p><?= e(Forms::paymentSummary($s)) ?></p>
<?php if ($payments): ?><ul class="small"><?php foreach ($payments as $pm): ?><li><?= e(fmt_date(substr($pm['paid_at'], 0, 10))) ?> · <?= e(fmt_money((int)$pm['amount_cents'])) ?> · <?= e(['card' => __('Card'), 'cash' => __('Cash'), 'bank' => __('Bank transfer'), 'manual' => __('Card (recorded by staff)')][$pm['method']] ?? $pm['method']) ?><?= (int)$pm['refunded_cents'] ? ' · <span class="badge badge-grey">' . e(__('%s refunded', fmt_money((int)$pm['refunded_cents']))) . '</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($s['stripe_subscription_id'] && (int)$s['paid_cents'] < (int)$s['amount_cents'] && $s['status'] !== 'cancelled'): ?>
<form method="post" action="<?= e(url($base . '/stop-instalments')) ?>" data-confirm="<?= e(__('Stop the remaining monthly payments? Nothing already paid is refunded.')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Stop monthly payments')) ?></button></form>
<?php endif; ?>
<?php if ((int)$s['paid_cents'] > 0): $card = Forms::refundable($s); ?>
<details style="margin-top:10px"><summary><?= e(__('Refund')) ?></summary>
<form method="post" action="<?= e(url($base . '/refund')) ?>" class="form" style="margin-top:8px" data-confirm="<?= e(__('Make this refund? A card refund can\'t be undone.')) ?>"><?= csrf_field() ?>
<div class="grid-2"><label><?= e(__('Amount')) ?><input name="amount" inputmode="decimal" required value="<?= e(number_format((int)$s['paid_cents'] / 100, 2, '.', '')) ?>"></label>
<label><?= e(__('How')) ?><select name="how"><?= options(array_filter(['card' => $card > 0 ? __('Back to their card (Stripe)') : null, 'cash' => __('Cash (record only)'), 'bank' => __('Bank transfer (record only)')]), $card > 0 ? 'card' : 'bank') ?></select></label></div>
<?php if ($card > 0): ?><p class="small muted"><?= e(__('%s can be refunded to the card.', fmt_money($card))) ?></p><?php endif; ?>
<label class="check"><input type="checkbox" name="cancel" value="1" checked> <?= e(__('Cancel the response too (stops monthly payments and frees any event place)')) ?></label>
<p class="small muted"><?= e(__('Untick this for a part refund such as a discount: the amount owed goes down by the refund instead.')) ?></p>
<button class="btn btn-sm btn-danger"><?= e(__('Refund')) ?></button></form></details>
<?php endif; ?>
<?php if ((int)$s['paid_cents'] < (int)$s['amount_cents'] && $s['status'] !== 'cancelled'): ?>
<details style="margin-top:10px"><summary><?= e(__('Record a payment')) ?></summary>
<form method="post" action="<?= e(url($base . '/payment')) ?>" class="form" style="margin-top:8px"><?= csrf_field() ?>
<div class="grid-2"><label><?= e(__('Amount')) ?><input name="amount" inputmode="decimal" required value="<?= e(number_format(((int)$s['amount_cents'] - (int)$s['paid_cents']) / 100, 2, '.', '')) ?>"></label>
<label><?= e(__('How')) ?><select name="method"><?= options(['cash' => __('Cash'), 'bank' => __('Bank transfer'), 'card' => __('Card (recorded by staff)')], 'cash') ?></select></label></div>
<button class="btn btn-sm"><?= e(__('Record payment')) ?></button></form></details>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="card no-print"><h2 class="h3"><?= e(__('Manage')) ?></h2>
<form method="post" action="<?= e(url($base)) ?>" class="form"><?= csrf_field() ?>
<label><?= e(__('Status')) ?><select name="status"><?= options(['submitted' => __('Submitted'), 'awaiting_payment' => __('Not yet paid'), 'part_paid' => __('Paying in instalments'), 'overdue' => __('Overdue'), 'paid' => __('Paid'), 'cancelled' => __('Cancelled')], $s['status']) ?></select></label>
<label><?= e(__('Staff notes')) ?><textarea name="staff_notes" rows="3" maxlength="500"><?= e((string)$s['staff_notes']) ?></textarea></label>
<button class="btn btn-primary btn-sm"><?= e(__('Save')) ?></button></form>
<form method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="<?= e(__('Delete this response?')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete response')) ?></button></form>
</div>
</div></div>
