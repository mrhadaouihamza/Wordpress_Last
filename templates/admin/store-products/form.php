<?php
use function Wordpresso\Core\e;
$editing = !empty($product);
$type = $type ?? strtolower($category ?? 'plugin');
$category = $product['category'] ?? ($category ?? 'Plugin');
$release = $release ?? null;
$name = $product['name'] ?? ($plugin['name'] ?? '');
$slug = $product['slug'] ?? ($plugin['slug'] ?? '');
$description = $product['description'] ?? ($plugin['description'] ?? '');
$price = $product['price'] ?? 0;
$active = $editing ? !empty($product['active']) : true;
$action = $editing ? '/admin/products/' . e($type) . '/' . (int)$product['id'] : '/admin/products';
?>
<div class="product-form-page">
  <div class="page-head product-form-heading">
    <div><span class="section-kicker">STORE CATALOG</span><h1><?= $editing ? 'Edit product' : 'Add product' ?></h1><p class="muted">Set the product details. Plugin downloads use the latest published release.</p></div>
    <div class="product-form-heading-actions"><?php if ($plugin): ?><a class="btn" href="/admin/plugins/<?= (int)$plugin['id'] ?>">Manage plugin releases</a><?php endif; ?><a class="btn" href="/admin/products">← Back to products</a></div>
  </div>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($plugin && !$editing): ?><div class="product-prefill-note"><span class="product-prefill-icon">↻</span><span><strong>Existing update plugin selected</strong><small><?= e($plugin['name']) ?> is already registered for WordPress updates.<?= $release ? ' Current published release ' . e($release['version']) . ' can be used as its initial download.' : ' Upload a store package or publish a release before listing it.' ?></small></span></div><?php endif; ?>

  <form class="product-editor form" method="post" enctype="multipart/form-data" action="<?= e($action) ?>">
    <?= \Wordpresso\Core\Csrf::field() ?>

    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">✦</span><div><h2>Product details</h2><p>Name, category, price, and the description customers will see.</p></div></div>
      <div class="product-image-editor">
        <?php if (!empty($imagePath) && $editing): ?><img src="/products/<?= e($type) ?>/<?= (int)$product['id'] ?>/image" alt="Current product artwork"><?php else: ?><div class="product-image-empty">✦</div><?php endif; ?>
        <label>Product image<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"><small>Optional. JPG, PNG, or WebP, up to 5 MB. Leave empty to keep the current image.</small></label>
      </div>
      <div class="product-form-grid">
        <label>Product name<input type="text" name="name" placeholder="e.g. Wordpresso Forms" required value="<?= e($name) ?>"><small class="field-hint"><span>Example</span> Wordpresso Forms</small></label>
        <label>URL slug<input type="text" name="slug" placeholder="e.g. wordpresso-forms" required pattern="[a-z0-9][a-z0-9-]*" value="<?= e($slug) ?>"><small class="field-hint"><span>Format</span> <code>wordpresso-forms</code> · lowercase letters, numbers, and hyphens</small></label>
        <label>Price (USDT)<input type="number" name="price" min="0" step="0.01" required value="<?= e(number_format((float)$price, 2, '.', '')) ?>"></label>
        <?php if ($editing): ?>
          <label>Product type<input type="text" value="<?= e($category) ?><?= $type === 'plugin' ? ' (updater record)' : '' ?>" disabled></label>
        <?php else: ?>
          <label>Product type<select name="category" id="product-category"><option value="Plugin" <?= $category==='Plugin'?'selected':'' ?>>WordPress plugin</option><option value="Theme" <?= $category==='Theme'?'selected':'' ?>>WordPress theme</option></select></label>
        <?php endif; ?>
        <label class="product-description-field">Description<textarea name="description" placeholder="Describe what the product does and what customers receive." rows="4"><?= e($description) ?></textarea><small class="field-hint"><span>Tip</span> Describe what the product does and what customers receive.</small></label>
        <label class="checkbox product-auto-update"><input type="checkbox" name="active" value="1" <?= $active ? 'checked' : '' ?>> Publish this listing in the store</label>
      </div>
    </section>

    <div id="theme-update-note" class="alert alert-credentials" <?= $category==='Theme'?'':'hidden' ?>>Upload theme releases below. ZIPs must contain <code><?= e($slug) ?>/style.css</code> with a matching version.</div>

    <section id="plugin-fields" class="product-editor-section plugin-update-section" <?= $category==='Plugin' ? '' : 'hidden' ?>>
      <div class="product-section-heading"><span class="product-section-icon">↻</span><div><h2>Plugin update connection</h2><p>Connect this store listing to the WordPress update service.</p></div></div>
      <label>Plugin main-file path<input type="text" name="basename" placeholder="my-plugin/my-plugin.php" value="<?= e($plugin['basename'] ?? '') ?>"><small class="field-hint"><span>Example</span> <code>my-plugin/my-plugin.php</code> · the path inside <code>wp-content/plugins</code></small></label>
      <?php if ($release): ?><p class="release-hint"><span class="badge badge-published">Release ready</span> Current published version: <strong><?= e($release['version']) ?></strong></p><?php endif; ?>
      <div class="product-form-grid product-technical-grid">
        <label>Minimum WordPress version<input type="text" name="min_wp_version" placeholder="e.g. 6.0" value="<?= e($plugin['min_wp_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 6.0</small></label>
        <label>Minimum PHP version<input type="text" name="min_php_version" placeholder="e.g. 8.0" value="<?= e($plugin['min_php_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 8.0</small></label>
        <label>Tested WordPress version<input type="text" name="tested_wp_version" placeholder="e.g. 6.8" value="<?= e($plugin['tested_wp_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 6.8</small></label>
      </div>
      <label class="checkbox product-auto-update"><input type="checkbox" name="auto_update_allowed" value="1" <?= !$plugin || !empty($plugin['auto_update_allowed'])?'checked':'' ?>> Allow automatic updates in WordPress</label>
    </section>

    <div class="product-form-actions">
      <a class="btn" href="/admin/products">Cancel</a>
      <?php if ($editing): ?>
        <button class="btn btn-danger" type="submit" form="delete-store-product" onclick="return confirm('<?= $type === 'plugin' ? 'Remove this product from the store? Its plugin updater record will remain.' : 'Permanently delete this theme and its package?' ?>')"><?= $type === 'plugin' ? 'Remove from store' : 'Delete product' ?></button>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $editing ? 'Save product' : 'Create product' ?></button>
    </div>
  </form>
  <?php if ($editing && $type === 'theme'): ?>
  <section class="product-editor-section">
    <div class="product-section-heading"><span class="product-section-icon">↻</span><div><h2>Theme releases</h2><p>Upload drafts, then publish the release customers should receive.</p></div></div>
    <form method="post" action="/admin/themes/<?= (int) $product['id'] ?>/versions" enctype="multipart/form-data" class="form">
      <?= \Wordpresso\Core\Csrf::field() ?>
      <label>Theme ZIP package<input type="file" name="package" accept=".zip" required></label>
      <div class="form-row"><label>Version<input type="text" name="version" placeholder="1.2.3" required pattern="\d+(\.\d+)*([.-][A-Za-z0-9]+)?"></label><label>Minimum WordPress<input type="text" name="min_wp_version"></label><label>Minimum PHP<input type="text" name="min_php_version"></label><label>Tested WordPress<input type="text" name="tested_wp_version"></label></div>
      <label>Changelog<textarea name="changelog" rows="3"></textarea></label>
      <button class="btn btn-primary" type="submit">Upload release</button>
    </form>
    <?php if (!empty($themeVersions)): ?><table class="table"><thead><tr><th>Version</th><th>Status</th><th>Requirements</th><th>Actions</th></tr></thead><tbody><?php foreach ($themeVersions as $version): ?><tr><td><?= e($version['version']) ?> <?= (int) $version['is_current'] === 1 ? '<span class="badge badge-active">current</span>' : '' ?></td><td><?= e($version['status']) ?></td><td>WP <?= e($version['min_wp_version'] ?: 'any') ?> · PHP <?= e($version['min_php_version'] ?: 'any') ?></td><td><?php if ((int) $version['is_current'] !== 1): ?><form method="post" action="/admin/theme-versions/<?= (int) $version['id'] ?>/publish" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-primary">Publish</button></form><form method="post" action="/admin/theme-versions/<?= (int) $version['id'] ?>/delete" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger">Delete</button></form><?php else: ?><form method="post" action="/admin/theme-versions/<?= (int) $version['id'] ?>/withdraw" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger">Withdraw</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if ($editing): ?>
    <form id="delete-store-product" method="post" action="/admin/products/<?= e($type) ?>/<?= (int)$product['id'] ?>/delete">
      <?= \Wordpresso\Core\Csrf::field() ?>
    </form>
  <?php endif; ?>
</div>
<script>
(() => {
  const type = document.getElementById('product-category');
  const fields = document.getElementById('plugin-fields');
  const themeNote = document.getElementById('theme-update-note');
  function update() {
    const plugin = type ? type.value === 'Plugin' : <?= $type === 'plugin' ? 'true' : 'false' ?>;
    fields.hidden = !plugin;
    if (themeNote) themeNote.hidden = plugin;
    const basename = fields.querySelector('[name="basename"]');
    if (basename) basename.required = plugin;
  }
  if (type) type.addEventListener('change', update);
  update();
})();
</script>
