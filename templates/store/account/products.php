<?php
use function Wordpresso\Core\e;
use Wordpresso\Core\View;

$search = (string) ($search ?? '');
$category = (string) ($category ?? 'All');
$total = (int) ($total ?? count($products));
$count = count($products);
$isFiltered = $search !== '' || $category !== 'All';
?>
<div class="page-head"><div><h1>Products</h1><p class="account-intro">Download products included with your active plan or purchased directly.</p></div></div>

<?php if ($total > 0): ?>
  <?= View::render('store/partials/search', [
      'action' => '/account/products',
      'search' => $search,
      'category' => $category,
      'allLabel' => 'All products',
      'submitLabel' => 'Search',
      'tabs' => ['Plugin' => 'Plugins', 'Theme' => 'Themes'],
      'showSort' => false,
  ]) ?>
  <div class="store-results">
    <span>Showing <strong><?= $count ?> product<?= $count === 1 ? '' : 's' ?></strong><?= $search !== '' ? ' matching “' . e($search) . '”' : ' in your library' ?></span>
    <?php if ($isFiltered): ?><a href="/account/products">Clear filters</a><?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($products === []): ?>
  <?php if ($isFiltered): ?>
    <div class="card account-empty-state"><span class="account-empty-icon">⌕</span><h2>No matching products</h2><p class="muted"><?= $search !== '' ? 'Nothing in your library matches “' . e($search) . '”.' : 'No products in this category are available in your library.' ?> Try another search or clear the filters.</p><a class="btn" href="/account/products">Clear filters</a></div>
  <?php else: ?>
    <div class="card account-empty-state"><span class="account-empty-icon">⇩</span><h2>No products available yet</h2><p class="muted">Products from active plans and paid purchases will appear here.</p><a class="btn btn-primary" href="/plans">View plans</a></div>
  <?php endif; ?>
<?php else: ?>
  <div class="account-product-grid">
    <?php foreach ($products as $product): ?>
      <article class="account-product-card">
        <div class="account-product-card-head"><span class="account-product-icon"><?= $product['category'] === 'Theme' ? '◫' : '✦' ?></span><span class="badge badge-active"><?= e($product['category']) ?></span></div>
        <h2><?= e($product['name']) ?></h2>
        <p class="muted"><?= e(mb_strimwidth((string) $product['description'], 0, 150, '…')) ?></p>
        <div class="account-product-card-foot">
        <?php if (!empty($product['package_path'])): ?>
          <a class="btn btn-primary" href="/account/products/download/<?= e($product['type']) ?>/<?= (int) $product['id'] ?>">Download latest release</a>
        <?php else: ?>
          <p class="muted">No published release is available yet.</p>
        <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
