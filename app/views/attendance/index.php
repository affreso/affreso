<?php
$f = $edit ?: ['count_date' => $date, 'service_label' => $services[0]['title'] ?? __('Sunday service'), 'adults' => '', 'youth' => '', 'children' => $kids ?: '', 'online' => '', 'visitors' => '', 'notes' => '', 'campus_id' => $campus, 'event_id' => $services[0]['id'] ?? null];
$s = $summary;
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p><?= e(__('Headcounts for each service. Children checked in are counted for you.')) ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/attendance/report')) ?>"><?= e(__('Trends & report')) ?></a></div></div>
<div class="grid-4" style="margin-bottom:18px">
  <div class="stat"><div class="num"><?= $s['last'] ? (int)$s['last']['total'] : '—' ?></div><div class="lbl"><?= e($s['last'] ? __('Week of %s', fmt_date($s['last']['week'], 'j M')) : __('No counts yet')) ?></div></div>
  <div class="stat"><div class="num"><?= $s['avg4'] ?? '—' ?></div><div class="lbl"><?= e(__('Average, last 4 weeks')) ?></div></div>
  <div class="stat"><div class="num"><?= $s['change'] === null ? '—' : (($s['change'] > 0 ? '+' : '') . (int)$s['change'] . '%') ?></div><div class="lbl"><?= e(__('Compared with the 4 weeks before')) ?></div></div>
  <div class="stat"><div class="num"><?= (int)$s['visitors4'] ?></div><div class="lbl"><?= e(__('First-time visitors, last 4 weeks')) ?></div></div>
</div>
<div class="split">
<div class="card">
  <h2 class="h3"><?= e($edit ? __('Edit count') : __('Record a count')) ?></h2>
  <?php if (!$edit): ?><form method="get" action="<?= e(url('/attendance')) ?>" class="form-row"><input type="hidden" name="r" value="/attendance"><label><?= e(__('Service date')) ?><input type="date" name="date" value="<?= e($date) ?>" data-autosubmit></label><noscript><button class="btn btn-sm"><?= e(__('Go')) ?></button></noscript></form><?php endif; ?>
  <form method="post" action="<?= e(url($edit ? '/attendance/' . $edit['id'] : '/attendance')) ?>" class="form"><?= csrf_field() ?>
    <input type="hidden" name="count_date" value="<?= e($f['count_date']) ?>">
    <?php if ($services && !$edit): ?>
      <label><?= e(__('Service')) ?><select name="event_id"><?= options(array_column(array_map(fn($x) => ['id' => $x['id'], 'l' => $x['title'] . ' · ' . fmt_time($x['start_at'])], $services), 'l', 'id'), $f['event_id']) ?></select></label>
    <?php endif; ?>
    <label><?= e(__('Service name')) ?><input name="service_label" required maxlength="120" value="<?= e($f['service_label']) ?>" list="svc"><datalist id="svc"><option value="<?= e(__('Sunday service')) ?>"><option value="<?= e(__('Morning service')) ?>"><option value="<?= e(__('Evening service')) ?>"><?php foreach ($services as $x): ?><option value="<?= e($x['title']) ?>"><?php endforeach; ?></datalist>
      <span class="help"><?= e(__('Date: %s. Use the same name each week so trends line up.', fmt_date($f['count_date'], 'l j F Y'))) ?></span></label>
    <?php if (Campus::multi() && !Campus::restriction()): ?><label><?= e(__('Campus')) ?><select name="campus_id" required><?= options(Campus::all(), $f['campus_id'] ?? null, __('— Choose —')) ?></select></label>
    <?php else: ?><?= Campus::field(isset($f['campus_id']) ? (int)$f['campus_id'] : null) ?><?php endif; ?>
    <div class="grid-2">
      <label><?= e(__('Adults')) ?><input type="number" min="0" name="adults" value="<?= e((string)$f['adults']) ?>" inputmode="numeric"></label>
      <label><?= e(__('Youth')) ?><input type="number" min="0" name="youth" value="<?= e((string)$f['youth']) ?>" inputmode="numeric"></label>
      <label><?= e(__('Children')) ?><input type="number" min="0" name="children" value="<?= e((string)$f['children']) ?>" inputmode="numeric"><?php if ($kids): ?><span class="help"><?= e(__('%d children were checked in that day.', $kids)) ?></span><?php endif; ?></label>
      <label><?= e(__('Online')) ?><input type="number" min="0" name="online" value="<?= e((string)$f['online']) ?>" inputmode="numeric"><span class="help"><?= e(__('Households or devices watching live.')) ?></span></label>
      <label><?= e(__('First-time visitors')) ?><input type="number" min="0" name="visitors" value="<?= e((string)$f['visitors']) ?>" inputmode="numeric"><span class="help"><?= e(__('Included in the counts above.')) ?></span></label>
    </div>
    <label><?= e(__('Notes')) ?><input name="notes" maxlength="255" value="<?= e((string)$f['notes']) ?>" placeholder="<?= e(__('e.g. school holidays, combined service, wet weather')) ?>"></label>
    <button class="btn btn-primary"><?= e($edit ? __('Save count') : __('Record count')) ?></button>
    <?php if ($edit): ?><a class="btn" href="<?= e(url('/attendance')) ?>"><?= e(__('Cancel')) ?></a><?php endif; ?>
  </form>
</div>
<div class="card table-wrap">
  <h2 class="h3"><?= e(__('Recent counts')) ?></h2>
  <table><thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Service')) ?></th><th class="right"><?= e(__('Total')) ?></th><th class="right"><?= e(__('Visitors')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $t = (int)$r['adults'] + (int)$r['youth'] + (int)$r['children'] + (int)$r['online']; ?>
    <tr><td><?= e(fmt_date($r['count_date'], 'D j M')) ?></td><td><?= e($r['service_label']) ?><?= $r['campus'] && Campus::multi() ? '<div class="small muted">' . e($r['campus']) . '</div>' : '' ?><?= $r['notes'] ? '<div class="small muted">' . e($r['notes']) . '</div>' : '' ?></td>
    <td class="right" title="<?= e(__('Adults %d, youth %d, children %d, online %d', $r['adults'], $r['youth'], $r['children'], $r['online'])) ?>"><strong><?= $t ?></strong></td><td class="right"><?= (int)$r['visitors'] ?></td>
    <td class="actions-cell"><a class="btn btn-sm" href="<?= e(url('/attendance', ['edit' => $r['id']])) ?>"><?= e(__('Edit')) ?></a>
      <form class="inline-form" method="post" action="<?= e(url('/attendance/' . $r['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this count?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete')) ?></button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= e(__('No counts recorded yet.')) ?></td></tr><?php endif; ?>
  </tbody></table>
</div>
</div>
