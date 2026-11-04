<?php
/** @var string $content */
use function Wordpresso\Core\e;
$siteName = \Wordpresso\Models\StoreSettings::get('site_title', $siteName ?? 'Wordpresso');
$accountNav = [
    '/account' => 'Dashboard',
    '/account/websites' => 'Websites',
    '/account/products' => 'Products',
    '/account/orders' => 'Orders',
    '/account/subscriptions' => 'Subscriptions',
    '/account/settings' => 'Settings',
];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'My account') ?> — <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/store.css?v=store-v4-20261103">
<style>
.account-product-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-top:24px}.account-product-card{display:flex;flex-direction:column;min-height:220px;padding:22px;border:1px solid #e1e7f0;border-radius:16px;background:#fff;box-shadow:0 8px 24px #21345d0b}.account-product-card-head{display:flex;align-items:center;justify-content:space-between}.account-product-icon,.account-empty-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#eef3ff;color:#3159a6;font-size:21px}.account-product-card h2{margin:18px 0 8px}.account-product-card-foot{margin-top:auto;padding-top:18px}.account-empty-state{text-align:center;padding:46px 24px}.account-empty-icon{margin:0 auto 15px}.included-products{display:flex;flex-wrap:wrap;gap:7px;min-width:220px}.included-product{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;background:#eef3ff;color:#3159a6;text-decoration:none;font-size:12px;font-weight:600}.included-product:hover{background:#dfe9ff}
</style>
</head>
<body class="account">
<header class="account-header">
  <div class="account-header-inner">
    <a class="brand" href="/account"><?= e($siteName ?? 'Wordpresso') ?> <span class="muted">/ account</span></a>
    <div class="account-tools">
      <a class="account-store-link" href="/store">View store</a>
      <span class="muted account-email"><?= e($customer['email'] ?? '') ?></span>
      <form method="post" action="/logout" class="inline-form">
        <?= \Wordpresso\Core\Csrf::field() ?>
        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
      </form>
    </div>
  </div>
  <nav class="account-subnav" aria-label="Account sections">
    <div class="account-subnav-inner">
      <?php foreach ($accountNav as $href => $label): ?>
        <a href="<?= e($href) ?>" class="<?= ($href === '/account' ? $path === '/account' : str_starts_with($path, $href)) ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>
<main class="account-main">
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?= $content ?>
</main>
<footer class="account-foot">
  <div class="account-foot-inner">
    <span class="muted">&copy; <?= date('Y') ?> <?= e($siteName) ?></span>
    <span class="account-foot-links">
      <a href="/privacy">Privacy policy</a>
      <a href="/terms">Terms of use</a>
    </span>
    <a href="/store">← Back to the store</a>
  </div>
</footer>
</body>
</html>
