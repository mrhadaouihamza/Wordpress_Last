<?php
use function Wordpresso\Core\e;
?>
<div class="page-intro">
  <h1>Choose your access plan</h1>
  <p>Every plan is all-access: it includes every published plugin and theme. Access starts after your USDT payment is confirmed.</p>
</div>

<?php if ($plans === []): ?>
  <div class="empty-state">
    <span class="empty-icon">◈</span>
    <h3>No plans are available yet</h3>
    <p>Check back later for subscription options, or browse the products available now.</p>
    <a class="btn btn-primary" href="/store">Browse the store</a>
  </div>
<?php else: ?>
  <div class="pricing-grid plan-grid">
    <?php foreach ($plans as $plan): ?>
      <?php
      $termMonths = (int) ($plan['term_months'] ?? 0);
      $websiteLimit = (int) ($plan['website_limit'] ?? 0);
      $termLabel = $termMonths <= 0 ? 'Lifetime access' : $termMonths . ' months access';
      ?>
      <article class="pricing-card featured-plan plan-card">
        <span class="plan-ribbon">ALL-ACCESS</span>
        <?php if (!empty($plan['image_path'])): ?>
          <img class="plan-product-image" src="/products/plan/<?= (int) $plan['id'] ?>/image" alt="<?= e($plan['name']) ?> artwork">
        <?php else: ?>
          <span class="pricing-icon" aria-hidden="true">◈</span>
        <?php endif; ?>
        <h2><?= e($plan['name']) ?></h2>
        <p><?= nl2br(e($plan['description'])) ?></p>
        <div class="plan-price">
          <strong>$<?= number_format((float) $plan['price'], 2) ?></strong>
          <span>USDT · <?= e($termLabel) ?></span>
        </div>
        <ul class="plan-features">
          <li>Every published plugin and theme</li>
          <li><?= $websiteLimit === 0 ? 'Unlimited connected websites' : (int) $websiteLimit . ' connected website' . ($websiteLimit === 1 ? '' : 's') ?></li>
          <li>Eligible plugin updates in WordPress</li>
          <li>Protected downloads from your account</li>
        </ul>
        <form method="post" action="/cart/add">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <input type="hidden" name="product_key" value="plan:<?= (int) $plan['id'] ?>">
          <button class="btn btn-primary btn-lg" type="submit">Add to cart</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
