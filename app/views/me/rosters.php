<div class="page-head"><div><h1><?= e(__('My rosters')) ?></h1><p><?= $positions ? e(__('You serve as: %s', implode(', ', array_map(fn($p) => $p['name'] . ' (' . $p['group_name'] . ')', $positions)))) : e(__('You are not approved for any serving positions yet.')) ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/me/availability')) ?>"><?= e(__('Set unavailable dates')) ?></a><a class="btn" href="<?= e(url('/me/volunteer')) ?>"><?= e(__('Volunteer for a team')) ?></a></div></div>
<?php if ($openSwaps): ?>
<div class="card"><h2><?= e(__('Can you help? These people need cover')) ?></h2>
<ul class="list-plain"><?php foreach ($openSwaps as $s): ?><li><strong><?= e(fmt_date($s['service_date'], 'D j M')) ?></strong> — <?= e($s['position']) ?> · <?= e($s['title']) ?><?= $s['reason'] ? ' <span class="small muted">(' . e($s['reason']) . ')</span>' : '' ?>
<form class="inline-form" method="post" action="<?= e(url('/me/swaps/' . $s['id'] . '/take')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary"><?= e(__("I'll cover it")) ?></button></form></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (!$upcoming): ?><div class="card"><p class="empty"><?= e(__('Nothing coming up. When you are rostered you will see it here and get an email or text.')) ?></p></div><?php endif; ?>
<?php foreach ($upcoming as $a): $others = DB::all('SELECT p.id, p.first_name, p.last_name, p.preferred_name FROM {p}volunteer_positions vp JOIN {p}people p ON p.id = vp.person_id WHERE vp.position_id = ? AND vp.approved_at IS NOT NULL AND p.id <> ? AND p.archived_at IS NULL ORDER BY p.first_name', [$a['position_id'], person_id()]); ?>
<div class="card event-card">
  <div class="datebox"><div class="m"><?= e(fmt_date($a['service_date'], 'M')) ?></div><div class="d"><?= e(fmt_date($a['service_date'], 'j')) ?></div><div class="m"><?= e(fmt_date($a['service_date'], 'D')) ?></div></div>
  <div>
    <h2 class="h3" style="margin-top:0"><?= e($a['position']) ?> · <?= e($a['group_name']) ?> <?= badge($a['status']) ?></h2>
    <p class="small muted"><?= e($a['title']) ?><?= $a['start_time'] ? ' · ' . e(fmt_time($a['start_time'])) . '–' . e(fmt_time($a['end_time'])) : '' ?></p>
    <?php if ($a['notes']): ?><p class="small"><?= nl2br(e($a['notes'])) ?></p><?php endif; ?>
    <div class="actions">
      <?php if (in_array($a['status'], ['pending', 'declined'], true)): ?>
        <form method="post" action="<?= e(url('/me/rosters/' . $a['id'] . '/respond')) ?>"><?= csrf_field() ?><button class="btn btn-primary" name="answer" value="accept"><?= e(__('Accept')) ?></button></form>
      <?php endif; ?>
      <?php if ($a['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('/me/rosters/' . $a['id'] . '/respond')) ?>"><?= csrf_field() ?><button class="btn" name="answer" value="decline"><?= e(__('Decline')) ?></button></form>
      <?php endif; ?>
      <?php if (in_array($a['status'], ['pending', 'accepted'], true)): ?>
      <details><summary class="btn"><?= e(__('Request a swap')) ?></summary>
        <form method="post" action="<?= e(url('/me/rosters/' . $a['id'] . '/swap')) ?>" class="form" style="margin-top:8px"><?= csrf_field() ?>
          <label><?= e(__('Swap with (optional)')) ?><select name="replacement_id"><?= options(array_combine(array_column($others, 'id'), array_map('person_name', $others)) ?: [], null, __('Ask the team to cover')) ?></select></label>
          <label><?= e(__('Reason (optional)')) ?><input name="reason"></label>
          <button class="btn btn-sm"><?= e(__('Send swap request')) ?></button>
        </form></details>
      <?php endif; ?>
      <?php if ($a['status'] === 'swap_requested'): ?><span class="small muted"><?= e(__('Swap requested — you remain rostered until someone covers.')) ?></span><?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if ($past): ?><div class="card"><h2 class="h3"><?= e(__('Recently served')) ?></h2><ul class="list-plain small"><?php foreach ($past as $p): ?><li><?= e(fmt_date($p['service_date'])) ?> — <?= e($p['position']) ?> · <?= e($p['title']) ?> <?= badge($p['status']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
