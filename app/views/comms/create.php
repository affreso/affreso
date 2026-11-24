<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/comms')) ?>"><?= e(__('Communications')) ?></a></div><h1><?= e(__('New message')) ?></h1></div></div>
<form method="post" action="<?= e(url('/comms')) ?>" class="form" data-confirm="<?= e(__('Send this message now?')) ?>"><?= csrf_field() ?>
<div class="split">
<div class="card">
  <label><?= e(__('Send by')) ?><select name="channel" id="msg-channel"><?= options(['email' => __('Email')] + ($smsEnabled ? ['sms' => __('SMS')] : [])) ?></select></label>
  <label data-email-only><?= e(__('Subject')) ?><input name="subject"></label>
  <label><?= e(__('Message')) ?><textarea name="body" id="msg-body" rows="10" required></textarea><span class="help"><?= e(__('Use {first_name} to personalise.')) ?> <span id="sms-count"></span></span></label>
  <button class="btn btn-primary"><?= e(__('Send')) ?></button>
</div>
<div class="card"><h2 class="h3"><?= e(__('Who should receive it?')) ?></h2>
  <?php foreach (['all' => __('Everyone'), 'members' => __('Members'), 'regulars' => __('Members & regular attenders'), 'visitors' => __('Visitors'), 'volunteers' => __('All volunteers'), 'groups' => __('Specific groups or teams'), 'people' => __('Specific people')] as $k => $l): ?>
    <label class="check"><input type="radio" name="audience" value="<?= e($k) ?>" <?= ($preselect ? 'groups' : 'all') === $k ? 'checked' : '' ?>> <?= e($l) ?></label>
  <?php endforeach; ?>
  <label><?= e(__('Groups')) ?><select name="group_ids[]" multiple size="5"><?= options($groups, $preselect) ?></select></label>
  <label><?= e(__('People')) ?><select name="person_ids[]" multiple size="6"><?= options(people_options()) ?></select></label>
  <label class="check"><input type="checkbox" name="adults_only" value="1" checked> <?= e(__('Adults only (exclude children in households)')) ?></label>
  <p class="small muted"><?= e(__('Only people who have consented to contact and have not opted out of this channel will receive it.')) ?></p>
</div>
</div>
</form>
