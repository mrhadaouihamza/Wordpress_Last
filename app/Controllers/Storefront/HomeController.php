<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Storefront;

use Wordpresso\Core\Config;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\StoreSettings;
use Wordpresso\Services\StoreCatalog;

/**
 * Public website pages: home, catalog, plugin detail, pricing.
 */
final class HomeController
{
    public function productImage(Request $request, array $params): Response
    {
        $type = strtolower((string) $params['type']);
        $product = StoreCatalog::find($type, (int) $params['id']);
        $image = $product['image_path'] ?? null;
        $path = $image ? \Wordpresso\Services\ProductImageStorage::absolutePath((string) $image) : null;
        if (!$path) return Response::error('Product image not found.', 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) { 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream' };
        $body = file_get_contents($path);
        if ($body === false) return Response::error('Product image not found.', 404);
        return new Response($body, 200, ['Content-Type'=>$mime, 'Content-Length'=>(string)strlen($body), 'Cache-Control'=>'public, max-age=3600']);
    }

    public function index(Request $request): Response
    {
        $products = StoreCatalog::listings(true);
        return Response::html(View::page('store', 'store/home', [
            'siteName' => StoreSettings::get('site_title', Config::get('site_name')),
            'storeTagline' => StoreSettings::get('site_tagline', 'Premium WordPress tools for your next great website.'),
            'storeDescription' => StoreSettings::get('site_description', 'Browse plugins and themes in one catalog, then manage purchases and eligible plugin updates from your account.'),
            'storeProducts' => $products,
            'themeCount' => count(array_filter($products,static fn($item)=>$item['category']==='Theme')),
            'pluginCount' => count(array_filter($products,static fn($item)=>$item['category']==='Plugin')),
            'customer' => self::currentCustomer(),
        ]));
    }

    public function catalog(Request $request): Response
    {
        return Response::redirect('/store');
    }

    public function plugin(Request $request, array $params): Response
    {
        $plugin = Plugin::findBySlug((string) $params['slug']);
        if ($plugin === null || (int) $plugin['enabled'] !== 1) {
            return Response::html(View::page('store', 'errors/404', [
                'siteName' => Config::get('site_name'),
                'customer' => self::currentCustomer(),
            ]), 404);
        }
        $current = \Wordpresso\Models\PluginVersion::current((int) $plugin['id']);
        return Response::html(View::page('store', 'store/plugin', [
            'siteName' => Config::get('site_name'),
            'plugin' => $plugin,
            'current' => $current,
            'customer' => self::currentCustomer(),
        ]));
    }

    public function product(Request $request, array $params): Response
    {
        $type = strtolower((string) $params['type']);
        $product = StoreCatalog::find($type, (int) ($params['id'] ?? 0));
        if ($product === null || (int) ($product['active'] ?? 0) !== 1) {
            return Response::html(View::page('store', 'errors/404', [
                'siteName' => Config::get('site_name'),
                'customer' => self::currentCustomer(),
            ]), 404);
        }
        return Response::html(View::page('store', 'store/product', [
            'siteName' => Config::get('site_name'),
            'product' => $product,
            'release' => $product['current_release'] ?? null,
            'customer' => self::currentCustomer(),
        ]));
    }

    public function pricing(Request $request): Response
    {
        return Response::html(View::page('store', 'store/pricing', [
            'siteName' => Config::get('site_name'),
            'customer' => self::currentCustomer(),
            'plans' => StoreCatalog::plans(true),
        ]));
    }

    public static function currentCustomer(): ?array
    {
        $id = Session::get('customer_id');
        return $id !== null ? \Wordpresso\Models\Customer::find((int) $id) : null;
    }
}
