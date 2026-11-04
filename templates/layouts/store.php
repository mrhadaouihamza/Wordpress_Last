<?php
/** @var string $content */
use function Wordpresso\Core\e;
use Wordpresso\Models\StoreSettings;
$customer = $customer ?? null;
$siteName = StoreSettings::get('site_title', $siteName ?? 'Wordpresso');
$storeTagline = StoreSettings::get('site_tagline', 'Premium WordPress tools for your next great website.');
$storeDescription = StoreSettings::get('site_description', 'Browse plugins and themes in one catalog, then manage purchases and eligible plugin updates from your account.');
$metaDescription = StoreSettings::get('meta_description', 'Browse WordPress plugins and themes, manage purchases, and connect eligible plugin updates.');
$footerText = StoreSettings::get('footer_text', 'WordPress products, account access, and update connections in one place.');
$supportEmail = StoreSettings::get('support_email', '');
$pageTitle = $title ?? $siteName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($metaDescription) ?>">
<title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/store.css?v=store-v4-20261103">
</head>
<body class="storefront">
<aside class="announcement-strip"><span class="announce-icon">✦</span><span><?= e($storeTagline) ?></span><a href="/plans">Explore plans <b>→</b></a></aside>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/"><?= e($siteName ?? 'Wordpresso') ?><span class="brand-tld">.store</span></a>
    <nav class="nav">
      <a href="/store">Store</a>
      <a href="/plans">Plans</a>
      <a href="/cart">Cart</a>
      <?php if ($customer !== null): ?>
        <a href="/account">My account</a>
        <form method="post" action="/logout" class="inline-form">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <button type="submit" class="btn btn-ghost">Sign out</button>
        </form>
      <?php else: ?>
        <a href="/login">Sign in</a>
        <a href="/register" class="btn btn-primary">Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
    <div class="footer-brand"><a class="brand" href="/"><?= e($siteName) ?></a><p><?= e($footerText) ?></p><?php if ($supportEmail !== ''): ?><a class="store-support-email" href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a><?php endif; ?><span class="footer-security"><b>✓</b> Protected account and download access</span></div>
      <div><h3>Store catalog</h3><a href="/store">Browse plugins & themes</a><a href="/plans">Plans & membership</a></div>
      <div><h3>Your account</h3><a href="/account">My account</a><a href="/cart">Shopping cart</a><a href="/register">Create an account</a></div>
      <div><h3>Wordpresso</h3><a href="/#why-us">How it works</a><a href="/#about">About & FAQ</a><a href="/store">Plugins & themes</a><span class="footer-legal"><a href="/privacy">Privacy policy</a><a href="/terms">Terms of use</a></span></div>
    </div>
    <div class="footer-bottom"><span>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span><span>WordPress product access and updates</span></div>
  </div>
</footer>
</body>
</html>
