<?php $need = max(2, (int)ceil(count($kids) / max(1, (int)$k['ratio']))); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/checkin/sessions/' . $s['id'])) ?>"><?= e($s['title']) ?></a></div><h1><?= e($k['name']) ?></h1><p><?= e(__('%d children · %d leaders on duty', count($kids), count($leaders))) ?></p></div></div>
<?php if ($kids && count($leaders) < $need): ?><div class="alert alert-error"><?= e(__('Not enough leaders: this room needs at least %d adults with current checks.', $need)) ?></div><?php endif; ?>
<div class="split"><div class="card table-wrap"><table><thead><tr><th><?= e(__('Child')) ?></th><th><?= e(__('Care information')) ?></th><th><?= e(__('Photos')) ?></th></tr></thead><tbody>
<?php foreach ($kids as $c): $i = $c['info']; $has = trim($i['allergies'] . $i['medical'] . $i['needs']) !== ''; ?>
<tr><td><strong><?= e(person_name($c)) ?></strong> <span class="small muted"><?= $c['birthdate'] ? e(__('age %d', age_on($c['birthdate']))) : '' ?></span></td>
<td class="small"><?php if (!$has): ?>—<?php elseif ($showMedical): ?><?= $i['allergies'] !== '' ? '<div><span class="badge badge-red">' . e(__('Allergy')) . '</span> ' . e($i['allergies']) . '</div>' : '' ?><?= $i['medical'] !== '' ? '<div><span class="badge badge-amber">' . e(__('Medical')) . '</span> ' . e($i['medical']) . '</div>' : '' ?><?= $i['needs'] !== '' ? '<div><span class="badge badge-blue">' . e(__('Needs')) . '</span> ' . e($i['needs']) . '</div>' : '' ?><?= $i['emergency'] !== '' ? '<div class="muted">' . e(__('Emergency: %s', $i['emergency'])) . '</div>' : '' ?>
<?php else: ?><span class="badge badge-red">⚠ <?= e(__('Care alert — ask the room leader')) ?></span><?php endif; ?></td>
<td><?= $c['photo'] ? '✓' : '<span class="badge badge-grey">' . e(__('No photos')) . '</span>' ?></td></tr>
<?php endforeach; ?>
<?php if (!$kids): ?><tr><td colspan="3" class="empty"><?= e(__('No children in this room.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="card"><h2 class="h3"><?= e(__('Leaders')) ?></h2><ul class="list-plain"><?php foreach ($leaders as $l): ?><li><?= e(person_name($l)) ?> <span class="small muted"><?= e(fmt_time($l['checked_in_at'])) ?></span></li><?php endforeach; ?></ul><?php if (!$leaders): ?><p class="empty"><?= e(__('No leaders checked in.')) ?></p><?php endif; ?></div></div>
