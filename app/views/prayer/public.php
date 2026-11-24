<div class="card" style="max-width:600px;margin:0 auto"><h1><?= e(__('How can we pray for you?')) ?></h1>
<p class="muted"><?= e(__('Our prayer team would love to pray for you. Requests are kept confidential.')) ?></p>
<form method="post" action="<?= e(url('/pray')) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Your name (optional)')) ?><input name="name" autocomplete="name"></label>
  <label><?= e(__('Email (optional, if you would like us to follow up)')) ?><input type="email" name="email" autocomplete="email"></label>
  <label><?= e(__('Prayer request')) ?><textarea name="request" rows="6" required></textarea></label>
  <label><?= e(__('Who should see it?')) ?><select name="visibility"><?= options(['team' => __('Our prayer team'), 'pastors' => __('Pastors only')]) ?></select></label>
  <div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <label class="check"><input type="checkbox" name="consent" value="1" required> <?= e(__('I agree to my request being stored and shared with the people chosen above.')) ?></label>
  <button class="btn btn-primary"><?= e(__('Send prayer request')) ?></button>
</form></div>
