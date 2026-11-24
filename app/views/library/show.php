<?php $did = (int)$d['id']; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/library')) ?>"><?= e(__('Resource library')) ?></a></div><h1><?= e($d['title']) ?></h1><p><?= e($d['category']) ?> · <?= e(__('Version %d', (int)$d['current_version'])) ?><?= $current ? ' · ' . e(fmt_date($current['created_at'])) : '' ?></p></div></div>
<?php if ($d['description']): ?><p><?= nl2br(e($d['description'])) ?></p><?php endif; ?>
<div class="split"><div>
<div class="card">
  <?php if ($current && $current['file_id']): ?><p><a class="btn btn-primary" href="<?= e(url('/library/file/' . $current['id'])) ?>" target="_blank">📄 <?= e(__('Open %s', $current['original_name'])) ?></a></p><?php endif; ?>
  <?php if ($current && $current['body']): ?><div class="lesson-body"><?= e($current['body']) ?></div><?php endif; ?>
</div>
<?php if ((int)$d['requires_ack'] && person_id()): ?>
<div class="card" id="sign">
  <?php if ($ack): ?><p><?= badge('completed') ?> <?= e(__('You signed version %d on %s.', (int)$ack['version'], fmt_datetime($ack['signed_at']))) ?> <a href="<?= e(url('/library/ack/' . $ack['id'])) ?>"><?= e(__('View signing record')) ?></a></p>
  <?php else: ?>
  <h2><?= e(__('Read and sign')) ?></h2>
  <form method="post" action="<?= e(url('/library/' . $did . '/sign')) ?>" class="form" data-signature-form><?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="agree" value="1" required> <?= e(__('I have read and understood this document (version %d) and agree to follow it.', (int)$d['current_version'])) ?></label>
    <label><?= e(__('Type your full name')) ?><input name="signed_name" required minlength="3" autocomplete="name"></label>
    <p class="small"><?= e(__('Draw your signature (optional):')) ?></p>
    <canvas class="sig-pad" width="460" height="140" aria-label="<?= e(__('Signature pad')) ?>"></canvas>
    <input type="hidden" name="signature">
    <p><button type="button" class="btn btn-sm" data-sig-clear><?= e(__('Clear')) ?></button></p>
    <p class="small muted"><?= e(__('We record your typed name, the date and time, your IP address, and a fingerprint of the exact version you signed.')) ?></p>
    <button class="btn btn-primary"><?= e(__('Sign')) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($signers): ?>
<div class="card table-wrap"><div class="card-head"><h2><?= e(__('Signatures')) ?></h2><form method="post" action="<?= e(url('/library/' . $did . '/remind')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Request signatures')) ?></button></form></div>
<table><tbody><?php foreach ($signers as $s): ?><tr><td><?= e(person_name($s)) ?></td><td><?= $s['ack'] ? badge('completed') . ' <span class="small muted">' . e(fmt_date($s['ack']['signed_at'])) . '</span>' : ($s['last'] ? '<span class="badge badge-amber">' . e(__('Signed v%d — needs current', (int)$s['last']['version'])) . '</span>' : '<span class="badge badge-red">' . e(__('Not signed')) . '</span>') ?></td><td><?php if ($s['ack']): ?><a class="small" href="<?= e(url('/library/ack/' . $s['ack']['id'])) ?>"><?= e(__('record')) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
</div>
<div>
<div class="card"><h2 class="h3"><?= e(__('Version history')) ?></h2><ul class="list-plain small"><?php foreach ($versions as $v): ?><li><strong>v<?= (int)$v['version'] ?></strong> · <?= e(fmt_date($v['created_at'])) ?> <?= e($v['notes']) ?><?php if ($v['file_id'] && can('library.manage')): ?> · <a href="<?= e(url('/library/file/' . $v['id'])) ?>"><?= e(__('file')) ?></a><?php endif; ?><div class="muted" title="SHA-256"><?= e(substr($v['content_hash'], 0, 16)) ?>…</div></li><?php endforeach; ?></ul></div>
<?php if (can('library.manage')): ?>
<form method="post" action="<?= e(url('/library/' . $did . '/version')) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?><h2 class="h3"><?= e(__('Publish a new version')) ?></h2>
  <label><?= e(__('File')) ?><input type="file" name="file"></label><label><?= e(__('…or text')) ?><textarea name="body" rows="5"><?= e($current['body'] ?? '') ?></textarea></label><label><?= e(__('What changed?')) ?><input name="notes"></label><button class="btn btn-sm btn-primary"><?= e(__('Publish version %d', (int)$d['current_version'] + 1)) ?></button></form>
<form method="post" action="<?= e(url('/library/' . $did)) ?>" class="card form"><?= csrf_field() ?><h2 class="h3"><?= e(__('Settings')) ?></h2><?php partial('library/meta_fields', ['d' => $d, 'groups' => $groups]); ?><label class="check"><input type="checkbox" name="archived" value="1" <?= (int)$d['archived'] ? 'checked' : '' ?>> <?= e(__('Archived')) ?></label><button class="btn btn-sm"><?= e(__('Save')) ?></button></form>
<?php endif; ?>
</div></div>
