<div class="no-print" style="padding:16px">
<?php if ($sent === true): ?><div class="alert alert-success" role="status"><?= e(__('Labels sent to the label printer.')) ?></div><?php elseif ($sent === false): ?><div class="alert alert-error" role="alert"><?= e(__('Could not send to the label printer: %s. Print from the browser instead.', (string)$printError)) ?></div><?php endif; ?>
<?php if ($sent): ?><a class="btn" href="<?= e(url('/checkin/labels', ['ids' => $ids, 'reprint' => 1])) ?>"><?= e(__('Print again')) ?></a><?php else: ?><button class="btn btn-primary" data-print><?= e(__('Print labels')) ?></button><?php endif; ?>
<?php if (Labels::canRender() && $rows): ?><a class="btn" href="<?= e(url('/checkin/labels/pdf', ['ids' => $ids])) ?>" target="_blank" rel="noopener"><?= e(__('PDF')) ?></a><?php endif; ?> <a class="btn" href="<?= e(url('/checkin/sessions/' . ($rows[0]['session_id'] ?? 0))) ?>"><?= e(__('Back to check-in')) ?></a>
<p class="small muted"><?= e(__('Labels are sized for 62 mm × 100 mm label printers (Brother QL / DYMO). Give the parent tag to the adult; they show it at pick-up.')) ?></p></div>
<?php foreach ($rows as $r): ?>
<div class="label"><div class="label-code"><?= e($r['security_code']) ?></div><div class="label-name" dir="auto"><?= e(person_name($r)) ?></div><div><?= e($r['class']) ?> · <?= e(fmt_date($r['session_date'], 'j M')) ?></div>
<?php if ($r['alert']): ?><div class="label-alert">⚠ <?= can('children.medical') && $r['allergies'] !== '' ? e(__('Allergy: %s', mb_strimwidth($r['allergies'], 0, 60, '…'))) : e(__('Care alert — see leader')) ?></div><?php endif; ?></div>
<?php endforeach; ?>
<?php if ($rows): ?><div class="label parent"><div class="small"><?= e(__('PARENT TAG — show at pick-up')) ?></div><div class="label-code"><?= e($rows[0]['security_code']) ?></div><div dir="auto"><?= e(implode(', ', array_map('person_name', $rows))) ?></div></div><?php endif; ?>
