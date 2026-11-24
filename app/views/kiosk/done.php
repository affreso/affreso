<?php $refresh = [30, url('/kiosk')]; ?>
<div class="kiosk-card center no-print">
  <h1><?= e(__('You\'re checked in!')) ?></h1>
  <p><?= e(__('Your pick-up code is')) ?></p>
  <div class="kiosk-code"><?= e($rows[0]['security_code'] ?? '') ?></div>
  <ul class="kiosk-list"><?php foreach ($rows as $r): ?><li><strong><?= e($r['short']) ?></strong> — <?= e($r['class'] ?: __('see a leader')) ?></li><?php endforeach; ?></ul>
  <p><?= e(__('Keep the parent tag. You will need this code to collect your children.')) ?></p>
  <?php if ($silent): ?><p><strong><?= e(__('Your labels are printing now.')) ?></strong></p><?php endif; ?>
  <?php if ((int)$k['print_labels'] && !$silent): ?><button type="button" class="btn kiosk-btn" data-print><?= e(__('Print labels again')) ?></button><?php endif; ?>
  <a class="btn btn-primary kiosk-btn" href="<?= e(url('/kiosk')) ?>"><?= e(__('Done')) ?></a>
</div>
<?php if ((int)$k['print_labels'] && !$silent): ?><div class="print-only" data-autoprint>
<?php foreach ($rows as $r): ?>
<div class="label"><div class="label-code"><?= e($r['security_code']) ?></div><div class="label-name"><?= e(person_name($r)) ?></div><div><?= e($r['class']) ?> · <?= e(fmt_date($r['session_date'], 'j M')) ?></div>
<?php if ($r['alert']): ?><div class="label-alert">⚠ <?= e(__('Care alert — see leader')) ?></div><?php endif; ?></div>
<?php endforeach; ?>
<div class="label parent"><div class="small"><?= e(__('PARENT TAG — show at pick-up')) ?></div><div class="label-code"><?= e($rows[0]['security_code'] ?? '') ?></div><div><?= e(implode(', ', array_column($rows, 'short'))) ?></div></div>
</div><?php endif; ?>
