<?php

declare(strict_types=1);

/**
 * Wordpresso Auto Updater — front controller.
 *
 * Point the web server docroot at this directory. Everything else
 * (config, app code, storage) lives one level up and is NOT web-accessible.
 */

define('WORDPRESSO_ROOT', __DIR__);
define('WORDPRESSO_PUBLIC_DIR', __DIR__);
define('WORDPRESSO_START', microtime(true));

require WORDPRESSO_ROOT . '/app/bootstrap.php';

use Wordpresso\Core\Config;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Router;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;

Config::load(WORDPRESSO_ROOT . '/config/config.php');
\Wordpresso\Core\View::init(WORDPRESSO_ROOT . '/templates');

$request = Request::capture();

// Security: refuse plain HTTP when running behind TLS termination handled by
// the app itself (the front controller is the only public entry point).
$router = new Router();

// --- Storefront (public website) -------------------------------------------
$router->get('/', [\Wordpresso\Controllers\Storefront\HomeController::class, 'index']);
$router->get('/plugins', [\Wordpresso\Controllers\Storefront\HomeController::class, 'catalog']);
$router->get('/plugins/{slug}', [\Wordpresso\Controllers\Storefront\HomeController::class, 'plugin']);
$router->get('/product/{type}/{id}', [\Wordpresso\Controllers\Storefront\HomeController::class, 'product']);
$router->get('/plans', [\Wordpresso\Controllers\Storefront\HomeController::class, 'pricing']);
$router->get('/pricing', [\Wordpresso\Controllers\Storefront\HomeController::class, 'pricing']);
$router->get('/products/{type}/{id}/image', [\Wordpresso\Controllers\Storefront\HomeController::class, 'productImage']);
$router->get('/store', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'catalog']);
$router->get('/privacy', [\Wordpresso\Controllers\Storefront\LegalController::class, 'privacy']);
$router->get('/terms', [\Wordpresso\Controllers\Storefront\LegalController::class, 'terms']);
$router->post('/cart/add', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'add']);
$router->get('/cart', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'cart']);
$router->post('/cart/remove', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'remove']);
$router->post('/checkout', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'checkout']);
$router->get('/checkout/{code}', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'payment']);
$router->post('/checkout/{code}/verify', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'verify']);
$router->post('/checkout/{code}/cancel', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'cancel']);
$router->post('/account/orders/{code}/cancel', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'cancel']);
$router->get('/account/orders/{code}/download/{type}/{id}', [\Wordpresso\Controllers\Storefront\CommerceController::class, 'download']);
$router->get('/admin/store-products', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'index']);
$router->get('/admin/products', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'index']);
$router->get('/admin/orders', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'orders']);
$router->post('/admin/orders/{id}/confirm', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'confirmOrder']);
$router->post('/admin/orders/{id}/reject', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'rejectOrder']);
$router->post('/admin/orders/{id}/delete', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'deleteOrder']);
$router->get('/admin/store-products/new', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'create']);
$router->get('/admin/products/new', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'create']);
$router->post('/admin/store-products', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'store']);
$router->post('/admin/products', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'store']);
$router->get('/admin/store-products/{type}/{id}', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'edit']);
$router->get('/admin/products/{type}/{id}', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'edit']);
$router->post('/admin/store-products/{type}/{id}', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'store']);
$router->post('/admin/products/{type}/{id}', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'store']);
$router->post('/admin/store-products/{type}/{id}/toggle', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'toggle']);
$router->post('/admin/products/{type}/{id}/toggle', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'toggle']);
$router->post('/admin/store-products/{type}/{id}/delete', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'deleteProduct']);
$router->post('/admin/products/{type}/{id}/delete', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'deleteUnlistedPlugin']);
$router->post('/admin/themes/{id}/versions', [\Wordpresso\Controllers\Admin\ThemeVersionController::class, 'upload']);
$router->post('/admin/theme-versions/{id}/publish', [\Wordpresso\Controllers\Admin\ThemeVersionController::class, 'publish']);
$router->post('/admin/theme-versions/{id}/withdraw', [\Wordpresso\Controllers\Admin\ThemeVersionController::class, 'withdraw']);
$router->post('/admin/theme-versions/{id}/delete', [\Wordpresso\Controllers\Admin\ThemeVersionController::class, 'delete']);
$router->get('/admin/migrations', [\Wordpresso\Controllers\Admin\MigrationController::class, 'index']);
$router->post('/admin/migrations/run', [\Wordpresso\Controllers\Admin\MigrationController::class, 'run']);
$router->get('/admin/store-settings', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'settings']);
$router->post('/admin/store-settings', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'settings']);
$router->get('/admin/payments', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'payments']);
$router->post('/admin/payments', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'payments']);

$router->get('/login', [\Wordpresso\Controllers\Storefront\AuthController::class, 'loginForm']);
$router->post('/login', [\Wordpresso\Controllers\Storefront\AuthController::class, 'login']);
$router->get('/register', [\Wordpresso\Controllers\Storefront\AuthController::class, 'registerForm']);
$router->post('/register', [\Wordpresso\Controllers\Storefront\AuthController::class, 'register']);
$router->post('/logout', [\Wordpresso\Controllers\Storefront\AuthController::class, 'logout']);

$router->get('/account', [\Wordpresso\Controllers\Storefront\AccountController::class, 'index']);
$router->get('/account/websites', [\Wordpresso\Controllers\Storefront\AccountController::class, 'websites']);
$router->get('/account/products', [\Wordpresso\Controllers\Storefront\AccountController::class, 'products']);
$router->get('/account/products/download/{type}/{id}', [\Wordpresso\Controllers\Storefront\AccountController::class, 'downloadProduct']);
$router->post('/account/websites', [\Wordpresso\Controllers\Storefront\AccountController::class, 'addWebsite']);
$router->post('/account/websites/{id}/delete', [\Wordpresso\Controllers\Storefront\AccountController::class, 'deleteWebsite']);
$router->post('/account/websites/{id}/rotate', [\Wordpresso\Controllers\Storefront\AccountController::class, 'rotateSecret']);
$router->get('/account/orders', [\Wordpresso\Controllers\Storefront\AccountController::class, 'orders']);
$router->get('/account/subscriptions', [\Wordpresso\Controllers\Storefront\AccountController::class, 'subscriptions']);
$router->get('/account/settings', [\Wordpresso\Controllers\Storefront\AccountController::class, 'settings']);
$router->post('/account/settings/profile', [\Wordpresso\Controllers\Storefront\AccountController::class, 'updateProfile']);
$router->post('/account/settings/password', [\Wordpresso\Controllers\Storefront\AccountController::class, 'changePassword']);
$router->post('/account/delete', [\Wordpresso\Controllers\Storefront\AccountController::class, 'deleteAccount']);
$router->get('/account/password', [\Wordpresso\Controllers\Storefront\AccountController::class, 'passwordRedirect']);
$router->post('/account/password', [\Wordpresso\Controllers\Storefront\AccountController::class, 'changePassword']);

// --- Admin dashboard ---------------------------------------------------------
$router->get('/admin/login', [\Wordpresso\Controllers\Admin\LoginController::class, 'form']);
$router->post('/admin/login', [\Wordpresso\Controllers\Admin\LoginController::class, 'login']);
$router->post('/admin/logout', [\Wordpresso\Controllers\Admin\LoginController::class, 'logout']);

$router->get('/admin', [\Wordpresso\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/updates', [\Wordpresso\Controllers\Admin\UpdaterController::class, 'index']);
$router->post('/admin/plugins/{id}/auto-updates', [\Wordpresso\Controllers\Admin\UpdaterController::class, 'toggleAutoUpdates']);

$router->get('/admin/plugins', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'index']);
$router->get('/admin/plugins/new', [\Wordpresso\Controllers\Admin\StoreProductController::class, 'create']);
$router->get('/admin/plugins/{id}', [\Wordpresso\Controllers\Admin\PluginController::class, 'show']);
$router->post('/admin/plugins/{id}', [\Wordpresso\Controllers\Admin\PluginController::class, 'update']);
$router->post('/admin/plugins/{id}/toggle', [\Wordpresso\Controllers\Admin\PluginController::class, 'toggle']);
$router->post('/admin/plugins/{id}/versions', [\Wordpresso\Controllers\Admin\VersionController::class, 'upload']);
$router->post('/admin/versions/{id}/publish', [\Wordpresso\Controllers\Admin\VersionController::class, 'publish']);
$router->post('/admin/versions/{id}/withdraw', [\Wordpresso\Controllers\Admin\VersionController::class, 'withdraw']);
$router->post('/admin/versions/{id}/rollback', [\Wordpresso\Controllers\Admin\VersionController::class, 'rollback']);
$router->post('/admin/versions/{id}/changelog', [\Wordpresso\Controllers\Admin\VersionController::class, 'saveChangelog']);
$router->post('/admin/versions/{id}/delete', [\Wordpresso\Controllers\Admin\VersionController::class, 'delete']);

$router->get('/admin/customers', [\Wordpresso\Controllers\Admin\CustomerController::class, 'index']);
$router->get('/admin/subscriptions', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'index']);
$router->get('/admin/subscriptions/new', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'create']);
$router->post('/admin/subscriptions', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'save']);
$router->get('/admin/subscriptions/{id}/edit', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'edit']);
$router->post('/admin/subscriptions/{id}', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'save']);
$router->post('/admin/subscriptions/{id}/toggle', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'toggle']);
$router->post('/admin/subscription-plans/{id}/delete', [\Wordpresso\Controllers\Admin\SubscriptionController::class, 'delete']);
$router->get('/admin/customers/new', [\Wordpresso\Controllers\Admin\CustomerController::class, 'create']);
$router->post('/admin/customers', [\Wordpresso\Controllers\Admin\CustomerController::class, 'store']);
$router->get('/admin/customers/{id}', [\Wordpresso\Controllers\Admin\CustomerController::class, 'show']);
$router->get('/admin/customers/{id}/edit', [\Wordpresso\Controllers\Admin\CustomerController::class, 'edit']);
$router->post('/admin/customers/{id}', [\Wordpresso\Controllers\Admin\CustomerController::class, 'update']);
$router->post('/admin/customers/{id}/suspend', [\Wordpresso\Controllers\Admin\CustomerController::class, 'toggleSuspend']);
$router->post('/admin/customers/{id}/delete', [\Wordpresso\Controllers\Admin\CustomerController::class, 'delete']);
$router->post('/admin/customers/{id}/subscriptions', [\Wordpresso\Controllers\Admin\CustomerController::class, 'saveSubscription']);
$router->post('/admin/customers/{id}/subscriptions/assign', [\Wordpresso\Controllers\Admin\CustomerController::class, 'assignPlan']);
$router->post('/admin/subscriptions/{id}/delete', [\Wordpresso\Controllers\Admin\CustomerController::class, 'deleteSubscription']);

$router->get('/admin/websites', [\Wordpresso\Controllers\Admin\WebsiteController::class, 'index']);
$router->post('/admin/websites/{id}/revoke', [\Wordpresso\Controllers\Admin\WebsiteController::class, 'revoke']);
$router->post('/admin/websites/{id}/activate', [\Wordpresso\Controllers\Admin\WebsiteController::class, 'activate']);
$router->post('/admin/websites/{id}/delete', [\Wordpresso\Controllers\Admin\WebsiteController::class, 'delete']);

$router->get('/admin/logs', [\Wordpresso\Controllers\Admin\LogController::class, 'index']);
$router->post('/admin/logs/prune', [\Wordpresso\Controllers\Admin\LogController::class, 'prune']);

// --- Update API v1 -----------------------------------------------------------
$router->post('/api/v1/register', [\Wordpresso\Controllers\Api\WebsiteController::class, 'register']);
$router->post('/api/v1/updates', [\Wordpresso\Controllers\Api\UpdateController::class, 'check']);
$router->post('/api/v1/package', [\Wordpresso\Controllers\Api\PackageController::class, 'authorize']);
$router->get('/api/v1/package/{token}', [\Wordpresso\Controllers\Api\PackageController::class, 'download']);
$router->post('/api/v1/status', [\Wordpresso\Controllers\Api\UpdateController::class, 'status']);

// --- Static assets (only the tiny public assets directory) -------------------
if (str_starts_with($request->path, '/assets/')) {
    $asset = basename($request->path);
    $file = WORDPRESSO_PUBLIC_DIR . '/assets/' . $asset;
    if (is_file($file) && preg_match('/\.(css|js|png|svg)$/', $asset)) {
        $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
        };
        Response::new((string) file_get_contents($file), 200, ['Content-Type' => $mime])->send();
        exit;
    }
}

// --- Dispatch -----------------------------------------------------------------
$matched = $router->match($request->method, $request->path);

if ($matched === null) {
    if (str_starts_with($request->path, '/api/')) {
        Response::error('Not found.', 404)->send();
    } else {
        Response::html(View::page('store', 'errors/404', ['siteName' => Config::get('site_name')]), 404)->send();
    }
    exit;
}

[$handler, $params] = $matched;
[$class, $action] = $handler;

try {
    $controller = new $class();
    $response = $controller->$action($request, $params);
    if ($response instanceof Response) {
        $response->send();
    }
} catch (\Throwable $e) {
    error_log('[wordpresso] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (str_starts_with($request->path, '/api/')) {
        Response::error('Internal server error.', 500)->send();
    } else {
        Response::html(View::page('store', 'errors/500', [
            'siteName' => Config::get('site_name'),
            'message' => $e->getMessage(),
        ]), 500)->send();
    }
}
