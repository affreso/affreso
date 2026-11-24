<div class="page-head"><div><h1><?= e(__('Forms & sign-ups')) ?></h1><p><?= e(__('Camps, courses, enquiries and other sign-ups that are open now.')) ?></p></div></div>
<div class="split">
<div class="card"><h2 class="h3"><?= e(__('Open now')) ?></h2>
<?php foreach ($forms as $f): ?><div class="sync-row" style="grid-template-columns:1fr auto;align-items:center"><div><strong><?= e($f['title']) ?></strong>
  <div class="small muted"><?= $f['closes_at'] ? e(__('Closes %s', fmt_datetime($f['closes_at']))) : '' ?><?= Forms::isPaid($f) ? ' · ' . e(Forms::priceText($f)) : '' ?></div></div>
  <a class="btn btn-primary btn-sm" href="<?= e(url('/f/' . $f['slug'])) ?>"><?= e(__('Fill in')) ?></a></div><?php endforeach; ?>
<?php if (!$forms): ?><p class="empty"><?= e(__('Nothing open at the moment.')) ?></p><?php endif; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('What I\'ve filled in')) ?></h2>
<ul class="list-plain"><?php foreach ($mine as $m): ?><li><?= e($m['title']) ?> · <span class="small muted"><?= e(fmt_date($m['created_at'])) ?></span> <?= badge($m['status']) ?></li><?php endforeach; ?></ul>
<?php if (!$mine): ?><p class="empty"><?= e(__('Nothing yet.')) ?></p><?php endif; ?>
</div></div>
