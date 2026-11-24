<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/reports')) ?>"><?= e(__('Reports & insights')) ?></a></div><h1><?= e($title) ?></h1><p><?= e($desc) ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/reports/' . $name, ['export' => 1])) ?>"><?= e(__('Export CSV')) ?></a><button class="btn" data-print><?= e(__('Print')) ?></button></div></div>
<?php if (!empty($extra['totals'])): ?><div class="grid-4" style="margin-bottom:18px"><?php foreach ($extra['totals'] as $k => $v): ?><div class="stat"><div class="num"><?= e((string)$v) ?></div><div class="lbl"><?= e(__(ucfirst(str_replace('_', ' ', $k)))) ?></div></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!empty($extra['bars'])): ?><div class="grid-3"><?php foreach ($extra['bars'] as $label => $bars): $max = max(1, ...array_map(fn($b) => (int)$b['n'], $bars ?: [['n' => 1]])); ?>
<div class="card"><h2 class="h3" style="margin-top:0"><?= e(__($label)) ?></h2>
<?php foreach ($bars as $b): ?><div class="small" style="display:grid;grid-template-columns:90px 1fr 50px;gap:8px;align-items:center;margin:4px 0"><span><?= e(__(ucfirst((string)$b['label']))) ?></span><span class="progress"><span style="width:<?= round((int)$b['n'] / $max * 100) ?>%;background:var(--teal-2)"></span></span><span class="right"><?= e(number_format((float)$b['n'])) ?></span></div><?php endforeach; ?>
<?php if (!$bars): ?><p class="empty"><?= e(__('No data yet.')) ?></p><?php endif; ?></div>
<?php endforeach; ?></div><?php endif; ?>
<?php if (!empty($extra['note'])): ?><p class="small muted"><?= e($extra['note']) ?></p><?php endif; ?>
<div class="card table-wrap"><table><thead><tr><?php foreach ($header as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e((string)$c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($header) ?>" class="empty"><?= e(__('Nothing to report — all clear.')) ?></td></tr><?php endif; ?>
</tbody></table></div>
