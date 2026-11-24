<?php $refresh = [90, url('/kiosk')]; ?>
<div class="kiosk-card">
  <h1><?= e(__('Who is checking in today?')) ?></h1>
  <form method="post" action="<?= e(url('/kiosk/checkin')) ?>"><?= csrf_field() ?>
    <fieldset class="kiosk-kids"><legend class="sr-only"><?= e(__('Children')) ?></legend>
    <?php foreach ($kids as $c): ?>
      <label class="kiosk-kid<?= $c['in'] ? ' is-in' : '' ?>">
        <input type="checkbox" name="child_ids[]" value="<?= (int)$c['id'] ?>" <?= $c['in'] ? 'disabled' : 'checked' ?>>
        <span class="kid-name"><?= e($c['short']) ?></span>
        <span class="kid-class"><?= e($c['in'] ? __('Already checked in') : ($c['pre'] ? __('Pre-checked in on your phone') . ' · ' : '') . ($c['class'] ?: __('A leader will show you where to go'))) ?></span>
      </label>
    <?php endforeach; ?>
    </fieldset>
    <?php if ($adults): ?><fieldset class="kiosk-adults"><legend><?= e(__('Dropped off by')) ?></legend>
      <?php foreach ($adults as $i => $a): ?><label class="kiosk-pill"><input type="radio" name="adult_id" value="<?= (int)$a['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= e($a['short']) ?></label><?php endforeach; ?>
    </fieldset><?php endif; ?>
    <div class="kiosk-actions"><a class="btn kiosk-btn" href="<?= e(url('/kiosk')) ?>"><?= e(__('Start again')) ?></a><button class="btn btn-primary kiosk-btn"><?= e(__('Check in')) ?></button></div>
  </form>
  <p class="small muted"><?= e(__('Allergies or care needs changed? Please tell a leader, or update them in My Affreso → My children.')) ?></p>
</div>
