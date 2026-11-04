<?php
/** @var string $content */
use function Wordpresso\Core\e;
$siteName = \Wordpresso\Models\StoreSettings::get('site_title', $siteName ?? 'Wordpresso');
$adminEmail = \Wordpresso\Core\Session::get('admin_email', '');
$isAdmin = \Wordpresso\Core\Session::get('admin_id') !== null;
$adminNav = [
    '/admin' => 'Dashboard',
    '/admin/products' => 'Products',
    '/admin/updates' => 'Auto Updater',
    '/admin/orders' => 'Orders',
    '/admin/subscriptions' => 'Subscriptions',
    '/admin/store-settings' => 'Store settings',
    '/admin/migrations' => 'Migrations',
    '/admin/payments' => 'Payments',
    '/admin/customers' => 'Customers',
    '/admin/websites' => 'Websites',
    '/admin/logs' => 'Logs',
];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Admin') ?> — <?= e($siteName ?? 'Wordpresso') ?> Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/admin.css?v=updater-manager-20261031">
</head>
<body class="admin">
<header class="admin-header">
  <div class="admin-header-inner">
    <a class="brand" href="/admin"><?= e($siteName ?? 'Wordpresso') ?> <span class="muted">/ admin</span></a>
    <?php if ($isAdmin): ?>
      <div class="admin-account">
      <span class="muted"><?= e($adminEmail) ?></span>
      <form method="post" action="/admin/logout" class="inline-form">
        <?= \Wordpresso\Core\Csrf::field() ?>
        <button type="submit" class="btn btn-ghost">Sign out</button>
      </form>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($isAdmin): ?>
    <nav class="admin-subnav" aria-label="Admin sections">
      <div class="admin-subnav-inner">
        <?php foreach ($adminNav as $href => $label): ?>
          <a href="<?= e($href) ?>" class="<?= ($href === '/admin' ? $path === '/admin' : str_starts_with($path, $href)) ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </nav>
    <?php endif; ?>
</header>
<main class="admin-main">
<?= $content ?>
</main>
</body>
</html>
