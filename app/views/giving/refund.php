<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/giving')) ?>"><?= e(__('Giving')) ?></a></div><h1><?= e(__('Refund a gift')) ?></h1></div></div>
<div class="split"><div>
<div class="card">
<dl class="answers">
<dt><?= e(__('Donor')) ?></dt><dd><?= e(person_name($d) ?: ($d['donor_name'] ?: __('Anonymous'))) ?></dd>
<dt><?= e(__('Gift')) ?></dt><dd><?= e(fmt_money(Gifts::gross($d), $d['currency'])) ?> · <?= e($d['fund'] ?: __('Unallocated')) ?> · <?= e(fmt_date($d['received_on'] ?: $d['created_at'])) ?> · <?= e(strtoupper($d['method'])) ?><?= $d['receipt_no'] ? ' · ' . e($d['receipt_no']) : '' ?></dd>
<?php if ((int)$d['refunded_cents']): ?><dt><?= e(__('Already refunded')) ?></dt><dd><?= e(fmt_money((int)$d['refunded_cents'], $d['currency'])) ?></dd><?php endif; ?>
</dl>
<?php if ($d['status'] !== 'paid' || (int)$d['amount_cents'] <= 0): ?><p><?= e(__('There is nothing left to refund on this gift.')) ?></p><?php else: $card = Gifts::cardRefundable($d); ?>
<form method="post" action="<?= e(url('/giving/' . $d['id'] . '/refund')) ?>" class="form" data-confirm="<?= e(__('Make this refund? A card refund can\'t be undone.')) ?>"><?= csrf_field() ?>
<div class="grid-2"><label><?= e(__('Amount')) ?><input name="amount" inputmode="decimal" required value="<?= e(number_format((int)$d['amount_cents'] / 100, 2, '.', '')) ?>"></label>
<label><?= e(__('How')) ?><select name="how"><?= options(array_filter(['card' => $card ? __('Back to their card (Stripe)') : null, 'record' => __('Record only (cash, bank or a correction)')]), $card ? 'card' : 'record') ?></select></label></div>
<label><?= e(__('Reason (optional)')) ?><input name="reason" maxlength="80" placeholder="<?= e(__('e.g. given twice by mistake')) ?>"></label>
<button class="btn btn-danger"><?= e(__('Refund')) ?></button></form>
<?php endif; ?>
</div></div>
<div><div class="card"><h2 class="h3"><?= e(__('What happens')) ?></h2>
<p class="small"><?= e(__('The gift is reduced by the refund, so the donor\'s giving statement, your reports and any campaign totals show only what the church kept. A gift refunded in full is marked Refunded and leaves the statement.')) ?></p>
<p class="small"><?= e(__('The donor gets an email saying how much was refunded. If a statement has already been sent for this year, send them an updated one.')) ?></p>
<p class="small muted"><?= e(__('Refunds made in the Stripe dashboard are picked up automatically.')) ?></p></div></div></div>
