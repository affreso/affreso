<?php $sid = (int)$s['id']; $open = $s['status'] === 'open'; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/checkin')) ?>"><?= e(__("Children's check-in")) ?></a></div><h1><?= e($s['title']) ?> <?= badge($s['status']) ?></h1><p><?= e(fmt_date($s['session_date'], 'l j F Y')) ?></p></div>
<?php if ($open): ?><form method="post" action="<?= e(url('/checkin/sessions/' . $sid . '/close')) ?>" data-confirm="<?= e(__('Close this session?')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Close session')) ?></button></form><?php endif; ?></div>

<div class="grid-4" style="margin-bottom:16px">
<?php foreach ($classStats as $c): $need = (int)ceil((int)$c['kids'] / max(1, (int)$c['ratio'])); $short = (int)$c['kids'] > 0 && (int)$c['leaders'] < max(2, $need); ?>
  <a class="stat<?= $short ? ' warn' : '' ?>" href="<?= e(url('/checkin/sessions/' . $sid . '/room/' . $c['id'])) ?>"><div class="num"><?= (int)$c['kids'] ?></div><div class="lbl"><?= e($c['name']) ?> · <?= e(__('%d leader(s)', (int)$c['leaders'])) ?><?= $short ? ' · ' . e(__('needs %d', max(2, $need))) : '' ?></div></a>
<?php endforeach; ?>
</div>
<p class="small muted"><?= e(__('Ratios follow your settings, with a minimum of two adults in any room with children (two-adult rule).')) ?></p>

<div class="split">
<div>
<?php if ($open): ?>
<div class="card"><h2><?= e(__('Check in')) ?></h2>
  <form method="get" action="<?= e(base_path() . '/index.php') ?>" class="form-row"><input type="hidden" name="r" value="/checkin/sessions/<?= $sid ?>"><label><?= e(__('Family surname, name or mobile')) ?><input type="search" name="q" value="<?= e($q) ?>" autofocus autocomplete="off"></label><button class="btn btn-primary"><?= e(__('Find')) ?></button></form>
  <?php foreach ($families as $f): ?>
  <form method="post" action="<?= e(url('/checkin/sessions/' . $sid . '/in')) ?>" class="card form" style="margin-top:12px"><?= csrf_field() ?>
    <h2 class="h3" style="margin-top:0"><?= e($f['name']) ?></h2>
    <?php foreach ($f['kids'] as $k): ?>
      <div class="form-row" style="margin-bottom:8px">
        <label class="check" style="flex:2"><input type="checkbox" name="child_ids[]" value="<?= (int)$k['id'] ?>" <?= $k['in'] ? 'disabled' : 'checked' ?>> <strong><?= e(person_name($k)) ?></strong> <span class="muted"><?= $k['birthdate'] ? e(__('age %d', age_on($k['birthdate']))) : '' ?></span> <?= $k['alert'] ? '<span class="badge badge-red">⚠ ' . e(__('Alert')) . '</span>' : '' ?> <?= $k['in'] ? badge('registered') . ' ' . e(__('already in')) : '' ?></label>
        <label><select name="class_<?= (int)$k['id'] ?>" aria-label="<?= e(__('Class')) ?>"><?= options($classes, $k['suggest'], __('Choose class')) ?></select></label>
      </div>
    <?php endforeach; ?>
    <label><?= e(__('Dropped off by')) ?><select name="dropped_off_by"><?= options(array_combine(array_map('person_name', $f['adults']), array_map('person_name', $f['adults']))) ?><option value="Other"><?= e(__('Someone else')) ?></option></select></label>
    <button class="btn btn-primary"><?= e(__('Check in & print labels')) ?></button>
  </form>
  <?php endforeach; ?>
  <?php if ($q !== '' && !$families && !$expected): ?><p class="empty"><?= e(__('No family with children found. Add the family in Members & families first.')) ?></p><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($expected): ?><div class="card"><h2 class="h3"><?= e(__('Pre-checked in — waiting to arrive')) ?></h2>
  <p class="small muted"><?= e(__('Families who checked in on their phone. Confirm when they arrive to print labels. Search by their arrival code above.')) ?></p>
  <?php foreach ($expected as $code => $pcs): ?><div class="sync-row" style="grid-template-columns:1fr auto;align-items:center">
    <div><strong><?= e($code) ?></strong> · <?= e(implode(', ', array_map(fn($p) => person_name($p) . ($p['class'] ? ' (' . $p['class'] . ')' : ''), $pcs))) ?><?= $pcs[0]['dropped_off_by'] ? '<div class="small muted">' . e(__('Dropped off by %s', $pcs[0]['dropped_off_by'])) . '</div>' : '' ?></div>
    <form method="post" action="<?= e(url('/checkin/sessions/' . $s['id'] . '/arrive')) ?>"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($code) ?>"><button class="btn btn-primary btn-sm"><?= e(__('Arrived — print labels')) ?></button></form>
  </div><?php endforeach; ?>
</div><?php endif; ?>
<div class="card table-wrap"><h2><?= e(__('Children')) ?></h2>
<table><thead><tr><th><?= e(__('Child')) ?></th><th><?= e(__('Class')) ?></th><th><?= e(__('In')) ?></th><th><?= e(__('Check out')) ?></th></tr></thead><tbody>
<?php foreach ($present as $p): $out = $p['checked_out_at']; ?>
<tr><td><strong><?= e(person_name($p)) ?></strong> <?= $p['alert'] ? '<span class="badge badge-red">⚠</span>' : '' ?><div class="small muted"><?= e($p['dropped_off_by']) ?></div></td><td><?= e($p['class']) ?></td><td class="small"><?= e(fmt_time($p['checked_in_at'])) ?></td>
<td><?php if ($out): ?><span class="small"><?= badge('done') ?> <?= e(fmt_time($out)) ?> · <?= e($p['collected_by']) ?></span>
<?php else: $coll = CheckinController::collectors((int)$p['child_id']); ?>
  <form method="post" action="<?= e(url('/checkin/out/' . $p['id'])) ?>" class="form-row"><?= csrf_field() ?>
    <label><input name="code" required maxlength="4" placeholder="<?= e(__('Code')) ?>" style="max-width:90px;text-transform:uppercase" aria-label="<?= e(__('Security code from parent tag')) ?>" autocomplete="off"></label>
    <label><select name="collector" aria-label="<?= e(__('Collected by')) ?>"><?= options($coll, null, __('Collected by…')) ?></select></label>
    <details><summary class="small"><?= e(__('Someone else')) ?></summary><input name="collector_name" placeholder="<?= e(__('Name')) ?>" aria-label="<?= e(__('Name')) ?>"><input name="override_reason" placeholder="<?= e(__('Leader approval / reason')) ?>" aria-label="<?= e(__('Reason')) ?>"></details>
    <button class="btn btn-sm"><?= e(__('Release')) ?></button>
  </form>
<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$present): ?><tr><td colspan="4" class="empty"><?= e(__('No children checked in yet.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
</div>
<div class="card"><h2 class="h3"><?= e(__('Leaders on duty')) ?></h2>
  <p class="small muted"><?= e(__('Only people with a verified, current Working With Children Check can be counted.')) ?></p>
  <?php if ($open): ?><form method="post" action="<?= e(url('/checkin/sessions/' . $sid . '/leaders')) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Leader')) ?><select name="person_id" required><?= options(people_options_scoped(), person_id(), '—') ?></select></label>
    <label><?= e(__('Class')) ?><select name="class_id" required><?= options($classes, null, '—') ?></select></label>
    <button class="btn btn-sm"><?= e(__('Check in leader')) ?></button></form><?php endif; ?>
</div>
</div>
