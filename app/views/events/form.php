<?php $isNew = empty($ev['id']); $v = fn($k) => e($ev[$k] ?? ''); $dt = fn($k) => e(!empty($ev[$k]) ? date('Y-m-d\TH:i', strtotime($ev[$k])) : ''); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/events')) ?>"><?= e(__('Events & calendar')) ?></a></div><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url($isNew ? '/events' : '/events/' . $ev['id'])) ?>" class="form"><?= csrf_field() ?>
<div class="split">
<div class="card">
  <label><?= e(__('Title')) ?><input name="title" required value="<?= $v('title') ?>"></label>
  <div class="grid-2">
    <label><?= e(__('Starts')) ?><input type="datetime-local" name="start_at" required value="<?= $dt('start_at') ?>"></label>
    <label><?= e(__('Ends')) ?><input type="datetime-local" name="end_at" required value="<?= $dt('end_at') ?>"></label>
    <label><?= e(__('Category')) ?><input name="category" value="<?= $v('category') ?>" list="cats"><datalist id="cats"><option value="Service"><option value="Community"><option value="Prayer"><option value="Youth"><option value="Kids"><option value="Training"><option value="Outreach"></datalist></label>
    <label><?= e(__('Location')) ?><input name="location" value="<?= $v('location') ?>"></label>
    <?= Campus::field(isset($ev['campus_id']) ? (int)$ev['campus_id'] : null) ?>
  </div>
  <label><?= e(__('Description')) ?><textarea name="description"><?= $v('description') ?></textarea></label>
  <label class="check"><input type="checkbox" name="is_public" value="1" <?= !empty($ev['is_public']) ? 'checked' : '' ?>> <?= e(__('Public — show on the church website calendar')) ?></label>
  <?php
  $series = $series ?? null;
  $rp = $series ?: ['freq' => 'none', 'interval_n' => 1, 'weekdays' => '', 'until_date' => null, 'max_count' => null];
  $rpDays = array_map('intval', array_filter(explode(',', (string)$rp['weekdays'])));
  $rpEnd = $rp['until_date'] ? 'until' : ($rp['max_count'] ? 'count' : 'never');
  $dayNames = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')];
  ?>
  <fieldset class="fieldset" data-reveal-group>
    <legend><?= e(__('Repeat')) ?></legend>
    <div class="grid-2">
      <label><?= e(__('Repeats')) ?><select name="repeat" data-reveal><?= options(['none' => __('Does not repeat'), 'weekly' => __('Weekly'), 'monthly_date' => __('Monthly on the same date'), 'monthly_weekday' => __('Monthly on the same weekday (e.g. 2nd Sunday)')], $rp['freq']) ?></select></label>
      <label data-reveal-for="weekly monthly_date monthly_weekday"><?= e(__('Every')) ?><span class="inline-input"><input type="number" name="repeat_interval" min="1" max="12" value="<?= (int)$rp['interval_n'] ?>" style="max-width:80px"> <?= e(__('week(s) or month(s)')) ?></span></label>
    </div>
    <div data-reveal-for="weekly" class="checks-inline" role="group" aria-label="<?= e(__('On these days')) ?>"><span class="small muted"><?= e(__('On')) ?></span>
      <?php foreach ($dayNames as $n => $dn): ?><label class="check"><input type="checkbox" name="repeat_days[]" value="<?= $n ?>" <?= in_array($n, $rpDays, true) ? 'checked' : '' ?>> <?= e($dn) ?></label><?php endforeach; ?>
    </div>
    <div class="grid-2" data-reveal-for="weekly monthly_date monthly_weekday">
      <label><?= e(__('Ends')) ?><select name="repeat_end"><?= options(['never' => __('Keeps going (scheduled %d months ahead)', EventSeries::horizonMonths()), 'until' => __('On a date'), 'count' => __('After a number of times')], $rpEnd) ?></select></label>
      <div class="grid-2"><label><?= e(__('End date')) ?><input type="date" name="repeat_until" value="<?= e($rp['until_date'] ?? '') ?>"></label><label><?= e(__('Times')) ?><input type="number" name="repeat_count" min="1" max="500" value="<?= e($rp['max_count'] ?? '') ?>"></label></div>
    </div>
    <?php if (!$isNew && $series): ?>
      <p class="help"><?= e(__('This event is part of a series: %s.', EventSeries::describe($series, $ev['start_at']))) ?></p>
      <div role="radiogroup" aria-label="<?= e(__('Apply changes to')) ?>">
        <label class="check"><input type="radio" name="scope" value="one" checked> <?= e(__('Change this date only')) ?></label>
        <label class="check"><input type="radio" name="scope" value="following"> <?= e(__('Change this and all following dates (including the repeat pattern)')) ?></label>
      </div>
    <?php elseif (!$isNew): ?><p class="help"><?= e(__('To make an existing event repeat, choose a pattern — it will be repeated from this date.')) ?></p><?php endif; ?>
  </fieldset>
  <?php if (!$isNew): ?><label><?= e(__('Status')) ?><select name="status"><?= options(['scheduled' => __('Scheduled'), 'cancelled' => __('Cancelled (notifies registrants)')], $ev['status']) ?></select></label><?php endif; ?>
</div>
<div>
  <div class="card"><h2 class="h3"><?= e(__('Registration')) ?></h2>
    <label class="check"><input type="checkbox" name="registration_enabled" value="1" <?= !empty($ev['registration_enabled']) ? 'checked' : '' ?>> <?= e(__('Take registrations')) ?></label>
    <label><?= e(__('Capacity (blank = unlimited)')) ?><input type="number" min="1" name="capacity" value="<?= $v('capacity') ?>"><span class="help"><?= e(__('When full, people join a waiting list and are promoted automatically if someone cancels.')) ?></span></label>
    <label><?= e(__('Registrations close')) ?><input type="datetime-local" name="registration_closes" value="<?= $dt('registration_closes') ?>"></label>
    <label><?= e(__('Joining instructions (emailed on registration)')) ?><textarea name="joining_instructions"><?= $v('joining_instructions') ?></textarea></label>
  </div>
  <div class="card"><h2 class="h3"><?= e(__('Rooms & equipment')) ?></h2>
    <?php foreach ($rooms as $rid => $rn): ?><label class="check"><input type="checkbox" name="room_ids[]" value="<?= (int)$rid ?>" <?= in_array((int)$rid, $roomIds, true) ? 'checked' : '' ?>> <?= e($rn) ?></label><?php endforeach; ?>
    <p class="help"><?= e(__('Clashing bookings are detected and will not be double-booked.')) ?></p>
  </div>
  <button class="btn btn-primary"><?= e($isNew ? __('Create event') : __('Save event')) ?></button>
</div>
</div>
</form>
