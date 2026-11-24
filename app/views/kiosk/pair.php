<div class="kiosk-card">
  <h1><?= e(__('Set up this device as a check-in kiosk')) ?></h1>
  <p><?= e(__('A staff member can create a pairing code in Affreso under Children\'s check-in → Kiosks. Codes work once and expire after 30 minutes.')) ?></p>
  <form method="post" action="<?= e(url('/kiosk/pair')) ?>"><?= csrf_field() ?>
    <label class="kiosk-label" for="code"><?= e(__('Pairing code')) ?></label>
    <input id="code" class="kiosk-input" name="code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="12" placeholder="ABCD-2345" required>
    <button class="btn btn-primary kiosk-btn"><?= e(__('Pair this device')) ?></button>
  </form>
</div>
