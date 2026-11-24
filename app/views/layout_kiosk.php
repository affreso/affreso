<!doctype html>
<html lang="<?= e(current_language()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(setting('church_name', 'Affreso')) ?> · <?= e(__('Check-in')) ?></title>
<meta name="theme-color" content="#1d4e57">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="kiosk-body"<?php if (!empty($refresh)): ?> data-idle="<?= (int)$refresh[0] ?>" data-idle-url="<?= e($refresh[1]) ?>"<?php endif; ?>>
<header class="kiosk-head"><span class="logo-mark" aria-hidden="true">A</span><strong><?= e(setting('church_name', 'Affreso')) ?></strong><span class="kiosk-tag"><?= e(__("Kids' check-in")) ?></span>
<?php if (!empty($k)): ?><button type="button" class="kiosk-fs" data-fullscreen aria-label="<?= e(__('Full screen')) ?>">⛶</button><?php endif; ?></header>
<main id="main" class="kiosk-main">
  <?php foreach (flashes() as [$type, $msg]): ?><div class="alert alert-<?= e($type) ?>" role="alert"><?= e($msg) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
<?php if (!empty($k)): ?><footer class="kiosk-foot"><a href="<?= e(url('/kiosk/exit')) ?>"><?= e(__('Staff')) ?></a></footer><?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
