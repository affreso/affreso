<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/account')) ?>"><?= e(__('Account')) ?></a></div><h1><?= e(__('Two-step verification')) ?></h1></div></div>
<?php if ($codes): ?>
<div class="card">
  <h2><?= e(__('Your recovery codes')) ?></h2>
  <p><?= e(__('Each code can be used once if you lose your phone. Print or store them somewhere safe — they will not be shown again.')) ?></p>
  <ul class="secret grid-4 list-plain"><?php foreach ($codes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
  <button class="btn no-print" data-print><?= e(__('Print codes')) ?></button>
</div>
<?php endif; ?>
<?php if ((int)$u['mfa_enabled']): ?>
<div class="card">
  <p><?= badge('active') ?> <?= e(__('Two-step verification is on for your account.')) ?></p>
  <?php if (!Auth::needsMfaIfDisabled()): ?>
  <form method="post" action="<?= e(url('/account/mfa/disable')) ?>" class="form-row">
    <?= csrf_field() ?>
    <label><?= e(__('Confirm with your password to turn it off')) ?><input type="password" name="password" required></label>
    <button class="btn btn-danger"><?= e(__('Turn off')) ?></button>
  </form>
  <?php else: ?><p class="muted small"><?= e(__('Your role requires two-step verification, so it stays on.')) ?></p><?php endif; ?>
</div>
<?php else: ?>
<?php if ($required): ?><div class="callout"><?= e(__('Because your role gives access to sensitive personal information, two-step verification is required before you can continue.')) ?></div><?php endif; ?>
<div class="card">
  <h2><?= e(__('Set up your authenticator app')) ?></h2>
  <ol class="steps">
    <li><?= e(__('Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 1Password, Authy…).')) ?></li>
    <li><?= e(__('Scan this QR code, or type the key in manually:')) ?></li>
  </ol>
  <div class="qr-box"><div id="mfa-qr" data-uri="<?= e($uri) ?>" style="width:200px"></div></div>
  <p class="secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
  <form method="post" action="<?= e(url('/account/mfa/enable')) ?>" class="form-row">
    <?= csrf_field() ?>
    <label><?= e(__('Enter the 6-digit code shown in the app')) ?><input name="code" required inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>
    <button class="btn btn-primary"><?= e(__('Turn on')) ?></button>
  </form>
</div>
<script src="<?= e(asset('vendor/qrcode.js')) ?>"></script>
<?php endif; ?>
