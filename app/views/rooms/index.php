<div class="page-head"><div><h1><?= e(__('Rooms & bookings')) ?></h1><p><?= e(__('Book rooms and equipment. Clashes are detected automatically.')) ?></p></div></div>
<?php if ($requests): ?><div class="card"><h2><?= e(__('Requests awaiting approval')) ?></h2><table><tbody>
<?php foreach ($requests as $b): ?><tr><td><strong><?= e($b['room']) ?></strong> — <?= e($b['title']) ?><div class="small muted"><?= e(fmt_datetime($b['start_at'])) ?> – <?= e(fmt_time($b['end_at'])) ?><?= $b['notes'] ? ' · ' . e($b['notes']) : '' ?></div></td>
<td class="actions-cell"><form class="inline-form" method="post" action="<?= e(url('/bookings/' . $b['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" name="decision" value="approved"><?= e(__('Approve')) ?></button> <button class="btn btn-sm" name="decision" value="declined"><?= e(__('Decline')) ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<div class="split">
<div class="card table-wrap">
  <div class="card-head"><h2><?= e(__('%s – %s', fmt_date($date, 'j M'), fmt_date($weekEnd, 'j M'))) ?></h2>
  <form method="get" action="<?= e(base_path() . '/index.php') ?>" class="form-row"><input type="hidden" name="r" value="/rooms"><input type="date" name="date" value="<?= e($date) ?>" aria-label="<?= e(__('Start date')) ?>"><button class="btn btn-sm"><?= e(__('Go')) ?></button></form></div>
  <table><thead><tr><th><?= e(__('When')) ?></th><th><?= e(__('Room')) ?></th><th><?= e(__('Booking')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
  <?php foreach ($bookings as $b): ?><tr><td class="nowrap small"><?= e(fmt_datetime($b['start_at'])) ?> – <?= e(fmt_time($b['end_at'])) ?></td><td><?= e($b['room']) ?></td><td><?= e($b['title']) ?> <span class="small muted"><?= e(person_name($b)) ?></span></td><td><?= badge($b['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$bookings): ?><tr><td colspan="4" class="empty"><?= e(__('Nothing booked in this period.')) ?></td></tr><?php endif; ?>
  </tbody></table>
  <?php if ($mine): ?><h2 class="h3"><?= e(__('My bookings')) ?></h2><ul class="list-plain small"><?php foreach ($mine as $b): ?><li><?= e($b['room']) ?> — <?= e($b['title']) ?> · <?= e(fmt_datetime($b['start_at'])) ?> <?= badge($b['status']) ?>
  <?php if (in_array($b['status'], ['requested', 'approved'], true)): ?><form class="inline-form" method="post" action="<?= e(url('/bookings/' . $b['id'])) ?>"><?= csrf_field() ?><button class="btn-link" name="decision" value="cancel"><?= e(__('cancel')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<div>
  <div class="card"><h2 class="h3"><?= e(__('Make a booking')) ?></h2>
  <form method="post" action="<?= e(url('/bookings')) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Room or equipment')) ?><select name="room_id" required><?= options(array_column(array_filter($rooms, fn($r) => (int)$r['active']), 'name', 'id'), null, '—') ?></select></label>
    <label><?= e(__('What for?')) ?><input name="title" required></label>
    <label><?= e(__('From')) ?><input type="datetime-local" name="start_at" required value="<?= e($date) ?>T09:00"></label>
    <label><?= e(__('Until')) ?><input type="datetime-local" name="end_at" required value="<?= e($date) ?>T10:00"></label>
    <label><?= e(__('Notes')) ?><input name="notes"></label>
    <button class="btn btn-primary"><?= e(can('rooms.manage') ? __('Book') : __('Request booking')) ?></button>
  </form></div>
  <?php if (can('rooms.manage')): ?>
  <div class="card"><h2 class="h3"><?= e(__('Rooms & equipment')) ?></h2>
    <?php foreach ($rooms as $r): ?><details><summary><?= e($r['name']) ?> <?= (int)$r['active'] ? '' : badge('inactive') ?></summary>
    <form method="post" action="<?= e(url('/rooms/' . $r['id'])) ?>" class="form"><?= csrf_field() ?><label><?= e(__('Name')) ?><input name="name" value="<?= e($r['name']) ?>"></label><label><?= e(__('Capacity')) ?><input type="number" name="capacity" value="<?= e($r['capacity']) ?>"></label><label><?= e(__('Description')) ?><input name="description" value="<?= e($r['description']) ?>"></label>
    <label class="check"><input type="checkbox" name="is_equipment" value="1" <?= (int)$r['is_equipment'] ? 'checked' : '' ?>> <?= e(__('Equipment')) ?></label><label class="check"><input type="checkbox" name="active" value="1" <?= (int)$r['active'] ? 'checked' : '' ?>> <?= e(__('Active')) ?></label><button class="btn btn-sm"><?= e(__('Save')) ?></button></form></details><?php endforeach; ?>
    <form method="post" action="<?= e(url('/rooms')) ?>" class="form" style="margin-top:10px"><?= csrf_field() ?><label><?= e(__('Add room or equipment')) ?><input name="name" required></label><div class="form-row"><label><?= e(__('Capacity')) ?><input type="number" name="capacity"></label><label class="check"><input type="checkbox" name="is_equipment" value="1"> <?= e(__('Equipment')) ?></label></div><button class="btn btn-sm"><?= e(__('Add')) ?></button></form>
  </div>
  <?php endif; ?>
</div>
</div>
