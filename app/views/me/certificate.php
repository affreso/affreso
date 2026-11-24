<p class="no-print"><button class="btn" data-print><?= e(__('Print or save as PDF')) ?></button></p>
<div class="certificate">
  <div class="logo-mark" style="margin:0 auto 10px">A</div>
  <div class="small muted"><?= e(setting('church_name')) ?></div>
  <h1><?= e(__('Certificate of Completion')) ?></h1>
  <p><?= e(__('This certifies that')) ?></p>
  <div class="name"><?= e(trim($p['first_name'] . ' ' . $p['last_name'])) ?></div>
  <p><?= e(__('has successfully completed')) ?></p>
  <h2><?= e($e['title']) ?></h2>
  <p><?= e(__('on %s', fmt_date($e['completed_at'], 'j F Y'))) ?><?= $e['expires_at'] ? ' · ' . e(__('valid until %s', fmt_date($e['expires_at'], 'j F Y'))) : '' ?></p>
  <p class="small muted"><?= e(__('Verification code: %s', $e['certificate_code'])) ?><br><?= e(url('/verify/' . $e['certificate_code'], [], true)) ?></p>
</div>
