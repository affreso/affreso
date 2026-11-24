<?php $s = $summary; $rec = array_filter($weeks, fn($r) => $r['recorded']); $avg = $rec ? (int)round(array_sum(array_column($rec, 'total')) / count($rec)) : 0; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/attendance')) ?>"><?= e(__('Attendance')) ?></a></div><h1><?= e($title) ?></h1></div>
<div class="actions">
  <form method="get" action="<?= e(url('/attendance/report')) ?>" class="inline-form"><input type="hidden" name="r" value="/attendance/report"><select id="wk" name="weeks" aria-label="<?= e(__('Period')) ?>" data-autosubmit><?= options([13 => __('Last 3 months'), 26 => __('Last 6 months'), 52 => __('Last 12 months')], $weeksN) ?></select><noscript><button class="btn btn-sm"><?= e(__('Go')) ?></button></noscript></form>
  <a class="btn" href="<?= e(url('/attendance/report', ['weeks' => $weeksN, 'export' => 1])) ?>"><?= e(__('Export CSV')) ?></a><button class="btn" data-print><?= e(__('Print')) ?></button></div></div>
<div class="grid-4" style="margin-bottom:18px">
  <div class="stat"><div class="num"><?= $avg ?: '—' ?></div><div class="lbl"><?= e(__('Average per week')) ?></div></div>
  <div class="stat"><div class="num"><?= $lastYear ?: '—' ?></div><div class="lbl"><?= e(__('Same period last year')) ?></div></div>
  <div class="stat"><div class="num"><?= $rec ? max(array_column($rec, 'total')) : '—' ?></div><div class="lbl"><?= e(__('Highest week')) ?></div></div>
  <div class="stat"><div class="num"><?= array_sum(array_column($weeks, 'visitors')) ?></div><div class="lbl"><?= e(__('First-time visitors')) ?></div></div>
</div>
<div class="card"><h2 class="h3"><?= e(__('Weekly attendance')) ?></h2><?php include APP_DIR . '/views/partials/attendance_chart.php'; ?>
<?php if (count($rec) < count($weeks)): ?><p class="small muted"><?= e(__('%d weeks have no count recorded and are left out of the averages.', count($weeks) - count($rec))) ?></p><?php endif; ?></div>
<?php if ($byCampus): ?><div class="card table-wrap"><h2 class="h3"><?= e(__('By campus')) ?></h2><table><thead><tr><th><?= e(__('Campus')) ?></th><th class="right"><?= e(__('Average per week')) ?></th><th class="right"><?= e(__('First-time visitors')) ?></th><th class="right"><?= e(__('Weeks recorded')) ?></th></tr></thead><tbody>
<?php foreach ($byCampus as $c): ?><tr><td><?= e($c['name']) ?></td><td class="right"><?= (int)$c['avg'] ?></td><td class="right"><?= (int)$c['visitors'] ?></td><td class="right"><?= (int)$c['weeks'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="card table-wrap"><h2 class="h3"><?= e(__('Week by week')) ?></h2><table><thead><tr><th><?= e(__('Week starting')) ?></th><th class="right"><?= e(__('Adults')) ?></th><th class="right"><?= e(__('Youth')) ?></th><th class="right"><?= e(__('Children')) ?></th><th class="right"><?= e(__('Online')) ?></th><th class="right"><?= e(__('Total')) ?></th><th class="right"><?= e(__('Visitors')) ?></th></tr></thead><tbody>
<?php foreach (array_reverse($rec) as $r): ?><tr><td><?= e(fmt_date($r['week'], 'j M Y')) ?></td><?php if ($r['recorded']): ?><td class="right"><?= $r['adults'] ?></td><td class="right"><?= $r['youth'] ?></td><td class="right"><?= $r['children'] ?></td><td class="right"><?= $r['online'] ?></td><td class="right"><strong><?= $r['total'] ?></strong></td><td class="right"><?= $r['visitors'] ?></td><?php endif; ?></tr><?php endforeach; ?>
<?php if (!$rec): ?><tr><td colspan="7" class="empty"><?= e(__('No counts recorded in this period.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
