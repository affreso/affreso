<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/library')) ?>"><?= e(__('Resource library')) ?></a></div><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url('/library')) ?>" enctype="multipart/form-data" class="form"><?= csrf_field() ?>
<div class="split"><div class="card">
  <?php partial('library/meta_fields', ['d' => $d, 'groups' => $groups]); ?>
  <h2 class="h3"><?= e(__('Content')) ?></h2>
  <label><?= e(__('Upload a file (PDF, Word, PowerPoint, Excel, image, MP3)')) ?><input type="file" name="file"></label>
  <label><?= e(__('…or paste the text (best for policies people sign)')) ?><textarea name="body" rows="12"></textarea></label>
</div><div class="card"><button class="btn btn-primary"><?= e(__('Publish')) ?></button></div></div></form>
