<!doctype html>
<html lang="<?= e(current_language()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · <?= e(setting('church_name', 'Affreso')) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Crect width=%2732%27 height=%2732%27 rx=%277%27 fill=%27%23b9832f%27/%3E%3Ctext x=%2716%27 y=%2723%27 font-size=%2720%27 text-anchor=%27middle%27 fill=%27white%27 font-family=%27Georgia%27%3EA%3C/text%3E%3C/svg%3E">
<link rel="manifest" href="<?= e(url('/manifest')) ?>">
<meta name="theme-color" content="#1d4e57">
<link rel="apple-touch-icon" href="<?= e(base_path() . '/assets/icons/apple-touch-icon.png') ?>">
<meta name="base-path" content="<?= e(base_path()) ?>/">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-card<?= Demo::on() && ($GLOBALS['AFFRESO_ROUTE'] ?? '') === '/login' ? ' wide' : '' ?>" id="main">
  <div class="brand-lg"><span class="logo-mark">A</span><?= e(setting('church_name', 'Affreso')) ?></div>
  <?php foreach (flashes() as [$type, $msg]): ?><div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
