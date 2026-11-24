<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head><meta charset="utf-8"><title><?= e($title ?? '') ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="print-body"><?= $content ?><script src="<?= e(asset('js/app.js')) ?>"></script></body></html>
