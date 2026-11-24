<div class="page-head"><div><h1><?= e(__("Children's check-in")) ?></h1><p><?= e(__('Secure check-in and check-out with matching security codes, printed labels and authorised collectors.')) ?></p></div>
<?php if (can('checkin.kiosk')): ?><div class="actions"><a class="btn" href="<?= e(url('/checkin/kiosks')) ?>"><?= e(__('Self check-in kiosks')) ?></a></div><?php endif; ?></div>
<div class="split">
<div>
  <div class="card"><h2><?= e(__('Start a session')) ?></h2>
  <form method="post" action="<?= e(url('/checkin/sessions')) ?>" class="form-row"><?= csrf_field() ?>
    <label><?= e(__('Title')) ?><input name="title" value="<?= e(__('Sunday kids church')) ?>"></label>
    <label><?= e(__('Date')) ?><input type="date" name="session_date" value="<?= e(date('Y-m-d')) ?>"></label>
    <label><?= e(__('Starts')) ?><input type="time" name="start_time" value="09:30"></label>
    <?= Campus::field() ?>
    <button class="btn btn-primary"><?= e(__('Open check-in')) ?></button>
  </form></div>
  <div class="card table-wrap"><h2><?= e(__('Recent sessions')) ?></h2><table><thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Session')) ?></th><th><?= e(__('Children')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
  <?php foreach ($sessions as $s): ?><tr><td><?= e(fmt_date($s['session_date'], 'D j M')) ?></td><td><a href="<?= e(url('/checkin/sessions/' . $s['id'])) ?>"><?= e($s['title']) ?></a> <?= Campus::multi() ? '<span class="small muted">' . e(Campus::name($s['campus_id'] ? (int)$s['campus_id'] : null)) . '</span>' : '' ?></td><td><?= (int)$s['kids'] ?><?= (int)$s['present'] ? ' · <strong>' . e(__('%d here now', (int)$s['present'])) . '</strong>' : '' ?></td><td><?= badge($s['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$sessions): ?><tr><td colspan="4" class="empty"><?= e(__('No sessions yet.')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<div class="card"><h2 class="h3"><?= e(__('Classes')) ?></h2>
  <p class="small muted"><?= e(__('Children are placed in a class by age. Ratio = children per leader.')) ?></p>
  <?php foreach ($classes as $c): ?><details><summary><?= e($c['name']) ?> <span class="small muted"><?= e(__('ages %s–%s · 1:%d', $c['min_age'] ?? '?', $c['max_age'] ?? '+', (int)$c['ratio'])) ?></span> <?= (int)$c['active'] ? '' : badge('inactive') ?></summary>
    <form method="post" action="<?= e(url('/checkin/classes')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <label><?= e(__('Name')) ?><input name="name" value="<?= e($c['name']) ?>"></label>
      <div class="grid-2"><label><?= e(__('From age')) ?><input type="number" name="min_age" value="<?= e($c['min_age']) ?>"></label><label><?= e(__('To age')) ?><input type="number" name="max_age" value="<?= e($c['max_age']) ?>"></label><label><?= e(__('Children per leader')) ?><input type="number" name="ratio" value="<?= (int)$c['ratio'] ?>"></label><label><?= e(__('Room')) ?><select name="room_id"><?= options($rooms, $c['room_id'], '—') ?></select></label></div>
      <?= Campus::field($c['campus_id'] ? (int)$c['campus_id'] : null) ?>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$c['active'] ? 'checked' : '' ?>> <?= e(__('Active')) ?></label>
      <button class="btn btn-sm"><?= e(__('Save')) ?></button></form></details><?php endforeach; ?>
  <form method="post" action="<?= e(url('/checkin/classes')) ?>" class="form" style="margin-top:12px"><?= csrf_field() ?>
    <label><?= e(__('New class')) ?><input name="name" required placeholder="<?= e(__('e.g. Kids Church (5–7)')) ?>"></label>
    <div class="grid-2"><label><?= e(__('From age')) ?><input type="number" name="min_age"></label><label><?= e(__('To age')) ?><input type="number" name="max_age"></label><label><?= e(__('Children per leader')) ?><input type="number" name="ratio" value="8"></label><label><?= e(__('Room')) ?><select name="room_id"><?= options($rooms, null, '—') ?></select></label></div>
    <button class="btn btn-sm btn-primary"><?= e(__('Add class')) ?></button></form>
</div>
</div>
