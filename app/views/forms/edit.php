<?php $link = url('/f/' . $f['slug'], [], true); $types = Forms::types(); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/forms')) ?>"><?= e(__('Forms')) ?></a></div><h1><?= e($f['title']) ?> <?= badge($f['status']) ?></h1></div>
<div class="actions"><a class="btn" href="<?= e(url('/f/' . $f['slug'])) ?>" target="_blank" rel="noopener"><?= e($f['status'] === 'draft' ? __('Preview') : __('Open form')) ?></a>
<a class="btn" href="<?= e(url('/forms/' . $f['id'] . '/responses')) ?>"><?= e(__('Responses (%d)', $responses)) ?></a>
<form class="inline-form" method="post" action="<?= e(url('/forms/' . $f['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Duplicate')) ?></button></form></div></div>
<?php if ($f['status'] === 'open'): ?><div class="card callout"><strong><?= e(__('Share this link')) ?></strong> <code class="copyable"><?= e($link) ?></code> <span class="small muted"><?= e(__('Put it on your website, in the newsletter or in an email.')) ?></span></div><?php endif; ?>
<div class="split" style="grid-template-columns:3fr 2fr">
<div>
<div class="card" id="questions"><h2 class="h3"><?= e(__('Questions')) ?></h2>
<p class="small muted"><?= e(__('Everyone is always asked for their name and email, so you don\'t need to add those.')) ?></p>
<?php foreach ($fields as $i => $fld): ?>
  <div class="form-q">
    <div class="form-q-head"><span class="badge badge-grey"><?= e($types[$fld['type']] ?? $fld['type']) ?></span>
      <strong><?= e(mb_strimwidth($fld['label'], 0, 120, '…')) ?></strong><?= !empty($fld['required']) ? ' <span class="small muted">' . e(__('required')) . '</span>' : '' ?><?php $pt = !empty($fld['profile']) ? (Forms::profileTargets()[$fld['profile']][0] ?? null) : null; if ($pt): ?> <span class="badge badge-blue"><?= e(__('Saves to: %s', $pt)) ?></span><?php endif; ?>
      <?php if ($fld['options']): ?><div class="small muted"><?= e(implode(' · ', $fld['options'])) ?></div><?php endif; ?></div>
    <div class="form-q-actions">
      <form class="inline-form" method="post" action="<?= e(url('/forms/' . $f['id'] . '/fields/' . $fld['key'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm" <?= $i === 0 ? 'disabled' : '' ?> aria-label="<?= e(__('Move up')) ?>">↑</button></form>
      <form class="inline-form" method="post" action="<?= e(url('/forms/' . $f['id'] . '/fields/' . $fld['key'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm" <?= $i === count($fields) - 1 ? 'disabled' : '' ?> aria-label="<?= e(__('Move down')) ?>">↓</button></form>
      <form class="inline-form" method="post" action="<?= e(url('/forms/' . $f['id'] . '/fields/' . $fld['key'] . '/delete')) ?>" data-confirm="<?= e(__('Remove this question?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Remove')) ?></button></form>
    </div>
    <details class="form-q-edit"><summary><?= e(__('Edit')) ?></summary>
      <form method="post" action="<?= e(url('/forms/' . $f['id'] . '/fields/' . $fld['key'])) ?>" class="form"><?= csrf_field() ?><?php partial('forms/_field_inputs', ['fld' => $fld]); ?><button class="btn btn-sm btn-primary"><?= e(__('Save question')) ?></button></form>
    </details>
  </div>
<?php endforeach; ?>
<?php if (!$fields): ?><p class="empty"><?= e(__('No questions yet. Add the first one below.')) ?></p><?php endif; ?>
<h2 class="h3"><?= e(__('Add a question')) ?></h2>
<form method="post" action="<?= e(url('/forms/' . $f['id'] . '/fields')) ?>" class="form"><?= csrf_field() ?><?php partial('forms/_field_inputs', ['fld' => null]); ?><button class="btn btn-primary"><?= e(__('Add question')) ?></button></form>
</div>
</div>
<div>
<div class="card"><h2 class="h3"><?= e(__('Settings')) ?></h2>
<form method="post" action="<?= e(url('/forms/' . $f['id'])) ?>" class="form"><?= csrf_field() ?>
  <label><?= e(__('Title')) ?><input name="title" required maxlength="160" value="<?= e($f['title']) ?>"></label>
  <label><?= e(__('Introduction')) ?><textarea name="intro" rows="3"><?= e((string)$f['intro']) ?></textarea></label>
  <label><?= e(__('Status')) ?><select name="status"><?= options(['draft' => __('Draft — only form managers can preview'), 'open' => __('Open for responses'), 'closed' => __('Closed')], $f['status']) ?></select></label>
  <label><?= e(__('Who can fill it in')) ?><select name="audience"><?= options(['public' => __('Anyone with the link'), 'members' => __('Signed-in members only')], $f['audience']) ?></select></label>
  <div class="grid-2"><label><?= e(__('Opens')) ?><input type="datetime-local" name="opens_at" value="<?= e($f['opens_at'] ? date('Y-m-d\TH:i', strtotime($f['opens_at'])) : '') ?>"></label>
  <label><?= e(__('Closes')) ?><input type="datetime-local" name="closes_at" value="<?= e($f['closes_at'] ? date('Y-m-d\TH:i', strtotime($f['closes_at'])) : '') ?>"></label></div>
  <div class="grid-2"><label><?= e(__('Limit (places)')) ?><input type="number" min="1" name="capacity" value="<?= e((string)$f['capacity']) ?>" placeholder="<?= e(__('No limit')) ?>"></label>
  <label><?= e(__('Price')) ?><input name="price" inputmode="decimal" value="<?= $f['price_cents'] ? e(number_format($f['price_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="<?= e(__('Free')) ?>"><span class="help"><?= e(__('Paid by card through Stripe when they submit.')) ?></span></label></div>
  <?php $tk = Forms::tickets($f); ?>
  <details class="fieldset"<?= $tk ? ' open' : '' ?>><summary><?= e(__('Ticket types (optional)')) ?></summary>
  <p class="small muted"><?= e(__('For family or group bookings: people choose how many of each ticket they want. A family ticket can count as several places towards the limit and on the event list. When you add ticket types, the single price above isn\'t used.')) ?></p>
  <div class="table-wrap"><table class="ticket-editor"><thead><tr><th scope="col"><?= e(__('Ticket')) ?></th><th scope="col"><?= e(__('Price')) ?></th><th scope="col"><?= e(__('Places each')) ?></th></tr></thead><tbody>
  <?php for ($i = 0; $i < max(4, count($tk) + 2) && $i < 8; $i++): $t = $tk[$i] ?? ['name' => '', 'price_cents' => 0, 'places' => 1]; ?>
  <tr><td><input name="tickets[<?= $i ?>][name]" maxlength="60" value="<?= e($t['name']) ?>" aria-label="<?= e(__('Ticket')) ?> <?= $i + 1 ?>" placeholder="<?= e($i === 0 ? __('e.g. Adult') : ($i === 1 ? __('e.g. Child') : ($i === 2 ? __('e.g. Family (2 adults, 3 children)') : ''))) ?>"></td>
  <td><input name="tickets[<?= $i ?>][price]" inputmode="decimal" value="<?= $t['name'] !== '' ? e(number_format((int)$t['price_cents'] / 100, 2, '.', '')) : '' ?>" aria-label="<?= e(__('Price')) ?> <?= $i + 1 ?>" placeholder="0.00"></td>
  <td><input type="number" min="1" max="20" name="tickets[<?= $i ?>][places]" value="<?= (int)$t['places'] ?>" aria-label="<?= e(__('Places each')) ?> <?= $i + 1 ?>"></td></tr>
  <?php endfor; ?>
  </tbody></table></div></details>
  <label><?= e(__('Paying in instalments')) ?><select name="instalments"><?= options([1 => __('Pay in full only'), 2 => __('Allow up to %d monthly payments', 2), 3 => __('Allow up to %d monthly payments', 3), 4 => __('Allow up to %d monthly payments', 4), 6 => __('Allow up to %d monthly payments', 6), 10 => __('Allow up to %d monthly payments', 10), 12 => __('Allow up to %d monthly payments', 12)], (int)$f['instalments']) ?></select><span class="help"><?= e(__('People choose to pay in full or spread the cost. Stripe takes the first payment straight away and the rest monthly, then stops by itself.')) ?></span></label>
  <label class="check"><input type="checkbox" name="profile_auto" value="1" <?= $f['profile_auto'] ? 'checked' : '' ?>> <?= e(__('When signed-in people fill this in, update their own profile with answers linked to profile fields')) ?></label>
  <label class="check"><input type="checkbox" name="one_per_person" value="1" <?= $f['one_per_person'] ? 'checked' : '' ?>> <?= e(__('Signed-in people can respond only once')) ?></label>
  <label><?= e(__('Web address')) ?><span class="inline-input"><span class="small muted">/f/</span><input name="slug" maxlength="60" value="<?= e($f['slug']) ?>" pattern="[a-z0-9\-]+"></span></label>
  <label><?= e(__('Linked event (optional)')) ?><select name="event_id"><?= options($events, $f['event_id'], __('— None —')) ?></select></label>
  <?= Campus::field($f['campus_id'] ? (int)$f['campus_id'] : null) ?>
  <label><?= e(__('Email new responses to')) ?><input name="notify_emails" value="<?= e((string)$f['notify_emails']) ?>" placeholder="office@church.org.au, youth@church.org.au"><span class="help"><?= e(__('They get a link, not the answers, so private details stay in Affreso.')) ?></span></label>
  <label><?= e(__('Thank-you message')) ?><textarea name="confirmation" rows="3" placeholder="<?= e(__('Thank you — we have received your response.')) ?>"><?= e((string)$f['confirmation']) ?></textarea></label>
  <button class="btn btn-primary"><?= e(__('Save settings')) ?></button>
</form></div>
<div class="card"><h2 class="h3"><?= e(__('Delete form')) ?></h2>
<p class="small muted"><?= e(__('Deletes the form and all %d responses. This cannot be undone.', $responses)) ?></p>
<form method="post" action="<?= e(url('/forms/' . $f['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this form and all its responses?')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm"><?= e(__('Delete form')) ?></button></form></div>
</div>
</div>
