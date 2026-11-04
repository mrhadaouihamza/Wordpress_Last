<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Storefront;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Db;
use Wordpresso\Core\RateLimiter;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Customer;
use Wordpresso\Models\Subscription;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Models\Website;
use Wordpresso\Services\StoreCatalog;

/**
 * Customer account area, organised like the admin panel: a shared account
 * layout with a section menu (Dashboard, Websites, Orders, Subscriptions,
 * Settings) wraps one controller action per section.
 *
 * Website credentials are created on the Websites page (key + secret shown
 * exactly once) and pasted into the Wordpresso Auto Updater plugin on the
 * customer's WordPress site.
 */
final class AccountController
{
    /** GET /account — access overview, latest orders, quick links. */
    public function index(Request $request): Response
    {
        $customer = $this->requireCustomer();
        $customerId = (int) $customer['id'];

        $subscriptions = Customer::subscriptions($customerId);
        $orders = $this->ordersForCustomer($customerId);
        $planOrders = $this->planOrdersForCustomer($customerId);
        $websites = Website::forCustomer($customerId);

        $activeSubscriptions = 0;
        foreach ($subscriptions as $subscription) {
            if ($subscription['status'] === 'active') {
                $activeSubscriptions++;
            }
        }
        // Pending payments and total spent cover both product orders and plan purchases.
        $pendingOrders = 0;
        $pendingPlanOrders = 0;
        $spent = 0.0;
        foreach (array_merge($orders, $planOrders) as $order) {
            if ($order['status'] === 'pending') {
                $pendingOrders++;
                if (!empty($order['is_plan_order'])) {
                    $pendingPlanOrders++;
                }
            }
            if ($order['status'] === 'paid') {
                $spent += (float) $order['amount'];
            }
        }

        return $this->page('store/account/dashboard', [
            'customer' => $customer,
            'stats' => [
                'active_subscriptions' => $activeSubscriptions,
                'websites' => count($websites),
                'orders' => count($orders),
                'pending_orders' => $pendingOrders,
                'pending_plans' => $pendingPlanOrders,
                'spent' => $spent,
            ],
            'recentOrders' => array_slice($orders, 0, 3),
        ]);
    }

    /** GET /account/websites — connected WordPress sites and API credentials. */
    public function websites(Request $request): Response
    {
        $customer = $this->requireCustomer();
        return $this->page('store/account/websites', [
            'customer' => $customer,
            'websites' => Website::forCustomer((int) $customer['id']),
            // Connecting a site requires owning a plan, plugin or theme.
            // There is no numeric website limit.
            'canAdd' => Customer::canAddWebsite((int) $customer['id']),
            'newCredentials' => Session::flash('new_credentials'),
        ]);
    }

    /** GET /account/products — products unlocked by plans or paid items. */
    public function products(Request $request): Response
    {
        $customer = $this->requireCustomer();
        $search = trim((string) $request->query('search', ''));
        $category = (string) $request->query('category', 'All');
        if (!in_array($category, ['All', 'Plugin', 'Theme'], true)) {
            $category = 'All';
        }
        $allProducts = $this->productsForCustomer((int) $customer['id']);
        return $this->page('store/account/products', [
            'customer' => $customer,
            'products' => StoreCatalog::refine($allProducts, $search, $category, 'featured'),
            'search' => $search,
            'category' => $category,
            'total' => count($allProducts),
        ]);
    }

    public function downloadProduct(Request $request, array $params): Response
    {
        $customer = $this->requireCustomer();
        $type = strtolower((string) ($params['type'] ?? ''));
        $id = (int) ($params['id'] ?? 0);
        if (!in_array($type, ['plugin', 'theme'], true) || !$this->customerCanAccessProduct((int) $customer['id'], $type, $id)) {
            return Response::error('Product download not found.', 403);
        }
        $product = StoreCatalog::find($type, $id);
        if ($product === null || empty($product['package_path'])) {
            return Response::error('The latest published release is not available yet.', 404);
        }
        $path = \Wordpresso\Services\PackageStorage::baseDir() . '/' . $product['package_path'];
        $real = realpath($path);
        $base = realpath(\Wordpresso\Services\PackageStorage::baseDir());
        if (!$real || !$base || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return Response::error('The product package is unavailable.', 404);
        }
        return Response::file($real, $product['slug'] . '.zip');
    }

    /** GET /account/orders — full order history with payment status. */
    public function orders(Request $request): Response
    {
        $customer = $this->requireCustomer();
        return $this->page('store/account/orders', [
            'customer' => $customer,
            'orders' => $this->ordersForCustomer((int) $customer['id']),
        ]);
    }

    /** GET /account/subscriptions — plan purchases and the access they grant. */
    public function subscriptions(Request $request): Response
    {
        $customer = $this->requireCustomer();
        return $this->page('store/account/subscriptions', [
            'customer' => $customer,
            'subscriptions' => $this->subscriptionsForCustomer((int) $customer['id']),
            'planOrders' => $this->planOrdersForCustomer((int) $customer['id']),
        ]);
    }

    /** GET /account/settings — profile, password and account deletion. */
    public function settings(Request $request): Response
    {
        $customer = $this->requireCustomer();
        $customerId = (int) $customer['id'];
        return $this->page('store/account/settings', [
            'customer' => $customer,
            'websiteCount' => Website::countForCustomer($customerId),
            'subscriptionCount' => count(Subscription::forCustomer($customerId)),
            'orderCount' => (int) Db::value('SELECT COUNT(*) FROM store_orders WHERE customer_id = ?', [$customerId]),
        ]);
    }

    /** POST /account/settings/profile */
    public function updateProfile(Request $request): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);

        if (!RateLimiter::attempt('account-profile:' . $customer['id'], 10, 3600)) {
            Session::flash('error', 'Too many update attempts. Please try again later.');
            return Response::redirect('/account/settings');
        }

        $email = strtolower(trim((string) $request->post('email', '')));
        $name = trim((string) $request->post('name', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            return Response::redirect('/account/settings');
        }
        $existing = Customer::findByEmail($email);
        if ($existing !== null && (int) $existing['id'] !== (int) $customer['id']) {
            Session::flash('error', 'Another account already uses this email address.');
            return Response::redirect('/account/settings');
        }

        Customer::update((int) $customer['id'], ['email' => $email, 'name' => $name]);
        Session::set('customer_email', $email);
        Session::flash('success', 'Your profile was updated.');
        return Response::redirect('/account/settings');
    }

    /** POST /account/settings/password (also serves the legacy /account/password route) */
    public function changePassword(Request $request): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $current = (string) $request->post('current_password', '');
        $new = (string) $request->post('new_password', '');
        if (!password_verify($current, $customer['password_hash'])) {
            Session::flash('error', 'Your current password is incorrect.');
            return Response::redirect('/account/settings');
        }
        if (strlen($new) < 10) {
            Session::flash('error', 'The new password must be at least 10 characters long.');
            return Response::redirect('/account/settings');
        }
        Customer::setPassword((int) $customer['id'], password_hash($new, PASSWORD_DEFAULT));
        Session::regenerate();
        Session::flash('success', 'Password changed.');
        return Response::redirect('/account/settings');
    }

    /** GET /account/password — legacy route; the form moved to Settings. */
    public function passwordRedirect(Request $request): Response
    {
        $this->requireCustomer();
        return Response::redirect('/account/settings');
    }

    /** POST /account/delete — permanent, password-confirmed account deletion. */
    public function deleteAccount(Request $request): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);

        if (!RateLimiter::attempt('account-delete:' . $customer['id'], 3, 3600)) {
            Session::flash('error', 'Too many attempts. Please try again in an hour.');
            return Response::redirect('/account/settings');
        }
        if ((string) $request->post('confirm', '') !== '1') {
            Session::flash('error', 'Please confirm that you understand this action is permanent.');
            return Response::redirect('/account/settings');
        }
        if (!password_verify((string) $request->post('password', ''), $customer['password_hash'])) {
            Session::flash('error', 'Your password is incorrect — the account was not deleted.');
            return Response::redirect('/account/settings');
        }

        $customerId = (int) $customer['id'];
        $customerEmail = (string) $customer['email'];

        Customer::deleteAccount($customerId);
        // Audit trail for the store operator; the log keeps only the numeric id.
        UpdateLog::record('account_delete', 'success', 'Customer permanently deleted their own account.', null, $customerId, null, $request->ip, ['email_hash' => substr(hash('sha256', $customerEmail), 0, 16)]);

        Session::destroy();
        return Response::redirect('/');
    }

    // --- Website credential handlers (Websites page forms) -------------------

    public function addWebsite(Request $request): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);

        if (!RateLimiter::attempt('website-add:' . $customer['id'], 10, 3600)) {
            Session::flash('error', 'Too many website registrations. Please try again later.');
            return Response::redirect('/account/websites');
        }

        if (!Customer::canAddWebsite((int) $customer['id'])) {
            Session::flash('error', Customer::ownsPlanOrProduct((int) $customer['id'])
                ? 'Your plan has reached its website limit.'
                : 'You need an active plan, plugin, or theme before adding a website.');
            return Response::redirect('/account/websites');
        }

        $name = trim((string) $request->post('name', ''));
        $url = rtrim(trim((string) $request->post('url', '')), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'http')) {
            Session::flash('error', 'Please enter a valid site URL including https://');
            return Response::redirect('/account/websites');
        }

        [$website, $secret] = Website::create((int) $customer['id'], $name !== '' ? $name : $url, $url);
        UpdateLog::record('website_create', 'success', 'Website credentials issued.', (int) $website['id'], (int) $customer['id'], null, $request->ip, ['url' => $url]);

        // Show the secret exactly once.
        Session::flash('new_credentials', json_encode([
            'url' => $url,
            'api_key' => $website['api_key'],
            'api_secret' => $secret,
        ]));
        return Response::redirect('/account/websites');
    }

    public function deleteWebsite(Request $request, array $params): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $website = $this->ownWebsite($params, $customer);
        if ($website === null) {
            return Response::error('Website not found.', 404);
        }
        UpdateLog::record('website_delete', 'success', 'Website and API credentials permanently deleted by customer.', (int)$website['id'], (int)$customer['id'], null, $request->ip);
        Website::delete((int)$website['id']);
        Session::flash('success', 'Website and its API credentials were permanently deleted.');
        return Response::redirect('/account/websites');
    }

    public function rotateSecret(Request $request, array $params): Response
    {
        $customer = $this->requireCustomer();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $website = $this->ownWebsite($params, $customer);
        if ($website === null) {
            return Response::error('Website not found.', 404);
        }
        $secret = Website::rotateSecret((int) $website['id']);
        UpdateLog::record('website_rotate', 'success', 'API secret rotated.', (int) $website['id'], (int) $customer['id'], null, $request->ip);
        Session::flash('new_credentials', json_encode([
            'url' => $website['url'],
            'api_key' => $website['api_key'],
            'api_secret' => $secret,
        ]));
        Session::flash('success', 'New secret generated — update it in the plugin settings. The old secret stopped working immediately.');
        return Response::redirect('/account/websites');
    }

    // --- Helpers --------------------------------------------------------------

    /** Render a template inside the shared account layout with flash messages. */
    private function page(string $template, array $vars): Response
    {
        $titles = [
            'store/account/dashboard' => 'Dashboard',
            'store/account/websites' => 'Websites',
            'store/account/orders' => 'Orders',
            'store/account/subscriptions' => 'Subscriptions',
            'store/account/settings' => 'Settings',
        ];
        return Response::html(View::page('account', $template, array_merge([
            'siteName' => Config::get('site_name'),
            'title' => $titles[$template] ?? 'My account',
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ], $vars)));
    }

    /**
     * Product orders (plugins/themes), newest first. Orders that contain a
     * subscription plan are plan purchases and appear on the Subscriptions
     * page instead.
     */
    private function ordersForCustomer(int $customerId): array
    {
        $orders = Db::fetchAll(
            "SELECT * FROM store_orders o
             WHERE o.customer_id = ?
               AND NOT EXISTS (SELECT 1 FROM store_order_items i WHERE i.order_id = o.id AND i.category = 'Plan')
             ORDER BY o.created_at DESC",
            [$customerId]
        );
        foreach ($orders as &$order) {
            $order['items'] = StoreCatalog::attachDownloadInfo(
                Db::fetchAll('SELECT * FROM store_order_items WHERE order_id = ?', [$order['id']])
            );
        }
        unset($order);
        return $orders;
    }

    private function productsForCustomer(int $customerId): array
    {
            $products = [];
            $activePlan = Db::value(
                "SELECT id FROM subscriptions
                 WHERE customer_id = ? AND plan_id IS NOT NULL AND status = 'active'
                   AND starts_at <= CURRENT_TIMESTAMP
                   AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
                 LIMIT 1",
                [$customerId]
            );
            if ($activePlan !== null) {
                foreach (StoreCatalog::listings(true) as $product) {
                    $products[$product['type'] . ':' . $product['id']] = $product;
                }
            }
            $rows = Db::fetchAll(
                "SELECT DISTINCT i.category, i.product_id
                 FROM store_order_items i
                 JOIN store_orders o ON o.id = i.order_id
                 WHERE o.customer_id = ? AND o.status = 'paid'
                   AND i.category IN ('Plugin', 'Theme')",
                [$customerId]
            );
            foreach ($rows as $row) {
                $type = StoreCatalog::typeFromCategory((string) $row['category']);
                if ($type === null) {
                    continue;
                }
                $product = StoreCatalog::find($type, (int) $row['product_id']);
                if ($product !== null) {
                    $products[$type . ':' . $product['id']] = $product;
                }
            }
            usort($products, static fn(array $a, array $b): int => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);
            return array_values($products);
    }

    private function customerCanAccessProduct(int $customerId, string $type, int $id): bool
    {
            $category = StoreCatalog::categoryFromType($type);
            if ($category === null) {
                return false;
            }
            if (Db::value(
                "SELECT i.id FROM store_order_items i JOIN store_orders o ON o.id = i.order_id
                 WHERE o.customer_id = ? AND o.status = 'paid' AND i.category = ? AND i.product_id = ?",
                [$customerId, $category, $id]
            ) !== null) {
                return true;
            }
            $product = StoreCatalog::find($type, $id);
            if ($product === null || (int) $product['active'] !== 1) {
                return false;
            }
            return Db::value(
                "SELECT s.id FROM subscriptions s
                 WHERE s.customer_id = ? AND s.plan_id IS NOT NULL AND s.status = 'active'
                   AND s.starts_at <= CURRENT_TIMESTAMP
                   AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)
                 LIMIT 1",
                [$customerId]
            ) !== null;
    }

    /** Subscription plan purchases, newest first, each with its items. */
    private function planOrdersForCustomer(int $customerId): array
    {
        $orders = Db::fetchAll(
            "SELECT o.*, 1 AS is_plan_order
             FROM store_orders o
             WHERE o.customer_id = ?
               AND EXISTS (SELECT 1 FROM store_order_items i WHERE i.order_id = o.id AND i.category = 'Plan')
             ORDER BY o.created_at DESC",
            [$customerId]
        );
        foreach ($orders as &$order) {
            $order['items'] = Db::fetchAll(
                'SELECT * FROM store_order_items WHERE order_id = ?',
                [$order['id']]
            );
            $order['plan_name'] = '';
            foreach ($order['items'] as $item) {
                if ($item['category'] === 'Plan') {
                    $order['plan_name'] = (string) $item['product_name'];
                    break;
                }
            }
        }
        unset($order);
        return $orders;
    }

    /**
     * Subscriptions with included products, newest first. Store purchases are
     * linked back to their order via the order code stored in the plan name
     * (e.g. "Pro Yearly (WPS-1A2B3C4D5E)").
     */
    private function subscriptionsForCustomer(int $customerId): array
    {
        $subscriptions = Customer::subscriptions($customerId);
        foreach ($subscriptions as &$subscription) {
            $subscription['included_products'] = Subscription::storeProducts((int) $subscription['id']);
            $subscription['order'] = null;
            $subscription['display_name'] = (string) $subscription['plan_name'];
            if (preg_match('/^(.*?)\s*\((WPS-[A-F0-9]{10})\)$/', (string) $subscription['plan_name'], $m)) {
                $order = Db::fetch(
                    'SELECT * FROM store_orders WHERE order_code = ? AND customer_id = ?',
                    [$m[2], $customerId]
                );
                if ($order !== null) {
                    $subscription['order'] = $order;
                    $subscription['display_name'] = $m[1];
                }
            }
        }
        unset($subscription);
        return $subscriptions;
    }

    private function requireCustomer(): array
    {
        $id = Session::get('customer_id');
        if ($id === null) {
            Response::redirect('/login')->send();
            exit;
        }
        $customer = Customer::find((int) $id);
        if ($customer === null || $customer['status'] !== 'active') {
            Session::destroy();
            Response::redirect('/login')->send();
            exit;
        }
        return $customer;
    }

    private function ownWebsite(array $params, array $customer): ?array
    {
        $website = Website::find((int) ($params['id'] ?? 0));
        if ($website === null || (int) $website['customer_id'] !== (int) $customer['id']) {
            return null;
        }
        return $website;
    }
}
