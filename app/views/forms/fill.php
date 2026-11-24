<?php $v = fn($k, $d = '') => $old['f_' . $k] ?? $d; ?>
<div class="form-page">
<h1 dir="auto"><?= e($f['title']) ?></h1>
<?php if ($f['intro']): ?><div class="lead" dir="auto"><?= nl2br(e($f['intro'])) ?></div><?php endif; ?>
<?php if ($f['status'] === 'draft'): ?><div class="alert alert-warning" role="status"><?= e(__('Preview: this form is a draft and not open to others yet.')) ?></div><?php endif; ?>
<?php if ($closed && $f['status'] !== 'draft'): ?><div class="card"><p><?= e($closed) ?></p></div>
<?php elseif ($already): ?><div class="card"><p><?= e(__('You filled in this form on %s. Thank you!', fmt_datetime($already['created_at']))) ?></p></div>
<?php else: ?>
<form method="post" action="<?= e(url('/f/' . $f['slug'])) ?>" class="card form" id="form" novalidate><?= csrf_field() ?>
  <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <?php if ($left !== null): ?><p class="small muted"><?= e(__('%d places left', $left)) ?></p><?php endif; ?>
  <?php $tickets = Forms::tickets($f); $paid = Forms::isPaid($f); ?>
  <?php if ($tickets): $qty = (array)($old['qty'] ?? []); $total = 0; foreach ($tickets as $i => $t) { $total += max(0, (int)($qty[$i] ?? 0)) * (int)$t['price_cents']; } ?>
  <fieldset class="fieldset" data-tickets data-currency="<?= e((string)setting('currency', 'AUD')) ?>"><legend><strong><?= e(__('Tickets')) ?></strong></legend>
  <div class="tickets">
  <?php foreach ($tickets as $i => $t): ?><label class="ticket"><span><span dir="auto"><?= e($t['name']) ?></span> <span class="muted">· <?= e((int)$t['price_cents'] ? fmt_money((int)$t['price_cents']) : __('Free')) ?><?= (int)$t['places'] > 1 ? ' · ' . e(__('counts as %d places', (int)$t['places'])) : '' ?></span></span><input type="number" name="qty[<?= $i ?>]" min="0" max="20" step="1" inputmode="numeric" data-price="<?= (int)$t['price_cents'] ?>" value="<?= (int)($qty[$i] ?? 0) ?>"></label><?php endforeach; ?>
  </div>
  <?php if ($paid): ?><p><?= e(__('Total')) ?>: <strong data-total><?= e(fmt_money($total)) ?></strong></p><?php endif; ?>
  </fieldset>
  <?php endif; ?>
  <?php $plans = Forms::plans($f); if ($paid && count($plans) > 1): $chosen = (int)($old['plan'] ?? 1); ?>
  <fieldset class="plans"><legend><?php if (!$tickets): ?><strong><?= e(__('Cost: %s', fmt_money((int)$f['price_cents']))) ?></strong> — <?php endif; ?><?= e(__('How would you like to pay?')) ?></legend>
  <?php foreach ($plans as $count => $each): ?><label class="check"><input type="radio" name="plan" value="<?= $count ?>" <?= $count === $chosen ? 'checked' : '' ?>> <?php if ($tickets): ?><?= e($count === 1 ? __('Pay in full') : __('%d monthly payments', $count)) ?><?= $count > 1 ? ' <span class="muted">(<span data-each="' . $count . '"></span> ' . e(__('each')) . ')</span>' : '' ?><?php else: ?><?= e(Forms::planLabel($count, $each)) ?><?= $count > 1 && $each * $count !== (int)$f['price_cents'] ? ' <span class="small muted">' . e(__('(%s in total, rounded up to whole cents)', fmt_money($each * $count))) . '</span>' : '' ?><?php endif; ?></label><?php endforeach; ?>
  <p class="small muted"><?= e(__('You will pay securely by card after submitting. With monthly payments, the first is taken now and the rest on the same day each month, then they stop.')) ?></p></fieldset>
  <?php elseif ($paid && !$tickets): ?><p><strong><?= e(__('Cost: %s', fmt_money((int)$f['price_cents']))) ?></strong> <span class="small muted"><?= e(__('You will pay securely by card after submitting.')) ?></span></p>
  <?php elseif ($paid): ?><p class="small muted"><?= e(__('You will pay securely by card after submitting.')) ?></p><?php endif; ?>
  <div class="grid-2">
    <label><?= e(__('Your name')) ?> <span class="req" aria-hidden="true">*</span><input name="respondent_name" required maxlength="160" autocomplete="name" value="<?= e($old['respondent_name'] ?? ($me ? person_name($me) : '')) ?>"></label>
    <label><?= e(__('Email')) ?><?= $paid ? ' <span class="req" aria-hidden="true">*</span>' : '' ?><input type="email" name="respondent_email" maxlength="190" autocomplete="email" <?= $paid ? 'required' : '' ?> value="<?= e($old['respondent_email'] ?? ($me['email'] ?? '')) ?>"></label>
  </div>
  <?php foreach ($fields as $fld): $n = 'f_' . $fld['key']; $id = 'q_' . $fld['key']; $req = !empty($fld['required']) || $fld['type'] === 'consent'; $star = $req ? ' <span class="req" aria-hidden="true">*</span>' : ''; $help = !empty($fld['help']) ? '<span class="help" id="' . $id . '_h">' . e($fld['help']) . '</span>' : ''; $desc = !empty($fld['help']) ? ' aria-describedby="' . $id . '_h"' : ''; $ra = $req ? ' required aria-required="true"' : ''; ?>
    <div class="fq"><?php switch ($fld['type']):
      case 'heading': ?><h2 class="h3 form-section" dir="auto"><?= e($fld['label']) ?></h2><?php break;
      case 'paragraph': ?><p dir="auto"><?= nl2br(e($fld['label'])) ?></p><?php break;
      case 'textarea': ?><label for="<?= $id ?>"><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></label><textarea id="<?= $id ?>" name="<?= $n ?>" rows="4"<?= $ra . $desc ?>><?= e((string)$v($fld['key'])) ?></textarea><?= $help ?><?php break;
      case 'select': ?><label for="<?= $id ?>"><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></label><select id="<?= $id ?>" name="<?= $n ?>"<?= $ra . $desc ?>><?= options(array_combine($fld['options'], $fld['options']), (string)$v($fld['key']), __('— Choose —')) ?></select><?= $help ?><?php break;
      case 'radio': case 'yesno': $opts = $fld['type'] === 'yesno' ? ['yes' => __('Yes'), 'no' => __('No')] : array_combine($fld['options'], $fld['options']); ?>
        <fieldset class="fieldset"<?= $desc ?>><legend><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></legend><div class="checks-inline"><?php foreach ($opts as $ov => $ol): ?><label class="check"><input type="radio" name="<?= $n ?>" value="<?= e((string)$ov) ?>" <?= (string)$v($fld['key']) === (string)$ov ? 'checked' : '' ?><?= $ra ?>> <?= e($ol) ?></label><?php endforeach; ?></div><?= $help ?></fieldset><?php break;
      case 'checkboxes': $cur = (array)$v($fld['key'], []); ?>
        <fieldset class="fieldset"<?= $desc ?>><legend><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></legend><div class="checks-inline"><?php foreach ($fld['options'] as $o): ?><label class="check"><input type="checkbox" name="<?= $n ?>[]" value="<?= e($o) ?>" <?= in_array($o, $cur, true) ? 'checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></div><?= $help ?></fieldset><?php break;
      case 'consent': ?><label class="check"><input type="checkbox" name="<?= $n ?>" value="1" <?= $v($fld['key']) ? 'checked' : '' ?> required aria-required="true"<?= $desc ?>> <span><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></span></label><?= $help ?><?php break;
      default: $t = ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$fld['type']] ?? 'text'; ?>
        <label for="<?= $id ?>"><span dir="auto"><?= e($fld['label']) ?></span><?= $star ?></label><input id="<?= $id ?>" type="<?= $t ?>" name="<?= $n ?>" value="<?= e((string)$v($fld['key'])) ?>"<?= $ra . $desc ?><?= $t === 'number' ? ' step="any"' : '' ?>><?= $help ?>
    <?php endswitch; ?></div>
  <?php endforeach; ?>
  <div class="fq"><label class="check privacy-ok"><input type="checkbox" name="privacy_ok" value="1" required> <span><?= e(__('I understand my details will be used for this purpose, as described in the')) ?> <a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener"><?= e(__('privacy policy')) ?></a>.</span></label></div>
  <button class="btn btn-primary"><?= e($paid ? __('Continue to payment') : __('Submit')) ?></button>
  <p class="small muted"><span aria-hidden="true">*</span> <?= e(__('Required')) ?></p>
</form>
<?php endif; ?>
</div>
