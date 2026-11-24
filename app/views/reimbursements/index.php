<div class="page-head"><div><h1><?= e(__('Reimbursements')) ?></h1><p><?= e(__('Claim money you spent on church ministry. Approved by your ministry leader, then paid by the treasurer.')) ?></p></div></div>
<?php if ($toPay): ?>
<form method="post" action="<?= e(url('/reimbursements/pay')) ?>" class="card table-wrap"><?= csrf_field() ?><h2><?= e(__('Approved — ready to pay')) ?></h2>
<table><thead><tr><th><input type="checkbox" data-check-all="ids[]" aria-label="<?= e(__('Select all')) ?>"></th><th><?= e(__('Claimant')) ?></th><th><?= e(__('For')) ?></th><th><?= e(__('Amount')) ?></th><th><?= e(__('Receipt')) ?></th></tr></thead><tbody>
<?php foreach ($toPay as $r): ?><tr><td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" checked aria-label="<?= e(__('Select')) ?>"></td><td><?= e(person_name($r)) ?><div class="small muted"><?= e($r['payee_name']) ?></div></td><td><?= e($r['title']) ?><div class="small muted"><?= e($r['group_name']) ?> · <?= e(fmt_date($r['expense_date'])) ?></div></td><td><?= e(fmt_money((int)$r['amount_cents'])) ?></td><td><?= $r['receipt_file_id'] ? '<a href="' . e(url('/reimbursements/' . $r['id'] . '/receipt')) . '" target="_blank">' . e(__('view')) . '</a>' : '—' ?></td></tr><?php endforeach; ?>
</tbody></table>
<div class="form-row" style="margin-top:10px"><label><?= e(__('Payment reference')) ?><input name="payment_ref" placeholder="<?= e(__('e.g. EFT batch 23 Nov')) ?>"></label><button class="btn btn-primary"><?= e(__('Mark selected as paid')) ?></button>
<button class="btn" formaction="<?= e(url('/reimbursements/export-selected')) ?>" name="format" value="aba"><?= e(__('Download ABA bank file')) ?></button><button class="btn" formaction="<?= e(url('/reimbursements/export-selected')) ?>" name="format" value="csv"><?= e(__('Export for Xero (bills CSV)')) ?></button></div>
<p class="small muted"><?= e(__('Upload the ABA file to your online banking to pay everyone in one batch, then mark them as paid.')) ?></p>
</form>
<?php endif; ?>
<?php if ($toApprove): ?>
<div class="card"><h2><?= e(__('Waiting for your approval')) ?></h2>
<?php foreach ($toApprove as $r): ?><div class="slot" style="grid-template-columns:1fr auto"><div><strong><?= e($r['title']) ?></strong> · <?= e(fmt_money((int)$r['amount_cents'])) ?><div class="small muted"><?= e(person_name($r)) ?> · <?= e($r['group_name']) ?> · <?= e($r['category']) ?> · <?= e(fmt_date($r['expense_date'])) ?></div><?= $r['description'] ? '<div class="small">' . e($r['description']) . '</div>' : '' ?><?= $r['receipt_file_id'] ? '<a class="small" href="' . e(url('/reimbursements/' . $r['id'] . '/receipt')) . '" target="_blank">' . e(__('View receipt')) . '</a>' : '' ?></div>
<form method="post" action="<?= e(url('/reimbursements/' . $r['id'] . '/decide')) ?>" class="form"><?= csrf_field() ?><input name="note" placeholder="<?= e(__('Note (optional)')) ?>" aria-label="<?= e(__('Note')) ?>"><button class="btn btn-sm btn-primary" name="decision" value="approve"><?= e(__('Approve')) ?></button> <button class="btn btn-sm" name="decision" value="reject"><?= e(__('Decline')) ?></button></form></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="split"><div class="card table-wrap"><h2><?= e(__('My claims')) ?></h2><table><tbody>
<?php foreach ($mine as $r): ?><tr><td><?= e($r['title']) ?><div class="small muted"><?= e(fmt_date($r['expense_date'])) ?> · <?= e($r['group_name']) ?></div><?= $r['approver_note'] ? '<div class="small">' . e($r['approver_note']) . '</div>' : '' ?></td><td><?= e(fmt_money((int)$r['amount_cents'])) ?></td><td><?= badge($r['status'] === 'rejected' ? 'declined' : $r['status']) ?><?= $r['payment_ref'] ? '<div class="small muted">' . e($r['payment_ref']) . '</div>' : '' ?></td></tr><?php endforeach; ?>
<?php if (!$mine): ?><tr><td class="empty"><?= e(__('No claims yet.')) ?></td></tr><?php endif; ?></tbody></table></div>
<form method="post" action="<?= e(url('/reimbursements')) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?><h2 class="h3"><?= e(__('New claim')) ?></h2>
  <label><?= e(__('What was it for?')) ?><input name="title" required></label>
  <div class="grid-2"><label><?= e(__('Amount ($)')) ?><input name="amount" required inputmode="decimal"></label><label><?= e(__('Date of expense')) ?><input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>"></label>
  <label><?= e(__('Category')) ?><select name="category"><?= options(ReimbursementsController::categories()) ?></select></label><label><?= e(__('Ministry')) ?><select name="group_id"><?= options($groups, null, __('General')) ?></select></label></div>
  <label><?= e(__('Details')) ?><textarea name="description" rows="2"></textarea></label>
  <label><?= e(__('Receipt (photo or PDF)')) ?><input type="file" name="receipt" accept="image/*,application/pdf" <?= Demo::on() ? '' : 'required' ?>></label>
  <fieldset><legend><?= e(__('Pay to')) ?></legend>
    <?php if ($hasSaved): ?><label class="check"><input type="checkbox" name="use_saved" value="1" checked> <?= e(__('Use the bank account from my last claim')) ?></label><?php endif; ?>
    <label><?= e(__('Account name')) ?><input name="payee_name" autocomplete="off"></label>
    <div class="grid-2"><label>BSB<input name="bsb" inputmode="numeric" maxlength="7" autocomplete="off"></label><label><?= e(__('Account number')) ?><input name="account" inputmode="numeric" maxlength="9" autocomplete="off"></label></div>
    <p class="help"><?= e(__('Bank details are encrypted and only used by the treasurer to pay you.')) ?></p>
  </fieldset>
  <button class="btn btn-primary"><?= e(__('Submit claim')) ?></button></form></div>
