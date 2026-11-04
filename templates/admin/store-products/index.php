<?php use function Wordpresso\Core\e; ?>
<div class="page-head"><div><h1>Products</h1><p class="muted">Plugins are listed from the updater catalogue, themes from their own catalogue. Manage prices, packages, and update releases here.</p></div>
  <div class="inline-actions"><a class="btn btn-primary" href="/admin/products/new">Add product</a></div>
</div>
<?php if ($flash): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<table class="table"><thead><tr><th>Product</th><th>Type</th><th>Price</th><th>Package / updates</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($products as $product): $type = $product['type']; ?>
<tr>
  <td><a class="product-name-link" href="/admin/products/<?= e($type) ?>/<?= (int)$product['id'] ?>"><strong><?= e($product['name']) ?></strong></a><br><span class="muted">/<?= e($product['slug']) ?></span></td>
  <td><?= e($product['category']) ?></td>
  <td>$<?= number_format((float)$product['price'],2) ?> USDT</td>
  <td><?php if ($type === 'plugin'): ?>
    <?= e($product['linked_plugin'] ?? 'Updater record missing') ?><br><span class="muted">Version: <?= e($product['current_version'] ?? 'No release yet') ?></span>
    <?php if (!empty($product['update_plugin_id'])): ?><br><a class="product-update-link" href="/admin/plugins/<?= (int)$product['update_plugin_id'] ?>">Upload / publish releases →</a><?php endif; ?>
  <?php else: ?><?= !empty($product['package_path'])?'ZIP package ready':'Package missing' ?><br><a class="product-update-link" href="/admin/products/theme/<?= (int)$product['id'] ?>">Replace / publish theme ZIP →</a><?php endif; ?></td>
  <td><?= (int)$product['active']?'Published':'Hidden' ?></td>
  <td><div class="inline-actions"><a class="btn btn-sm" href="/admin/products/<?= e($type) ?>/<?= (int)$product['id'] ?>">Edit product</a><form method="post" action="/admin/products/<?= e($type) ?>/<?= (int)$product['id'] ?>/toggle"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm <?= (int)$product['active']?'':'btn-primary' ?>" type="submit"><?= (int)$product['active']?'Hide listing':'Publish listing' ?></button></form><form method="post" action="/admin/products/<?= e($type) ?>/<?= (int)$product['id'] ?>/delete" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('<?= $type === 'plugin' ? 'Permanently delete this plugin and its releases?' : 'Permanently delete this theme and its package?' ?>')"><?= $type === 'plugin' ? 'Delete product' : 'Delete theme' ?></button></form></div></td>
</tr>
<?php endforeach; ?>
<?php foreach ($unlistedPlugins as $plugin): ?>
<tr class="unlisted-product-row">
  <td><strong><?= e($plugin['name']) ?></strong><br><span class="muted">/<?= e($plugin['slug']) ?></span></td>
  <td>Plugin</td>
  <td><span class="muted">Not priced</span></td>
  <td><?= $plugin['current_version'] !== null ? 'Update release: '.e($plugin['current_version']) : 'No published release' ?><br><span class="muted">Package will use the published release or a ZIP upload</span></td>
  <td><span class="badge badge-draft">Not listed</span></td>
  <td><div class="inline-actions"><a class="btn btn-primary btn-sm" href="/admin/products/plugin/<?= (int)$plugin['id'] ?>">List product</a><form method="post" action="/admin/products/plugin/<?= (int)$plugin['id'] ?>/delete" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Permanently delete this unlisted plugin and all releases?')">Delete</button></form></div></td>
</tr>
<?php endforeach; ?>
<?php if (!$products && !$unlistedPlugins): ?><tr><td colspan="6" class="muted">No plugins or themes yet. Add a product, or register a plugin update listing first.</td></tr><?php endif; ?>
</tbody></table>
