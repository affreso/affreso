<div class="page-head"><div><h1><?= e(__('Safeguarding')) ?></h1><p><?= e(__('Working With Children Checks and other clearances. Verify each check with the issuing state or territory agency before marking it verified.')) ?></p></div></div>
<?php if ($gaps): ?>
<div class="card" style="border-left:4px solid var(--red)">
  <h2><?= e(__('Action needed: %d volunteer(s) in child-related positions without a current verified check', count($gaps))) ?></h2>
  <p class="small muted"><?= e(__('They are automatically excluded from rosters for these positions until a check is verified.')) ?></p>
  <ul class="list-plain"><?php foreach ($gaps as $g): ?><li><a href="<?= e(url('/safeguarding', ['person' => $g['id']])) ?>"><?= e(person_name($g)) ?></a> <span class="small muted"><?= e($g['positions']) ?></span></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<div class="split">
<div class="card table-wrap">
  <div class="tabs"><?php foreach (['all' => __('All'), 'expiring' => __('Expiring soon'), 'expired' => __('Expired'), 'pending' => __('Pending verification')] as $k => $l): ?><a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= e(url('/safeguarding', ['filter' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
  <table><thead><tr><th><?= e(__('Person')) ?></th><th><?= e(__('Check')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Verified')) ?></th><th><?= e(__('Expires')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
  <?php foreach ($rows as $c): $expired = $c['expires_on'] && $c['expires_on'] < date('Y-m-d'); ?>
    <tr><td><a href="<?= e(url('/safeguarding', ['person' => $c['person_id']])) ?>"><?= e(person_name($c)) ?></a></td><td><?= e($c['check_type']) ?> <?= e($c['jurisdiction']) ?><div class="small muted"><?= $c['reference_enc'] ? '•••• ' . e(__('on file')) : e(__('no number')) ?></div></td>
    <td><?= badge($expired && $c['status'] === 'verified' ? 'expired' : $c['status']) ?></td><td class="small"><?= e(fmt_date($c['verified_on'])) ?></td><td class="small"><?= e(fmt_date($c['expires_on'])) ?></td>
    <td class="actions-cell"><?php if (can('safeguarding.manage')): ?><a class="btn btn-sm" href="<?= e(url('/safeguarding', ['person' => $c['person_id'], 'edit' => $c['id']])) ?>"><?= e(__('Edit')) ?></a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty"><?= e(__('No checks found.')) ?></td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php if (can('safeguarding.manage')): $c = $edit ?? []; ?>
<div class="card">
  <h2 class="h3"><?= e($edit ? __('Edit check') : __('Record a check')) ?></h2>
  <form method="post" action="<?= e(url($edit ? '/safeguarding/' . $edit['id'] : '/safeguarding')) ?>" class="form"><?= csrf_field() ?>
    <?php if (!$edit): ?><label><?= e(__('Person')) ?><select name="person_id" required><?= options(people_options(), $person, '—') ?></select></label><?php endif; ?>
    <label><?= e(__('Type')) ?><select name="check_type"><?= options(SafeguardingController::checkTypes(), $c['check_type'] ?? 'WWCC') ?></select></label>
    <label><?= e(__('Issuing state / territory')) ?><select name="jurisdiction"><?= options(au_states(), $c['jurisdiction'] ?? setting('church_state'), '—') ?></select></label>
    <label><?= e(__('Clearance number')) ?><input name="reference" value="<?= e($c['reference'] ?? '') ?>" autocomplete="off"><span class="help"><?= e(__('Stored encrypted. Leave blank to keep the existing number.')) ?></span></label>
    <label><?= e(__('Status')) ?><select name="status"><?= options(['pending' => __('Pending verification'), 'verified' => __('Verified with agency'), 'expired' => __('Expired'), 'barred' => __('Barred / negative notice'), 'withdrawn' => __('Withdrawn')], $c['status'] ?? 'pending') ?></select></label>
    <div class="grid-2"><label><?= e(__('Verified on')) ?><input type="date" name="verified_on" value="<?= e($c['verified_on'] ?? '') ?>"></label><label><?= e(__('Expires on')) ?><input type="date" name="expires_on" value="<?= e($c['expires_on'] ?? '') ?>"></label></div>
    <label><?= e(__('Notes')) ?><textarea name="notes"><?= e($c['notes'] ?? '') ?></textarea></label>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
    <?php if ($edit): ?><a class="btn" href="<?= e(url('/safeguarding')) ?>"><?= e(__('Cancel')) ?></a><?php endif; ?>
  </form>
  <p class="small muted"><?= e(__('Requirements differ between states and territories and change over time. Confirm current rules with your state screening agency and your denomination.')) ?></p>
</div>
<?php endif; ?>
</div>
