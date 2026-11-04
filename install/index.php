<?php

declare(strict_types=1);

define('WORDPRESSO_ROOT', dirname(__DIR__));
require WORDPRESSO_ROOT . '/app/bootstrap.php';

use Wordpresso\Core\Config;
use Wordpresso\Core\Crypto;
use Wordpresso\Core\Db;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
$_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));

$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$error = '';
$success = false;
$email = '';
$configReady = false;
$configPath = WORDPRESSO_ROOT . '/config/config.local.php';

try {
    Config::load(WORDPRESSO_ROOT . '/config/config.php');
    $configReady = is_file($configPath)
        && Config::get('db_driver') === 'mysql'
        && trim((string)Config::get('db_name')) !== ''
        && trim((string)Config::get('db_user')) !== ''
        && strlen((string)Config::get('installer_key')) >= 32
        && !str_starts_with((string)Config::get('installer_key'), 'replace-with-');
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $accessKey = (string)($_POST['installer_key'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
    try {
        if (!$configReady) throw new RuntimeException('Finish config/config.local.php using config.local.php.example, including a private installer_key with at least 32 characters.');
        if (!hash_equals((string)Config::get('installer_key'), $accessKey)) throw new RuntimeException('The installer key is incorrect.');
        if (!hash_equals((string)$_SESSION['installer_csrf'], (string)($_POST['_csrf'] ?? ''))) throw new RuntimeException('The form expired. Reload this page and try again.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid administrator email address.');
        if (strlen($password) < 12) throw new RuntimeException('Choose a password with at least 12 characters.');
        if (!hash_equals($password, $passwordConfirm)) throw new RuntimeException('The password confirmation does not match.');

        foreach ([Config::get('packages_dir'), dirname((string)Config::get('packages_dir')) . '/product-images', Config::get('logs_dir'), dirname((string)Config::get('app_key_file'))] as $directory) {
            $directory = (string)$directory;
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Could not create a required storage directory.');
            if (!is_writable($directory)) throw new RuntimeException('A storage directory is not writable by PHP.');
        }

        $pdo = Db::pdo();
        $sql = file_get_contents(__DIR__ . '/database.sql');
        if ($sql === false) throw new RuntimeException('Could not read install/database.sql from the website folder.');
        $sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql) ?? $sql;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') $pdo->exec($statement);
        }

        if ((int)Db::value('SELECT COUNT(*) FROM admins') > 0) throw new RuntimeException('An administrator already exists. The installer will not change existing accounts.');
        $websiteCount = (int)Db::value('SELECT COUNT(*) FROM websites');
        $configuredAppKey = trim((string)Config::get('app_key', ''));
        $appKeyFile = (string)Config::get('app_key_file', '');
        if ($configuredAppKey === '' && $websiteCount > 0 && ($appKeyFile === '' || !is_file($appKeyFile) || trim((string)file_get_contents($appKeyFile)) === '')) {
            throw new RuntimeException('Website records exist but their encryption key is missing. Restore the original storage/app_key before continuing.');
        }
        Crypto::appKey();

        Db::run('INSERT INTO admins (email,password_hash) VALUES (?,?)', [$email, password_hash($password, PASSWORD_DEFAULT)]);
        $success = true;
        session_regenerate_id(true);
        unset($_SESSION['installer_csrf']);
        if (@unlink(__FILE__)) {
            @rmdir(__DIR__);
        } else {
            $error = 'Installation completed, but the server could not remove install/index.php. Remove the install folder manually now.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install Wordpresso</title>
  <style>
    :root{color-scheme:light;--ink:#172033;--muted:#68758b;--line:#e2e8f0;--brand:#6048d8;--brand-dark:#4f38c5;--surface:#fff;--bg:#f3f5fb;--bad:#a12d3a;--ok:#16734b}
    *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 12% 8%,#e5e7ff 0,transparent 32%),radial-gradient(circle at 90% 86%,#e0f6ed 0,transparent 28%),var(--bg);font:15px/1.55 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:28px}
    .installer{width:min(100%,640px);background:var(--surface);border:1px solid rgba(220,226,238,.9);border-radius:22px;padding:clamp(24px,5vw,48px);box-shadow:0 24px 70px rgba(31,42,73,.12)}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:30px}.brand-mark{display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:#eeeaff;color:var(--brand);font-size:22px;font-weight:800}.eyebrow{display:block;color:var(--brand);font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.brand strong{display:block;font-size:16px}.brand small{display:block;color:var(--muted);font-size:12px}
    h1{margin:0 0 8px;font-size:clamp(28px,5vw,36px);line-height:1.15;letter-spacing:-.04em}.intro{margin:0 0 26px;color:var(--muted)}.status{display:flex;gap:11px;align-items:flex-start;padding:14px 16px;margin:0 0 20px;border-radius:12px;background:#fff3f2;color:var(--bad);font-size:13px}.status.success{background:#eaf8f0;color:var(--ok)}.status b{font-size:17px;line-height:1.2}.setup-note{margin:0 0 22px;padding:14px 16px;border:1px solid #e6e4fc;background:#f7f6ff;border-radius:12px;color:#525c75;font-size:13px}.setup-note code{color:#43349e;overflow-wrap:anywhere}
    form{display:grid;gap:16px}label{display:grid;gap:7px;font-size:13px;font-weight:700;color:#39445a}input{width:100%;border:1px solid #cfd7e5;border-radius:10px;padding:12px 13px;font:inherit;color:var(--ink);background:white;outline:none;transition:border .15s,box-shadow .15s}input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(96,72,216,.14)}input::placeholder{color:#98a2b4}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.btn{display:flex;justify-content:center;align-items:center;gap:9px;width:100%;border:0;border-radius:10px;background:var(--brand);padding:13px 18px;color:white;font:inherit;font-weight:750;cursor:pointer;box-shadow:0 8px 18px rgba(96,72,216,.2);transition:background .15s,transform .15s}.btn:hover{background:var(--brand-dark);transform:translateY(-1px)}.footer{margin:22px 0 0;text-align:center;color:var(--muted);font-size:12px}.footer a{color:var(--brand);font-weight:700;text-decoration:none}
    @media(max-width:560px){body{padding:14px}.installer{border-radius:17px}.form-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<main class="installer">
  <div class="brand"><span class="brand-mark">W</span><span><span class="eyebrow">Setup wizard</span><strong>Wordpresso</strong><small>Secure store installation</small></span></div>
  <?php if ($success): ?>
    <div class="status success"><b>✓</b><span><strong>Installation complete.</strong><br>Your administrator account is ready. Sign in to configure the store and add products.</span></div>
    <h1>Your store is ready</h1><p class="intro">The database schema has been imported and your administrator account was created. The installer has been removed.</p>
    <?php if ($error !== ''): ?><div class="status"><b>!</b><span><?= $escape($error) ?></span></div><?php endif; ?>
    <a class="btn" href="/admin/login">Go to admin sign in <span>→</span></a>
  <?php else: ?>
    <h1>Set up your store</h1>
    <p class="intro">Import the database schema and create your first administrator account.</p>
    <?php if ($error !== ''): ?><div class="status"><b>!</b><span><?= $escape($error) ?></span></div><?php endif; ?>
    <?php if (!$configReady): ?>
      <div class="setup-note">Before continuing, copy <code>config/config.local.php.example</code> to <code>config/config.local.php</code>, enter the cPanel database credentials, and set a private installer key of at least 32 characters. The config folder is blocked from web access.</div>
    <?php else: ?>
      <div class="setup-note">Connected database: <strong><?= $escape((string)Config::get('db_name')) ?></strong>. The schema import uses <code>CREATE TABLE IF NOT EXISTS</code> and preserves existing tables.</div>
    <?php endif; ?>
    <form method="post" action="/install/">
      <input type="hidden" name="_csrf" value="<?= $escape($_SESSION['installer_csrf']) ?>">
      <label>Installer key<input type="password" name="installer_key" required autocomplete="off" placeholder="The private key set in config.local.php" <?= !$configReady ? 'disabled' : '' ?>></label>
      <label>Administrator email<input type="email" name="email" required autocomplete="email" placeholder="admin@example.com" value="<?= $escape($email) ?>" <?= !$configReady ? 'disabled' : '' ?>></label>
      <div class="form-grid">
        <label>Administrator password<input type="password" name="password" required minlength="12" autocomplete="new-password" placeholder="At least 12 characters" <?= !$configReady ? 'disabled' : '' ?>></label>
        <label>Confirm password<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password" placeholder="Enter it again" <?= !$configReady ? 'disabled' : '' ?>></label>
      </div>
      <button class="btn" type="submit" <?= !$configReady ? 'disabled' : '' ?>>Import database and create admin <span>→</span></button>
    </form>
    <p class="footer">After setup, the installer removes itself. <a href="/">Return to storefront</a></p>
  <?php endif; ?>
</main>
</body>
</html>
