<div class="page-head"><div><h1><?= e(__('Rosters')) ?></h1><p><?= e(__('Build, fill and publish serving rosters. Volunteers accept or decline from their phones.')) ?></p></div>
<div class="actions"><?php if ($swaps): ?><a class="btn" href="<?= e(url('/rosters/swaps')) ?>"><?= e(__('Swap requests')) ?> <span class="badge badge-amber"><?= (int)$swaps ?></span></a><?php endif; ?><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(url('/rosters/new')) ?>">+ <?= e(__('New roster')) ?></a><?php endif; ?></div></div>
<form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="r" value="/rosters">
  <label><?= e(__('Team')) ?><select name="group"><?= options($groups, $group, __('All teams')) ?></select></label>
  <label><?= e(__('From')) ?><input type="date" name="from" value="<?= e($from) ?>"></label>
  <button class="btn"><?= e(__('Show')) ?></button>
</form>
<div class="card table-wrap">
<table><thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Roster')) ?></th><th><?= e(__('Team')) ?></th><th><?= e(__('Filled')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
<?php $last = null; foreach ($rows as $r): ?>
<tr><td class="nowrap"><?= $last !== $r['service_date'] ? '<strong>' . e(fmt_date($r['service_date'], 'D j M')) . '</strong>' : '' ?> <span class="small muted"><?= e(fmt_time($r['start_time'])) ?></span></td>
<td><a href="<?= e(url('/rosters/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td><td><?= e($r['group_name']) ?></td>
<td><?= (int)$r['accepted'] ?>/<?= (int)$r['slots'] ?> <?= e(__('confirmed')) ?><?= (int)$r['unfilled'] ? ' <span class="badge badge-red">' . e(__('%d unfilled', (int)$r['unfilled'])) . '</span>' : '' ?></td><td><?= badge($r['status']) ?></td></tr>
<?php $last = $r['service_date']; endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= e(__('No upcoming rosters.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
