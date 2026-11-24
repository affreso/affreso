<?php if ($pg['pages'] > 1): $qs = $_GET; ?>
<nav class="pagination" aria-label="<?= e(__('Pagination')) ?>">
  <?php if ($pg['page'] > 1): $qs['page'] = $pg['page'] - 1; ?><a class="btn btn-sm" href="?<?= e(http_build_query($qs)) ?>">‹ <?= e(__('Previous')) ?></a><?php endif; ?>
  <span class="muted small"><?= e(__('Page %d of %d', $pg['page'], $pg['pages'])) ?></span>
  <?php if ($pg['page'] < $pg['pages']): $qs['page'] = $pg['page'] + 1; ?><a class="btn btn-sm" href="?<?= e(http_build_query($qs)) ?>"><?= e(__('Next')) ?> ›</a><?php endif; ?>
</nav>
<?php endif; ?>
