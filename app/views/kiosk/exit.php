<div class="kiosk-card center">
  <h1><?= e(__('Staff: leave kiosk mode')) ?></h1>
  <p><?= e(__('Enter the kiosk exit PIN. The device will be unpaired and need a new pairing code to be used again.')) ?></p>
  <form method="post" action="<?= e(url('/kiosk/exit')) ?>"><?= csrf_field() ?>
    <label class="kiosk-label" for="pin"><?= e(__('Exit PIN')) ?></label>
    <input id="pin" class="kiosk-input" name="pin" type="password" inputmode="numeric" autocomplete="off" required>
    <div class="kiosk-actions"><a class="btn kiosk-btn" href="<?= e(url('/kiosk')) ?>"><?= e(__('Back')) ?></a><button class="btn btn-danger kiosk-btn"><?= e(__('Leave kiosk mode')) ?></button></div>
  </form>
</div>
