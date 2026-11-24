<?php
/** Web installer — only reachable while config/config.php does not exist. */
declare(strict_types=1);

$req = Installer::requirements();
$allOk = !in_array(false, $req, true);
$errors = [];
$done = false;
$v = fn(string $k, string $d = '') => e($_POST[$k] ?? $d);

if (is_post() && $allOk) {
    verify_csrf();
    $db = ['host' => trim($_POST['db_host'] ?? 'localhost'), 'port' => (int)($_POST['db_port'] ?? 3306), 'name' => trim($_POST['db_name'] ?? ''), 'user' => trim($_POST['db_user'] ?? ''), 'pass' => (string)($_POST['db_pass'] ?? ''), 'prefix' => preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_prefix'] ?? 'af_'))];
    $opts = ['church_name' => trim($_POST['church_name'] ?? ''), 'state' => $_POST['state'] ?? 'NSW', 'admin_name' => trim($_POST['admin_name'] ?? ''), 'admin_email' => mb_strtolower(trim($_POST['admin_email'] ?? '')), 'admin_password' => (string)($_POST['admin_password'] ?? ''), 'sample_data' => !empty($_POST['sample_data']), 'demo' => !empty($_POST['demo'])];
    $tz = in_array($_POST['timezone'] ?? '', timezone_identifiers_list(), true) ? $_POST['timezone'] : 'Australia/Sydney';
    if ($opts['church_name'] === '') $errors[] = 'Enter your church name.';
    if ($opts['admin_name'] === '') $errors[] = 'Enter the administrator\'s name.';
    if (!filter_var($opts['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid administrator email.';
    if ($msg = Auth::validatePassword($opts['admin_password'])) $errors[] = $msg;
    if (($opts['admin_password']) !== ($_POST['admin_password2'] ?? '')) $errors[] = 'The passwords do not match.';
    if ($db['name'] === '' || $db['user'] === '') $errors[] = 'Enter the database name and user.';
    if (!$errors) {
        try {
            date_default_timezone_set($tz);
            DB::connect($db);
            if (DB::val("SHOW TABLES LIKE '" . $db['prefix'] . "settings'")) {
                throw new RuntimeException('Affreso tables with prefix "' . $db['prefix'] . '" already exist in this database. Choose a different prefix or empty the database.');
            }
            $key = Crypto::newKey();
            Crypto::init($key);
            Installer::createSchema($db['prefix']);
            $GLOBALS['AFFRESO_CONFIG'] = ['db' => $db, 'demo_mode' => $opts['demo']];
            Installer::seed($opts);
            Installer::writeConfig($db, $tz, $key, $opts['demo']);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install Affreso</title><link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Crect width=%2732%27 height=%2732%27 rx=%277%27 fill=%27%23b9832f%27/%3E%3Ctext x=%2716%27 y=%2723%27 font-size=%2720%27 text-anchor=%27middle%27 fill=%27white%27 font-family=%27Georgia%27%3EA%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="auth-body"><main class="auth-card wide">
<div class="brand-lg"><span class="logo-mark">A</span> Affreso</div>
<?php if ($done): ?>
  <h1>Affreso is installed</h1>
  <p>Your church management system is ready. Next steps:</p>
  <ol class="steps">
    <li>Sign in with the administrator account you just created.</li>
    <li>You will be asked to set up two-step verification (an authenticator app on your phone).</li>
    <li>Go to <strong>Administration → Settings</strong> to connect email (SMTP), SMS, and Stripe.</li>
    <li>Set up the scheduled task (cron) described in <code>docs/INSTALL.md</code> so reminders and queued messages are sent.</li>
    <li>Back up <code>config/config.php</code> somewhere safe — it holds the key that protects confidential data.</li>
  </ol>
  <p><a class="btn btn-primary" href="<?= e(url('/login')) ?>">Go to sign in</a></p>
<?php else: ?>
  <h1>Install Affreso</h1>
  <p class="muted">One secure system for church leaders, administrators, members and volunteers.</p>
  <h2>1. Server check</h2>
  <ul class="checklist">
  <?php foreach ($req as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></li><?php endforeach; ?>
  </ul>
  <?php if (!$allOk): ?><div class="alert alert-error">Please ask your web host to fix the items marked ✗, then reload this page.</div><?php else: ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?>
    <h2>2. Your church</h2>
    <div class="grid-2">
      <label>Church name<input name="church_name" required value="<?= $v('church_name') ?>"></label>
      <label>State / territory<select name="state"><?= options(au_states() + ['OTHER' => 'Outside Australia'], $_POST['state'] ?? 'NSW') ?></select></label>
      <label>Time zone<select name="timezone"><?= options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()), $_POST['timezone'] ?? 'Australia/Sydney') ?></select></label>
    </div>
    <h2>3. Database (MySQL / MariaDB)</h2>
    <p class="muted small">Create an empty database and user in your hosting control panel (cPanel → MySQL Databases), then enter the details here.</p>
    <div class="grid-2">
      <label>Database host<input name="db_host" value="<?= $v('db_host', 'localhost') ?>"></label>
      <label>Port<input name="db_port" value="<?= $v('db_port', '3306') ?>"></label>
      <label>Database name<input name="db_name" required value="<?= $v('db_name') ?>"></label>
      <label>Database user<input name="db_user" required value="<?= $v('db_user') ?>"></label>
      <label>Database password<input type="password" name="db_pass"></label>
      <label>Table prefix<input name="db_prefix" value="<?= $v('db_prefix', 'af_') ?>"></label>
    </div>
    <h2>4. Super administrator</h2>
    <div class="grid-2">
      <label>Full name<input name="admin_name" required value="<?= $v('admin_name') ?>"></label>
      <label>Email<input type="email" name="admin_email" required value="<?= $v('admin_email') ?>"></label>
      <label>Password (10+ characters)<input type="password" name="admin_password" required minlength="10"></label>
      <label>Confirm password<input type="password" name="admin_password2" required minlength="10"></label>
    </div>
    <label class="check"><input type="checkbox" name="sample_data" value="1" <?= !empty($_POST['sample_data']) ? 'checked' : '' ?>> Load sample data (demo families, teams, rosters and courses — useful for trying Affreso; don't use on your live system)</label>
    <label class="check"><input type="checkbox" name="demo" value="1" <?= !empty($_POST['demo']) ? 'checked' : '' ?>> <strong>Make this a public demo site</strong> — loads sample data, adds one-click sign-in buttons for each role, switches off email, SMS and payments, and resets everything to fresh sample data every night (your own administrator login is kept)</label>
    <p><button class="btn btn-primary">Install Affreso</button></p>
  </form>
  <?php endif; ?>
<?php endif; ?>
</main></body></html>
