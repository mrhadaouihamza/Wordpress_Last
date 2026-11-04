<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Db;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;
use Wordpresso\Models\StoreSettings;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Services\StoreCatalog;
use Wordpresso\Services\StoreOrderActivation;

/**
 * Store catalogue admin. Plugins are listed directly from the `plugins`
 * table (the updater catalogue extended with storefront columns) and themes
 * from `themes`, so every route that targets one product carries its type:
 *
 *   /admin/store-products/{type}/{id}          type = plugin | theme
 */
final class StoreProductController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();

        $versions = [];
        foreach (Db::fetchAll("SELECT plugin_id, version FROM plugin_versions WHERE is_current = 1 AND status = 'published'") as $row) {
            $versions[(int) $row['plugin_id']] = (string) $row['version'];
        }

        $products = StoreCatalog::listings(false);
        foreach ($products as &$product) {
            if ($product['type'] === 'plugin') {
                $product['linked_plugin'] = $product['name'];
                $product['update_plugin_id'] = (int) $product['id'];
                $product['current_version'] = $versions[(int) $product['id']] ?? null;
            } else {
                $product['linked_plugin'] = null;
                $product['update_plugin_id'] = null;
                $product['current_version'] = null;
            }
        }
        unset($product);

        // Plugins that exist for updates but are not sold in the store yet.
        $unlistedPlugins = Db::fetchAll(
            "SELECT pl.*, pv.version AS current_version FROM plugins pl
             LEFT JOIN plugin_versions pv ON pv.plugin_id = pl.id AND pv.is_current = 1 AND pv.status = 'published'
             WHERE pl.active = 0
             ORDER BY pl.name"
        );

        return $this->page('admin/store-products/index', [
            'products' => $products,
            'unlistedPlugins' => $unlistedPlugins,
            'flash' => Session::flash('success'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();
        $category = 'Plugin';

        // The plugin row is the listing now — open it for editing.
        $pluginId = (int) $request->query('plugin_id', 0);
        if ($pluginId > 0) {
            return $this->edit($request, ['type' => 'plugin', 'id' => (string) $pluginId]);
        }

        return $this->page('admin/store-products/form', [
            'product' => null,
            'plugin' => null,
            'release' => null,
            'type' => strtolower($category),
            'category' => $category,
            'imagePath' => null,
            'error' => Session::flash('error'),
        ]);
    }

    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $type = strtolower((string) ($params['type'] ?? ''));
        $category = $this->categoryFor($type);
        if ($category === null) {
            return Response::error('Store listing not found.', 404);
        }

        $product = StoreCatalog::find($type, (int) ($params['id'] ?? 0));
        if ($product === null) {
            return Response::error('Store listing not found.', 404);
        }

        $plugin = $type === 'plugin' ? Plugin::find((int) $product['id']) : null;
        $release = $plugin ? PluginVersion::current((int) $plugin['id']) : null;
        $themeVersions = $type === 'theme' ? \Wordpresso\Models\ThemeVersion::forTheme((int) $product['id']) : [];

        return $this->page('admin/store-products/form', [
            'product' => $product,
            'plugin' => $plugin,
            'release' => $release,
            'themeVersions' => $themeVersions,
            'type' => $type,
            'category' => $category,
            'imagePath' => $product['image_path'] ?? null,
            'error' => Session::flash('error'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $editingId = (int) ($params['id'] ?? 0);
        $type = strtolower((string) ($params['type'] ?? $request->post('category', 'plugin')));
        $category = $this->categoryFor($type);
        if ($category === null) {
            Session::flash('error', 'Choose a valid product type.');
            return Response::redirect('/admin/store-products/new');
        }

        $formUrl = $editingId > 0
            ? '/admin/store-products/' . $type . '/' . $editingId
            : '/admin/store-products/new?category=' . rawurlencode($category);

        $existing = $editingId > 0 ? StoreCatalog::find($type, $editingId) : null;
        if ($editingId > 0 && $existing === null) {
            return Response::error('Store listing not found.', 404);
        }

        $name = trim((string) $request->post('name', ''));
        $slug = strtolower(trim((string) $request->post('slug', '')));
        $description = trim((string) $request->post('description', ''));
        $price = max(0.0, (float) $request->post('price', 0));
        $active = $request->post('active') !== null ? 1 : 0;

        if ($name === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) || $price < 0) {
            Session::flash('error', 'Enter a name, a valid lowercase slug, and a non-negative price.');
            return Response::redirect($formUrl);
        }
        // A published listing with no price cannot be bought — checkout
        // refuses a zero total — so keep it as a hidden draft instead.
        if ($active === 1 && $price <= 0) {
            Session::flash('error', 'Set a price above zero to publish this listing, or save it as a hidden draft.');
            return Response::redirect($formUrl);
        }

        $pluginData = null;
        $pluginId = null;
        if ($type === 'plugin') {
            // Editing updates the listing's own plugin row; creating reuses a
            // plugin that already carries this slug (the product form always
            // created both together).
            $plugin = $existing !== null
                ? Plugin::find((int) $existing['id'])
                : Plugin::findBySlug($slug);
            $pluginId = $plugin !== null ? (int) $plugin['id'] : null;

            $pluginWithSlug = Plugin::findBySlug($slug);
            if ($pluginWithSlug && $pluginId !== (int) $pluginWithSlug['id']) {
                Session::flash('error', 'This slug is already assigned to another plugin update listing.');
                return Response::redirect($formUrl);
            }

            $basename = trim((string) $request->post('basename', ''));
            if ($plugin !== null && $basename !== '' && $basename !== $plugin['basename']) {
                Session::flash('error', 'This plugin is already connected to another technical identifier.');
                return Response::redirect($formUrl);
            }
            $basename = $plugin['basename'] ?? $basename;
            if ($basename === '' || !preg_match('~^[a-zA-Z0-9._-]+/[a-zA-Z0-9._-]+\.php$~', $basename)) {
                Session::flash('error', 'Enter the plugin main-file path, for example my-plugin/my-plugin.php.');
                return Response::redirect($formUrl);
            }
            $sameBase = Plugin::findByBasename($basename);
            if ($sameBase && $pluginId !== (int) $sameBase['id']) {
                Session::flash('error', 'That technical plugin identifier is already assigned to another plugin.');
                return Response::redirect($formUrl);
            }

            $pluginData = [
                'slug' => $slug,
                'name' => $name,
                'basename' => $basename,
                'description' => $description,
                'auto_update_allowed' => (int) $request->post('auto_update_allowed', 0),
                'min_wp_version' => trim((string) $request->post('min_wp_version', '')),
                'min_php_version' => trim((string) $request->post('min_php_version', '')),
                'tested_wp_version' => trim((string) $request->post('tested_wp_version', '')),
            ];
        } elseif (Db::value('SELECT id FROM themes WHERE slug = ? AND id <> ?', [$slug, $editingId]) !== null) {
            Session::flash('error', 'That slug is already used by another theme.');
            return Response::redirect($formUrl);
        }

        $currentRelease = $pluginId !== null ? PluginVersion::current($pluginId) : null;
        $package = $type === 'plugin'
            ? ($currentRelease['package_path'] ?? null)
            : ($existing['package_path'] ?? null);

        $newImage = null;
        $imageUpload = $_FILES['product_image'] ?? null;
        if ($imageUpload && (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newImage = \Wordpresso\Services\ProductImageStorage::store($imageUpload);
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
                return Response::redirect($formUrl);
            }
        }
        $oldImage = $existing['image_path'] ?? null;
        $image = $newImage ?? $oldImage;

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        $sourceId = $editingId;
        try {
            if ($type === 'plugin') {
                if ($pluginId === null) {
                    $pluginId = Plugin::create($pluginData);
                } else {
                    Plugin::update($pluginId, $pluginData);
                }
                Db::run(
                    'UPDATE plugins SET price = ?, package_path = ?, image_path = ?, active = ? WHERE id = ?',
                    [$price, $package, $image, $active, $pluginId]
                );
                $sourceId = (int) $pluginId;
            } elseif ($existing !== null) {
                Db::run(
                    'UPDATE themes SET slug = ?, name = ?, description = ?, price = ?, package_path = ?, image_path = ?, active = ? WHERE id = ?',
                    [$slug, $name, $description, $price, $package, $image, $active, $editingId]
                );
                $sourceId = $editingId;
            } else {
                $sourceId = Db::insert(
                    'INSERT INTO themes (slug, name, description, price, package_path, image_path, active) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$slug, $name, $description, $price, $package, $image, $active]
                );
            }
            Db::run(
                'INSERT INTO products (source_id, category, slug, name, description, price, package_path, image_path, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name),
                    description = VALUES(description), price = VALUES(price),
                    package_path = VALUES(package_path), image_path = VALUES(image_path),
                    active = VALUES(active)',
                [$sourceId, $category, $slug, $name, $description, $price, $package, $image, $active]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \Wordpresso\Services\ProductImageStorage::delete($newImage);
            Session::flash('error', 'Could not save this listing: ' . $e->getMessage());
            return Response::redirect($formUrl);
        }

        if ($newImage && $oldImage) {
            \Wordpresso\Services\ProductImageStorage::delete($oldImage);
        }
        Session::flash('success', $existing ? 'Store listing updated.' : 'Store listing created.');
        return Response::redirect('/admin/store-products');
    }

    public function toggle(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $category = $this->categoryFor(strtolower((string) ($params['type'] ?? '')));
        if ($category === null) {
            return Response::error('Store listing not found.', 404);
        }
        $table = StoreCatalog::tableFor(strtolower((string) $params['type']));
        Db::run("UPDATE {$table} SET active = 1 - active WHERE id = ?", [(int) $params['id']]);
        Db::run('UPDATE products SET active = 1 - active WHERE category = ? AND source_id = ?', [$category, (int) $params['id']]);
        Session::flash('success', 'Store listing visibility updated.');
        return Response::redirect('/admin/store-products');
    }

    public function deleteProduct(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $type = strtolower((string) ($params['type'] ?? ''));
        $category = $this->categoryFor($type);
        if ($category === null) {
            return Response::error('Store listing not found.', 404);
        }
        $product = StoreCatalog::find($type, (int) $params['id']);
        if ($product === null) {
            return Response::error('Store listing not found.', 404);
        }

        \Wordpresso\Services\PackageStorage::delete((string) ($product['package_path'] ?? ''));
        \Wordpresso\Services\ProductImageStorage::delete($product['image_path'] ?? null);

        if ($type === 'plugin') {
            Plugin::delete((int) $product['id']);
            Db::run('DELETE FROM products WHERE category = ? AND source_id = ?', [$category, (int) $product['id']]);
            Session::flash('success', 'Plugin product and its releases were permanently deleted.');
        } else {
            Db::run('DELETE FROM themes WHERE id = ?', [(int) $product['id']]);
            Db::run('DELETE FROM products WHERE category = ? AND source_id = ?', [$category, (int) $product['id']]);
            Session::flash('success', 'Theme permanently deleted.');
        }
        return Response::redirect('/admin/store-products');
    }

    public function deleteUnlistedPlugin(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $id = (int) ($params['id'] ?? 0);
        $plugin = Plugin::find($id);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }
        try {
            Plugin::delete($id);
            Db::run('DELETE FROM products WHERE category = ? AND source_id = ?', ['Plugin', $id]);
            Session::flash('success', 'Plugin and its releases were permanently deleted.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return Response::redirect('/admin/products');
    }

    public function settings(Request $request): Response
    {
        $this->requireAdmin();
        if ($request->method==='POST') {
            Csrf::requireValid($request->post('_csrf') ?? null);
            $values = [
                'site_title' => trim((string)$request->post('site_title', '')),
                'site_tagline' => trim((string)$request->post('site_tagline', '')),
                'site_description' => trim((string)$request->post('site_description', '')),
                'meta_description' => trim((string)$request->post('meta_description', '')),
                'support_email' => trim((string)$request->post('support_email', '')),
                'footer_text' => trim((string)$request->post('footer_text', '')),
                'privacy_policy' => trim((string)$request->post('privacy_policy', '')),
                'terms_of_use' => trim((string)$request->post('terms_of_use', '')),
            ];
            if ($values['site_title'] === '' || mb_strlen($values['site_title']) > 100 || mb_strlen($values['site_tagline']) > 180 || mb_strlen($values['site_description']) > 1200 || mb_strlen($values['meta_description']) > 320 || mb_strlen($values['footer_text']) > 300 || mb_strlen($values['privacy_policy']) > 60000 || mb_strlen($values['terms_of_use']) > 60000 || ($values['support_email'] !== '' && !filter_var($values['support_email'], FILTER_VALIDATE_EMAIL))) {
                Session::flash('error', 'Enter a site title, keep the text within each field limit (legal pages: 60,000 characters), and use a valid support email.');
                return Response::redirect('/admin/store-settings');
            }
            try {
                StoreSettings::save($values);
            } catch (\Throwable) {
                Session::flash('error', 'Could not save store settings. Check that the database schema is up to date.');
                return Response::redirect('/admin/store-settings');
            }
            Session::flash('success', 'Store settings saved.');
            return Response::redirect('/admin/store-settings');
        }
        return $this->page('admin/store-products/settings', ['settings' => self::storeSettingsValues(), 'flash' => Session::flash('success'), 'error' => Session::flash('error')]);
    }

    public function payments(Request $request): Response
    {
        $this->requireAdmin();
        if ($request->method === 'POST') {
            Csrf::requireValid($request->post('_csrf') ?? null);
            $values = ['store_wallet_address' => strtolower(trim((string)$request->post('wallet', ''))), 'usdt_contract_address' => strtolower(trim((string)$request->post('contract', ''))), 'bsc_rpc_url' => trim((string)$request->post('rpc', ''))];
            foreach ($values as $key => $value) {
                if ($key !== 'bsc_rpc_url' && !preg_match('/^0x[a-f0-9]{40}$/', $value)) {
                    Session::flash('error', 'Enter valid 0x-prefixed wallet and token contract addresses.');
                    return Response::redirect('/admin/payments');
                }
                if ($key === 'bsc_rpc_url' && (!filter_var($value, FILTER_VALIDATE_URL) || strtolower((string)parse_url($value, PHP_URL_SCHEME)) !== 'https')) {
                    Session::flash('error', 'Enter a valid HTTPS RPC endpoint.');
                    return Response::redirect('/admin/payments');
                }
            }
            try {
                StoreSettings::save($values);
            } catch (\Throwable) {
                Session::flash('error', 'Could not save payment settings. Check that the database schema is up to date.');
                return Response::redirect('/admin/payments');
            }
            Session::flash('success', 'Payment settings saved.');
            return Response::redirect('/admin/payments');
        }
        return $this->page('admin/store-products/payments', ['settings' => self::paymentSettingsValues(), 'flash' => Session::flash('success'), 'error' => Session::flash('error')]);
    }

    public function orders(Request $request): Response
    {
        $this->requireAdmin();
        return $this->page('admin/store-products/orders', [
            'orders' => Db::fetchAll('SELECT o.*, c.email FROM store_orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC'),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function confirmOrder(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $order = Db::fetch('SELECT * FROM store_orders WHERE id=?', [(int)$params['id']]);
        if ($order === null) return Response::error('Order not found.', 404);
        if ($order['status'] !== 'pending') {
            Session::flash('error', 'Only pending orders can be confirmed.');
            return Response::redirect('/admin/orders');
        }
        $hash = strtolower(trim((string)$request->post('tx_hash', '')));
        if ($hash !== '' && !preg_match('/^0x[a-f0-9]{64}$/', $hash)) {
            Session::flash('error', 'Enter a valid transaction hash or leave it blank.');
            return Response::redirect('/admin/orders');
        }
        try {
            StoreOrderActivation::confirm((int)$order['id'], $hash !== '' ? $hash : null);
            UpdateLog::record('order_confirmed', 'success', 'Order manually confirmed by administrator.', null, (int)$order['customer_id'], null, $request->ip, ['order_id' => (int)$order['id'], 'order_code' => $order['order_code']]);
            Session::flash('success', 'Order confirmed and included product access activated.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not confirm order: ' . $e->getMessage());
        }
        return Response::redirect('/admin/orders');
    }

    public function rejectOrder(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $order = Db::fetch('SELECT * FROM store_orders WHERE id=?', [(int)$params['id']]);
        if ($order === null) return Response::error('Order not found.', 404);
        if ($order['status'] !== 'pending') {
            Session::flash('error', 'Only pending orders can be rejected.');
            return Response::redirect('/admin/orders');
        }

        Db::run("UPDATE store_orders SET status='rejected' WHERE id=? AND status='pending'", [(int)$order['id']]);
        UpdateLog::record('order_rejected', 'success', 'Order rejected by administrator.', null, (int)$order['customer_id'], null, $request->ip, ['order_id' => (int)$order['id'], 'order_code' => $order['order_code']]);
        Session::flash('success', 'Pending order rejected.');
        return Response::redirect('/admin/orders');
    }

    public function deleteOrder(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $order = Db::fetch('SELECT * FROM store_orders WHERE id=?', [(int)$params['id']]);
        if ($order === null) return Response::error('Order not found.', 404);
        Db::run('DELETE FROM store_orders WHERE id=?', [(int)$order['id']]);
        Session::flash('success', 'Order deleted.');
        return Response::redirect('/admin/orders');
    }

    /** plugin|theme → Plugin|Theme, anything else (including plan) → null. */
    private function categoryFor(string $type): ?string
    {
        if ($type !== 'plugin' && $type !== 'theme') {
            return null;
        }
        return StoreCatalog::categoryFromType($type);
    }

    public static function setting(string $key, mixed $fallback=''): mixed
    {
        $value = StoreSettings::get($key, null);
        if ($value !== null) return $value;
        return Config::get($key,$fallback);
    }
    private static function storeSettingsValues(): array { return ['site_title'=>StoreSettings::get('site_title',Config::get('site_name','Wordpresso')),'site_tagline'=>StoreSettings::get('site_tagline','Premium WordPress tools for your next great website.'),'site_description'=>StoreSettings::get('site_description','Browse plugins and themes in one catalog, then manage purchases and eligible plugin updates from your account.'),'meta_description'=>StoreSettings::get('meta_description','Browse WordPress plugins and themes, manage purchases, and connect eligible plugin updates.'),'support_email'=>StoreSettings::get('support_email',''),'footer_text'=>StoreSettings::get('footer_text','WordPress products, account access, and update connections in one place.'),'privacy_policy'=>StoreSettings::get('privacy_policy',''),'terms_of_use'=>StoreSettings::get('terms_of_use','')]; }
    private static function paymentSettingsValues(): array { return ['wallet'=>self::setting('store_wallet_address',''),'contract'=>self::setting('usdt_contract_address','0x55d398326f99059fF775485246999027B3197955'),'rpc'=>self::setting('bsc_rpc_url','https://bsc-dataseed.binance.org/')]; }
    private function page(string $template,array $vars=[]): Response { return Response::html(View::page('admin',$template,array_merge(['siteName'=>Config::get('site_name')],$vars))); }
}
