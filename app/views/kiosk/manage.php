<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/checkin')) ?>"><?= e(__("Children's check-in")) ?></a></div><h1><?= e($title) ?></h1>
<p><?= e(__('Let families check their own children in on a tablet at the door. Children are still collected through the welcome desk using the security code.')) ?></p></div></div>
<?php if (Demo::on()): ?><div class="alert alert-info" role="status"><?= e(__('Demo site: create a pairing code for the Foyer iPad, open the kiosk address in another browser tab or on a tablet, and use mobile number 0428 044 740. The exit PIN is 1234.')) ?></div><?php endif; ?>
<?php if ($pairing): ?>
<div class="card callout" role="status">
  <h2 class="h3"><?= e(__('Pairing code')) ?></h2>
  <p class="kiosk-code" style="text-align:left"><?= e($pairing['code']) ?></p>
  <ol class="small">
    <li><?= e(__('On the tablet, open this address in the browser:')) ?> <code><?= e(url('/kiosk/pair', [], true)) ?></code></li>
    <li><?= e(__('Enter the code above. It works once and expires in 30 minutes.')) ?></li>
    <li><?= e(__('Lock the tablet to the browser: Guided Access on iPad, or screen pinning on Android.')) ?></li>
  </ol>
</div>
<?php endif; ?>
<div class="split">
<div class="card table-wrap"><h2 class="h3"><?= e(__('Kiosks')) ?></h2>
<table><thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Last used')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
<?php foreach ($kiosks as $k): $state = !$k['active'] ? 'disabled' : ($k['token_hash'] ? 'paired' : ($k['pair_hash'] && strtotime((string)$k['pair_expires']) > time() ? 'waiting' : 'not paired')); ?>
<tr><td><strong><?= e($k['name']) ?></strong><?= $k['campus'] && Campus::multi() ? '<div class="small muted">' . e($k['campus']) . '</div>' : '' ?></td>
<td><?= badge($state) ?></td><td class="small"><?= e($k['last_seen_at'] ? fmt_datetime($k['last_seen_at']) : '—') ?></td>
<td class="actions-cell">
  <form class="inline-form" method="post" action="<?= e(url('/checkin/kiosks/' . $k['id'] . '/pair')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e($k['token_hash'] ? __('Re-pair') : __('New pairing code')) ?></button></form>
  <?php if ($k['active']): ?><form class="inline-form" method="post" action="<?= e(url('/checkin/kiosks/' . $k['id'] . '/revoke')) ?>" data-confirm="<?= e(__('Switch off this kiosk? The device stops working immediately.')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Switch off')) ?></button></form><?php endif; ?>
  <details class="inline-details"><summary class="btn btn-sm"><?= e(__('Settings')) ?></summary>
    <form method="post" action="<?= e(url('/checkin/kiosks/' . $k['id'])) ?>" class="form"><?= csrf_field() ?>
      <label><?= e(__('Name')) ?><input name="name" value="<?= e($k['name']) ?>" required></label>
      <?= Campus::field($k['campus_id'] ? (int)$k['campus_id'] : null) ?>
      <label><?= e(__('New exit PIN (leave blank to keep)')) ?><input name="exit_pin" inputmode="numeric" pattern="\d{4,8}" autocomplete="off"></label>
      <label class="check"><input type="checkbox" name="print_labels" value="1" <?= $k['print_labels'] ? 'checked' : '' ?>> <?= e(__('Print labels after check-in')) ?></label>
      <?php if ($printers): ?><label><?= e(__('Label printer')) ?><select name="printer_id"><?= options($printers, $k['printer_id'], __('— Default label printer —')) ?></select></label><?php endif; ?>
      <button class="btn btn-sm btn-primary"><?= e(__('Save')) ?></button>
    </form></details>
</td></tr>
<?php endforeach; ?>
<?php if (!$kiosks): ?><tr><td colspan="4" class="empty"><?= e(__('No kiosks yet.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="card"><h2 class="h3"><?= e(__('Add a kiosk')) ?></h2>
<form method="post" action="<?= e(url('/checkin/kiosks')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Name')) ?><input name="name" required placeholder="<?= e(__('e.g. Foyer iPad')) ?>"></label>
  <?= Campus::field() ?>
  <label><?= e(__('Exit PIN (4–8 digits)')) ?><input name="exit_pin" inputmode="numeric" pattern="\d{4,8}" required autocomplete="off"><span class="help"><?= e(__('Staff need this to leave kiosk mode on the device.')) ?></span></label>
  <label class="check"><input type="checkbox" name="print_labels" value="1" checked> <?= e(__('Print labels after check-in')) ?></label>
  <button class="btn btn-primary"><?= e(__('Create kiosk')) ?></button>
</form>
<h2 class="h3"><?= e(__('How it keeps children safe')) ?></h2>
<ul class="small">
  <li><?= e(__('Families are found only by the full mobile number of an adult in the household.')) ?></li>
  <li><?= e(__('The screen shows first names and initials only — never surnames, allergies or medical details.')) ?></li>
  <li><?= e(__('The kiosk only works while a check-in session is open for today.')) ?></li>
  <li><?= e(__('Searches are rate-limited, and the screen clears itself when nobody touches it for 90 seconds.')) ?></li>
  <li><?= e(__('Check-out still happens with a leader, using the security code.')) ?></li>
</ul></div>
</div>
