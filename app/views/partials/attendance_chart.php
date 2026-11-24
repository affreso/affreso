<?php
/** Stacked weekly column chart. Expects $weeks (from AttendanceController::weekly). */
$W = 720; $H = 240; $padL = 36; $padB = 26; $padT = 10;
$max = max(10, ...array_column($weeks, 'total'));
$step = (int)ceil($max / 4 / 10) * 10 ?: 10; $max = $step * 4;
$n = count($weeks); $bw = ($W - $padL - 6) / max(1, $n);
$y = fn($v) => $padT + ($H - $padT - $padB) * (1 - $v / $max);
$parts = ['adults' => __('Adults'), 'youth' => __('Youth'), 'children' => __('Children'), 'online' => __('Online')];
$rec = array_filter($weeks, fn($r) => $r['recorded']);
$label = __('Weekly attendance for %d weeks. Average %d, highest %d.', $n, $rec ? (int)round(array_sum(array_column($rec, 'total')) / count($rec)) : 0, $rec ? max(array_column($rec, 'total')) : 0);
?>
<figure class="chart">
<svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e($label) ?>" preserveAspectRatio="xMidYMid meet">
  <?php for ($i = 0; $i <= 4; $i++): $v = $step * $i; ?><line x1="<?= $padL ?>" x2="<?= $W ?>" y1="<?= round($y($v), 1) ?>" y2="<?= round($y($v), 1) ?>" class="grid"/><text x="<?= $padL - 6 ?>" y="<?= round($y($v) + 4, 1) ?>" class="axis" text-anchor="end"><?= $v ?></text><?php endfor; ?>
  <?php foreach ($weeks as $i => $r): $x = $padL + $i * $bw + $bw * 0.15; $acc = 0; ?>
    <g><title><?= e(__('Week of %s', fmt_date($r['week'], 'j M Y')) . ': ' . ($r['recorded'] ? $r['total'] : __('not recorded'))) ?></title>
    <?php foreach ($parts as $k => $pl): if (!$r[$k]) continue; $y1 = $y($acc + $r[$k]); $h = $y($acc) - $y1; $acc += $r[$k]; ?><rect x="<?= round($x, 1) ?>" y="<?= round($y1, 1) ?>" width="<?= round($bw * 0.7, 1) ?>" height="<?= round(max(0, $h), 1) ?>" class="seg-<?= $k ?>"/><?php endforeach; ?>
    <?php if (!$r['recorded']): ?><rect x="<?= round($x, 1) ?>" y="<?= $H - $padB - 2 ?>" width="<?= round($bw * 0.7, 1) ?>" height="2" class="seg-none"/><?php endif; ?>
    <?php if ($i % max(1, (int)ceil($n / 9)) === 0): ?><text x="<?= round($x + $bw * 0.35, 1) ?>" y="<?= $H - 8 ?>" class="axis" text-anchor="middle"><?= e(fmt_date($r['week'], 'j M')) ?></text><?php endif; ?>
    </g>
  <?php endforeach; ?>
</svg>
<figcaption class="legend"><?php foreach ($parts as $k => $pl): ?><span><i class="seg-<?= $k ?>"></i><?= e($pl) ?></span><?php endforeach; ?></figcaption>
</figure>
