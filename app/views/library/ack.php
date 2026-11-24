<p class="no-print"><button class="btn" data-print><?= e(__('Print or save as PDF')) ?></button></p>
<div class="card" style="max-width:720px"><h1><?= e(__('Signing record')) ?></h1>
<dl class="kv"><dt><?= e(__('Document')) ?></dt><dd><?= e($a['title']) ?> (<?= e(__('version %d', (int)$a['version'])) ?>)</dd>
<dt><?= e(__('Person')) ?></dt><dd><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></dd>
<dt><?= e(__('Typed name')) ?></dt><dd><?= e($a['signed_name']) ?></dd>
<dt><?= e(__('Signed at')) ?></dt><dd><?= e(fmt_datetime($a['signed_at'])) ?></dd>
<dt><?= e(__('IP address')) ?></dt><dd><?= e($a['ip']) ?></dd>
<dt><?= e(__('Document fingerprint (SHA-256)')) ?></dt><dd class="small" style="word-break:break-all"><?= e($a['content_hash']) ?></dd></dl>
<?php if ($a['signature_png']): ?><p><img src="<?= e($a['signature_png']) ?>" alt="<?= e(__('Signature')) ?>" style="max-width:360px;border-bottom:1px solid #999"></p><?php endif; ?>
<p class="small muted"><?= e(__('Electronic signatures are generally valid in Australia under the Electronic Transactions Acts. Some documents (for example certain legal deeds) may need other formalities.')) ?></p></div>
