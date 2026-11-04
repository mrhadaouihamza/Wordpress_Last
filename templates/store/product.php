<?php
use function Wordpresso\Core\e;
use Wordpresso\Services\StoreCatalog;

$release = $release ?? null;
$type = (string) ($product['type'] ?? 'plugin');
$category = (string) ($product['category'] ?? 'Plugin');
$isPlan = $type === 'plan';
$isTheme = $type === 'theme';
$symbol = $isTheme ? '◫' : ($isPlan ? '◈' : '✦');
$price = (float) ($product['price'] ?? 0);
$termMonths = (int) ($product['term_months'] ?? 0);
$websiteLimit = (int) ($product['website_limit'] ?? 0);
$productKey = StoreCatalog::key($type, (int) $product['id']);
$hasImage = !empty($product['image_path']);
$version = (string) ($release['version'] ?? ($product['current_version'] ?? ''));
$changelog = trim((string) ($release['changelog'] ?? ''));
$publishedAt = (string) ($release['published_at'] ?? '');
$minWp = (string) ($release['min_wp_version'] ?? '');
$minPhp = (string) ($release['min_php_version'] ?? '');
$testedWp = (string) ($release['tested_wp_version'] ?? '');
$packageSize = (int) ($release['package_size'] ?? 0);

$summary = trim((string) preg_replace('/\s+/u', ' ', (string) ($product['description'] ?? '')));
$summary = function_exists('mb_strimwidth')
    ? mb_strimwidth($summary, 0, 190, '…')
    : (strlen($summary) > 190 ? substr($summary, 0, 187) . '…' : $summary);

$formatSize = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $bytes;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return number_format($value, ($value >= 10 || $unit === 0) ? 0 : 1) . ' ' . $units[$unit];
};

$termLabel = $termMonths <= 0 ? 'Lifetime' : $termMonths . ' months';
$accessLabel = $termMonths <= 0 ? 'lifetime access' : $termMonths . ' months of access';
?>
<a class="back-link" href="/store">← Back to store</a>
<article class="product-detail">
  <div class="product-detail-head">
    <div class="product-detail-media<?= $isTheme ? ' theme' : ($isPlan ? ' plan' : '') ?>">
      <?php if ($hasImage): ?>
        <img src="/products/<?= e($type) ?>/<?= (int) $product['id'] ?>/image" alt="<?= e($product['name']) ?> artwork">
      <?php else: ?>
        <span class="plugin-symbol large" aria-hidden="true"><?= $symbol ?></span>
      <?php endif; ?>
    </div>
    <div class="product-detail-title">
      <span class="section-kicker"><?= $isPlan ? 'ALL-ACCESS PLAN' : e(strtoupper($category)) . ' · LIFETIME LICENSE' ?></span>
      <h1><?= e($product['name']) ?></h1>
      <p class="muted"><?= e($summary) ?></p>
    </div>
    <div class="product-detail-price">
      <strong>$<?= number_format($price, 2) ?></strong>
      <span>USDT · <?= $isPlan ? e($termLabel) : 'one-time' ?></span>
    </div>
  </div>

  <div class="product-detail-content">
    <div class="product-description">
      <h2>About this <?= e(strtolower($category)) ?></h2>
      <p><?= nl2br(e($product['description'])) ?></p>

      <?php if ($isPlan): ?>
        <h2>What’s included</h2>
        <ul class="product-includes">
          <li>Every published plugin and theme in the catalogue</li>
          <li><?= $websiteLimit === 0 ? 'Unlimited connected websites' : (int) $websiteLimit . ' connected website' . ($websiteLimit === 1 ? '' : 's') ?></li>
          <li>Eligible plugin updates through the Wordpresso Auto Updater</li>
          <li>Protected package downloads from your account</li>
        </ul>
      <?php else: ?>
        <?php if ($changelog !== ''): ?>
          <h2>What’s new<?= $version !== '' ? ' in version ' . e($version) : '' ?></h2>
          <div class="release-changelog"><?= nl2br(e($changelog)) ?></div>
        <?php endif; ?>

        <h2>Compatibility</h2>
        <ul class="compat-list">
          <li><span>WordPress</span><strong><?= e($minWp !== '' ? $minWp . ' or newer' : 'Any version') ?></strong></li>
          <li><span>PHP</span><strong><?= e($minPhp !== '' ? $minPhp . ' or newer' : 'Any version') ?></strong></li>
          <li><span>Tested up to</span><strong><?= e($testedWp !== '' ? 'WordPress ' . $testedWp : 'Not specified') ?></strong></li>
        </ul>

        <h2>What’s included</h2>
        <ul class="product-includes">
          <li>Protected <?= $isTheme ? 'theme ZIP download' : 'plugin package download' ?></li>
          <?php if ($isTheme): ?>
            <li>New theme releases included</li>
          <?php else: ?>
            <li>Eligible updates through the Wordpresso Auto Updater</li>
          <?php endif; ?>
          <li>Account access, receipts, and order history</li>
        </ul>
      <?php endif; ?>
    </div>

    <aside class="product-sidebar">
      <span class="section-kicker"><?= $isPlan ? 'GET THIS PLAN' : 'PURCHASE' ?></span>
      <div class="sidebar-price">$<?= number_format($price, 2) ?> <small>USDT</small></div>
      <p class="sidebar-note">
        <?= $isPlan
          ? 'One-time payment for ' . e($accessLabel) . '. Plans do not renew automatically.'
          : 'One-time purchase with lifetime access. Downloads unlock after your payment is confirmed.' ?>
      </p>
      <form class="product-buy-form" method="post" action="/cart/add">
        <?= \Wordpresso\Core\Csrf::field() ?>
        <input type="hidden" name="product_key" value="<?= e($productKey) ?>">
        <button class="btn btn-primary btn-lg" type="submit">Add to cart</button>
      </form>

      <div class="sidebar-divider"></div>
      <h3><?= $isPlan ? 'Plan details' : 'Release details' ?></h3>
      <dl class="release-details">
        <?php if ($isPlan): ?>
          <dt>Access period</dt><dd><?= e($termLabel) ?></dd>
          <dt>Website limit</dt><dd><?= $websiteLimit === 0 ? 'Unlimited' : (int) $websiteLimit ?></dd>
          <dt>Billing</dt><dd>One-time</dd>
        <?php elseif ($release !== null): ?>
          <dt>Version</dt><dd><?= e($version !== '' ? $version : '—') ?></dd>
          <?php if ($publishedAt !== ''): ?><dt>Released</dt><dd><?= e(date('M j, Y', strtotime($publishedAt))) ?></dd><?php endif; ?>
          <dt>Package</dt><dd><?= e($formatSize($packageSize)) ?></dd>
        <?php else: ?>
          <dt>Release</dt><dd>Not published yet</dd>
        <?php endif; ?>
      </dl>
      <p class="sidebar-note">
        <?= $isPlan
          ? 'Access starts after your USDT payment is verified on BNB Smart Chain.'
          : ($isTheme
            ? 'Theme packages are downloaded from your account. Auto-updates apply to plugins only.'
            : 'Updates arrive inside WordPress through the Wordpresso Auto Updater plugin while your access is active.') ?>
      </p>
    </aside>
  </div>
</article>
