<div class="page-head"><div><h1><?= e(__('Volunteers')) ?></h1><p><?= e(__('Applications, onboarding and who is approved to serve in each position.')) ?></p></div></div>
<div class="card">
  <div class="card-head"><h2><?= e(__('Applications')) ?></h2>
  <div class="tabs" style="margin:0;border:0"><?php foreach (['active' => __('In progress'), 'approved' => __('Approved'), 'declined' => __('Declined'), 'all' => __('All')] as $k => $l): ?><a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(url('/volunteers', ['status' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
  <?php if (!$apps): ?><p class="empty"><?= e(__('No applications.')) ?></p><?php else: ?>
  <table><thead><tr><th><?= e(__('Applicant')) ?></th><th><?= e(__('Positions')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Received')) ?></th></tr></thead><tbody>
  <?php foreach ($apps as $a): ?><tr><td><a href="<?= e(url('/volunteers/applications/' . $a['id'])) ?>"><?= e(person_name($a)) ?></a></td><td class="small"><?= e(implode(', ', array_map(fn($id) => $posNames[(int)$id] ?? '?', array_filter(explode(',', (string)$a['position_ids']))))) ?></td><td><?= badge($a['status']) ?></td><td class="small"><?= e(fmt_date($a['created_at'])) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</div>

<div class="split">
<div>
<h2><?= e(__('Approved volunteers by position')) ?><?= $filterPerson ? ' <a class="small" href="' . e(url('/volunteers')) . '">(' . e(__('show everyone')) . ')</a>' : '' ?></h2>
<?php foreach ($positions as $po): if ($filterPerson && !$po['people']) continue; ?>
<div class="card">
  <div class="card-head"><h2 class="h3"><?= e($po['group_name']) ?> — <?= e($po['name']) ?></h2><?= (int)$po['requires_wwcc'] ? '<span class="badge badge-gold">WWCC</span>' : '' ?></div>
  <?php if (!$po['people']): ?><p class="empty"><?= e(__('No approved volunteers yet.')) ?></p><?php endif; ?>
  <ul class="list-plain">
  <?php foreach ($po['people'] as $pp): ?>
    <li><?= e(person_name($pp)) ?> <?= $pp['preference'] === 'preferred' ? '<span class="badge badge-gold">' . e(__('Preferred')) . '</span>' : '' ?> <?= $pp['gaps'] ? badge('pending') : badge('active') ?>
      <form class="inline-form" method="post" action="<?= e(url('/volunteers/positions/remove')) ?>" data-confirm="<?= e(__('Remove from this position?')) ?>"><?= csrf_field() ?><input type="hidden" name="person_id" value="<?= (int)$pp['id'] ?>"><input type="hidden" name="position_id" value="<?= (int)$po['id'] ?>"><button class="btn-link small"><?= e(__('remove')) ?></button></form>
      <?php if ($pp['gaps']): ?><ul class="warn-list"><?php foreach ($pp['gaps'] as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>
</div>
<div class="card">
  <h2 class="h3"><?= e(__('Approve someone for a position')) ?></h2>
  <form method="post" action="<?= e(url('/volunteers/positions')) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Person')) ?><select name="person_id" required><?= options(people_options_scoped(), $filterPerson, '—') ?></select></label>
    <label><?= e(__('Position')) ?><select name="position_id" required><?= options(array_combine(array_column($positions, 'id'), array_map(fn($p) => $p['group_name'] . ' — ' . $p['name'], $positions)), null, '—') ?></select></label>
    <label><?= e(__('Preference')) ?><select name="preference"><?= options(['willing' => __('Willing'), 'preferred' => __('Preferred role')]) ?></select></label>
    <label class="check"><input type="checkbox" name="assign_training" value="1" checked> <?= e(__('Automatically assign any missing training')) ?></label>
    <button class="btn btn-primary"><?= e(__('Approve')) ?></button>
  </form>
  <p class="small muted"><?= e(__('People cannot be rostered until required training and checks are complete — Affreso checks this automatically.')) ?></p>
</div>
</div>
