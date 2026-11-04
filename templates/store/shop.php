<?php
use function Wordpresso\Core\e;
use Wordpresso\Core\View;

$search = (string) ($search ?? '');
$category = (string) ($category ?? 'All');
$sort = (string) ($sort ?? 'featured');
$count = count($products);
$categoryNoun = $category === 'All' ? 'product' : strtolower($category);
$resultLabel = $count . ' ' . $categoryNoun . ($count === 1 ? '' : 's');
$isFiltered = $search !== '' || $category !== 'All' || $sort !== 'featured';
?>
<div class="page-intro">
  <h1>Store</h1>
  <p>Browse every available plugin and theme, or add an all-access plan to unlock the whole catalogue.</p>
</div>

<?= View::render('store/partials/search', [
    'action' => '/store',
    'search' => $search,
    'category' => $category,
    'sort' => $sort,
    'allLabel' => 'All products',
    'submitLabel' => 'Search',
    'tabs' => ['Plugin' => 'Plugins', 'Theme' => 'Themes'],
]) ?>

<div class="store-results">
  <span>Showing <strong><?= e($resultLabel) ?></strong><?= $search !== '' ? ' matching “' . e($search) . '”' : '' ?></span>
  <?php if ($isFiltered): ?><a href="/store">Clear filters</a><?php endif; ?>
</div>

<?php if ($products === []): ?>
  <div class="empty-state">
    <span class="empty-icon">✦</span>
    <h3><?= $search !== '' ? 'No products match your search' : 'No products are available yet' ?></h3>
    <p><?= $search !== ''
      ? 'We could not find anything for “' . e($search) . '”. Try a shorter query or browse the full catalogue.'
      : 'New listings appear here once they are published by the store administrator.' ?></p>
    <?php if ($isFiltered): ?>
      <a class="btn btn-primary" href="/store">Clear filters</a>
    <?php else: ?>
      <a class="btn btn-primary" href="/">Back to home</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="shop-grid">
    <?php foreach ($products as $product): ?>
      <?php
      $url = '/product/' . (string) $product['type'] . '/' . (int) $product['id'];
      $symbol = $product['category'] === 'Theme' ? '◫' : '✦';
      $summary = (string) preg_replace('/\s+/u', ' ', (string) $product['description']);
      $summary = function_exists('mb_strimwidth') ? mb_strimwidth($summary, 0, 140, '…') : $summary;
      ?>
      <article class="shop-card">
        <a class="shop-art-link" href="<?= e($url) ?>" aria-label="View <?= e($product['name']) ?>">
          <div class="shop-art <?= strtolower(e($product['category'])) ?>">
            <?php if (!empty($product['image_path'])): ?>
              <img class="catalog-product-image" src="/products/<?= e($product['type']) ?>/<?= (int) $product['id'] ?>/image" alt="">
            <?php else: ?>
              <span aria-hidden="true"><?= $symbol ?></span>
            <?php endif; ?>
          </div>
        </a>
        <div class="shop-card-body">
          <h2><a href="<?= e($url) ?>"><?= e($product['name']) ?></a></h2>
          <p><?= e($summary) ?></p>
          <div class="shop-meta">
            <?php if (!empty($product['current_version'])): ?><span>Version <?= e($product['current_version']) ?></span><?php endif; ?>
            <span><?= $product['category'] === 'Theme' ? 'Theme download' : 'Lifetime updates' ?></span>
          </div>
          <div class="shop-buy">
            <strong>$<?= number_format((float) $product['price'], 2) ?> <small>USDT</small></strong>
            <form method="post" action="/cart/add">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <input type="hidden" name="product_key" value="<?= e((string) $product['type'] . ':' . (int) $product['id']) ?>">
              <button class="btn btn-primary" type="submit">Add to cart</button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($products !== []): ?>
<div class="pricing-note">
  <strong>Need the whole toolkit?</strong>
  <span>All-access plans include every published plugin and theme.</span>
  <a href="/plans">Compare plans →</a>
</div>
<?php endif; ?>
