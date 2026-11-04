<?php
/**
 * Shared storefront category + search + sort control.
 *
 * Category tabs are plain links so they keep the current query and sort, and
 * so pressing Enter in the search field never resets the selected category.
 *
 * Optional variables:
 *   $action         Form target (default /store).
 *   $search         Current query string.
 *   $category       Active category (All, Plugin, Theme).
 *   $sort           Active sort key.
 *   $allLabel       Label for the "All" tab.
 *   $submitLabel    Label for the submit button.
 *   $tabs           Category value => label map.
 *   $showCategories Set false to render only the search field.
 *   $showSort       Set false to hide the sort select.
 */
use function Wordpresso\Core\e;

$action = (string) ($action ?? '/store');
$search = (string) ($search ?? '');
$category = (string) ($category ?? 'All');
$sort = (string) ($sort ?? 'featured');
$allLabel = (string) ($allLabel ?? 'All products');
$submitLabel = (string) ($submitLabel ?? 'Search');
$tabs = $tabs ?? ['Plugin' => 'Plugins', 'Theme' => 'Themes'];
$showCategories = (bool) ($showCategories ?? true);
$showSort = (bool) ($showSort ?? true);
$sortOptions = [
    'featured' => 'Default',
    'name' => 'Name A–Z',
    'price-asc' => 'Price: low to high',
    'price-desc' => 'Price: high to low',
];

$tabUrl = static function (string $value) use ($action, $search, $sort): string {
    $query = ['category' => $value];
    if ($search !== '') {
        $query['search'] = $search;
    }
    if ($sort !== 'featured') {
        $query['sort'] = $sort;
    }
    return $action . '?' . http_build_query($query);
};
?>
<div class="featured-filter-panel store-filter<?= $showCategories ? '' : ' store-filter-search' ?>">
  <form method="get" action="<?= e($action) ?>" class="featured-filter-form" role="search">
    <?php if ($showCategories): ?>
      <div class="category-tabs" role="group" aria-label="Filter by category">
        <a href="<?= e($tabUrl('All')) ?>" class="<?= $category === 'All' ? 'selected' : '' ?>"<?= $category === 'All' ? ' aria-current="page"' : '' ?>><?= e($allLabel) ?></a>
        <?php foreach ($tabs as $value => $label): ?>
          <a href="<?= e($tabUrl((string) $value)) ?>" class="<?= $category === (string) $value ? 'selected' : '' ?>"<?= $category === (string) $value ? ' aria-current="page"' : '' ?>><?= e((string) $label) ?></a>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="category" value="<?= e($category) ?>">
    <?php endif; ?>
    <div class="featured-search">
      <span aria-hidden="true">⌕</span>
      <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search products" aria-label="Search products">
      <?php if ($showSort): ?>
        <select name="sort" aria-label="Sort products">
          <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button>
    </div>
  </form>
</div>
