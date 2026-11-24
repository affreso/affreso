<div class="page-head"><div><h1><?= e(__('Church directory')) ?></h1><p><?= e(__('Only people who have chosen to be listed appear here. Please use these details only for church fellowship.')) ?></p></div></div>
<form class="filters" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="r" value="/directory"><label><?= e(__('Search')) ?><input type="search" name="q" value="<?= e($q) ?>"></label><button class="btn"><?= e(__('Search')) ?></button></form>
<div class="card table-wrap"><table><thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Household')) ?></th><th><?= e(__('Email')) ?></th><th><?= e(__('Mobile')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(person_name($r)) ?></td><td><?= e($r['household']) ?></td><td><?= $r['email'] ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?></td><td><span dir="ltr"><?= e($r['mobile']) ?></span></td></tr><?php endforeach; ?>
</tbody></table></div>
