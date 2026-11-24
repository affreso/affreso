<div class="page-head"><div><h1><?= e($title) ?></h1><p><?= e(__('Sign-ups and forms for camps, baptisms, enquiries and more. Share the link, collect responses and payments in one place.')) ?></p></div></div>
<div class="split">
<div class="card table-wrap">
<table><thead><tr><th><?= e(__('Form')) ?></th><th><?= e(__('Status')) ?></th><th class="right"><?= e(__('Responses')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
<?php foreach ($forms as $f): ?>
<tr><td><a href="<?= e(url('/forms/' . $f['id'] . '/edit')) ?>"><strong><?= e($f['title']) ?></strong></a>
  <div class="small muted"><?= e($f['audience'] === 'members' ? __('Members only') : __('Anyone with the link')) ?><?= Forms::isPaid($f) ? ' · ' . e(Forms::priceText($f)) : '' ?><?= $f['closes_at'] ? ' · ' . e(__('closes %s', fmt_date($f['closes_at']))) : '' ?></div></td>
<td><?= badge($f['status']) ?></td><td class="right"><?= (int)$f['responses'] ?><?= $f['capacity'] ? ' / ' . (int)$f['capacity'] : '' ?></td>
<td class="actions-cell"><a class="btn btn-sm" href="<?= e(url('/forms/' . $f['id'] . '/responses')) ?>"><?= e(__('Responses')) ?></a></td></tr>
<?php endforeach; ?>
<?php if (!$forms): ?><tr><td colspan="4" class="empty"><?= e(__('No forms yet.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="card"><h2 class="h3"><?= e(__('New form')) ?></h2>
<form method="post" action="<?= e(url('/forms')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Start from')) ?><select name="template"><?php foreach (FormsController::templates() as $k => [$label]): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label><?= e(__('Title')) ?><input name="title" maxlength="160" placeholder="<?= e(__('e.g. Youth Camp 2027')) ?>"></label>
  <button class="btn btn-primary"><?= e(__('Create form')) ?></button>
</form></div>
</div>
