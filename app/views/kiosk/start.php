<?php if (!$s): $refresh = [60, url('/kiosk')]; ?>
<div class="kiosk-card center">
  <h1><?= e(__('Check-in is not open yet')) ?></h1>
  <p><?= e(__('Please see the welcome desk. This screen will refresh by itself.')) ?></p>
</div>
<?php else: ?>
<div class="kiosk-card center">
  <h1><?= e(__('Welcome! Let\'s check your children in')) ?></h1>
  <p class="kiosk-sub"><?= e($s['title']) ?></p>
  <form method="post" action="<?= e(url('/kiosk/find')) ?>" autocomplete="off" data-keypad-form><?= csrf_field() ?>
    <label class="kiosk-label" for="phone"><?= e(__('Enter your mobile number')) ?></label>
    <input id="phone" class="kiosk-input kiosk-phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9 +()\-]{8,20}" required autocomplete="off">
    <div class="keypad" data-keypad="phone" aria-hidden="true">
      <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', 'clear', '0', 'back'] as $key): ?>
        <button type="button" tabindex="-1" data-key="<?= $key ?>"><?= $key === 'clear' ? e(__('Clear')) : ($key === 'back' ? '⌫' : $key) ?></button>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary kiosk-btn"><?= e(__('Find my family')) ?></button>
  </form>
  <p class="small muted"><?= e(__('Visiting for the first time? Please see the welcome desk — we\'d love to meet you.')) ?></p>
</div>
<?php endif; ?>
