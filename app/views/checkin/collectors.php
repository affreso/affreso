<h2 class="h3"><?= e(__('Authorised to collect')) ?></h2>
<p class="small muted"><?= e(__('Adults in the household can always collect. Add anyone else here.')) ?></p>
<ul class="list-plain small"><?php foreach ($collectors as $c): ?><li><?= $c['not_permitted'] ? '<span class="badge badge-red">' . e(__('NOT permitted')) . '</span> ' : '' ?><?= e($c['name']) ?> <?= e($c['relationship']) ?> <span dir="ltr"><?= e($c['phone']) ?></span>
<form class="inline-form" method="post" action="<?= e(url('/collectors/' . $c['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn-link small"><?= e(__('remove')) ?></button></form></li><?php endforeach; ?></ul>
<form method="post" action="<?= e(url('/people/' . $child['id'] . '/collectors')) ?>" class="form"><?= csrf_field() ?>
  <div class="grid-2"><label><?= e(__('Name')) ?><input name="name" required></label><label><?= e(__('Relationship')) ?><input name="relationship" placeholder="<?= e(__('e.g. Grandparent')) ?>"></label><label><?= e(__('Phone')) ?><input name="phone"></label></div>
  <label class="check"><input type="checkbox" name="not_permitted" value="1"> <?= e(__('This person is NOT permitted to collect (e.g. court order)')) ?></label>
  <button class="btn btn-sm"><?= e(__('Add')) ?></button>
</form>
