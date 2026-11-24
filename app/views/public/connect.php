<div class="card" style="max-width:680px;margin:0 auto">
<h1><?= e(__('Welcome! We are glad you are here')) ?></h1>
<p class="muted"><?= e(__('Tell us a little about yourself and someone from our welcome team will be in touch.')) ?></p>
<form method="post" action="<?= e(url('/connect')) ?>" class="form"><?= csrf_field() ?>
  <div class="grid-2"><label><?= e(__('First name')) ?><input name="first_name" required autocomplete="given-name"></label><label><?= e(__('Last name')) ?><input name="last_name" required autocomplete="family-name"></label>
  <label><?= e(__('Email')) ?><input type="email" name="email" autocomplete="email"></label><label><?= e(__('Mobile')) ?><input type="tel" name="mobile" autocomplete="tel"></label>
  <label><?= e(__('Suburb')) ?><input name="suburb"></label><label><?= e(__('How did you hear about us?')) ?><input name="heard"></label></div>
  <fieldset><legend><?= e(__("I'd like to know more about…")) ?></legend>
    <label class="check"><input type="checkbox" name="small_group" value="1"> <?= e(__('Joining a small group')) ?></label>
    <label class="check"><input type="checkbox" name="interests[]" value="baptism"> <?= e(__('Baptism')) ?></label>
    <label class="check"><input type="checkbox" name="interests[]" value="membership_class"> <?= e(__('Becoming a member')) ?></label>
    <label class="check"><input type="checkbox" name="interests[]" value="pastoral_contact"> <?= e(__('Talking with a pastor')) ?></label>
  </fieldset>
  <label><?= e(__('Anything you would like us to pray about?')) ?><textarea name="prayer"></textarea></label>
  <label class="check"><input type="checkbox" name="prayer_private" value="1" checked> <?= e(__('Keep my prayer request confidential to the pastoral team')) ?></label>
  <label><?= e(__('Anything else?')) ?><textarea name="message"></textarea></label>
  <div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <label class="check"><input type="checkbox" name="consent" value="1" required> <?= e(__('I agree to %s keeping my details and contacting me, as described in the privacy policy.', setting('church_name'))) ?></label>
  <p class="small"><a href="<?= e(url('/privacy')) ?>" target="_blank"><?= e(__('Read the privacy policy')) ?></a></p>
  <button class="btn btn-primary"><?= e(__('Send')) ?></button>
</form></div>
