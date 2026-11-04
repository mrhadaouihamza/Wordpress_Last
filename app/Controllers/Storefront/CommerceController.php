<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Storefront;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Db;
use Wordpresso\Core\Request;
use Wordpresso\Core\RateLimiter;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Services\StoreCatalog;
use Wordpresso\Services\StoreOrderActivation;

final class CommerceController
{
    public function catalog(Request $request): Response
    {
        $category = (string) $request->query('category', 'All');
        if (!in_array($category, ['All', 'Plugin', 'Theme'], true)) {
            $category = 'All';
        }
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'featured');
        if (!in_array($sort, ['featured', 'name', 'price-asc', 'price-desc'], true)) {
            $sort = 'featured';
        }
        $products = StoreCatalog::refine(StoreCatalog::listings(true), $search, $category, $sort);
        return $this->page('store/shop', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
        ]);
    }

    public function add(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        $key = (string) $request->post('product_key', '');
        $parsed = StoreCatalog::parseKey($key);
        $product = $parsed === null ? null : StoreCatalog::find($parsed['type'], $parsed['id']);
        if ($product === null || (int) $product['active'] !== 1) {
            Session::flash('error', 'That product is not available.');
            return Response::redirect('/store');
        }
        $cart = (array) Session::get('cart', []);
        $cart[$key] = 1;
        Session::set('cart', $cart);
        Session::flash('success', 'Added to your cart.');
        return Response::redirect('/cart');
    }

    public function cart(Request $request): Response
    {
        $items = [];
        foreach ($this->resolveCart((array) Session::get('cart', [])) as $key => $quantity) {
            $parsed = StoreCatalog::parseKey($key);
            if ($parsed === null) {
                continue;
            }
            $product = StoreCatalog::find($parsed['type'], $parsed['id']);
            if ($product === null || (int) $product['active'] !== 1) {
                continue;
            }
            $product['quantity'] = $quantity;
            $product['key'] = $key;
            $items[] = $product;
        }

        return $this->page('store/cart', [
            'items' => $items,
            'total' => array_sum(array_map(static fn($i) => (float) $i['price'] * $i['quantity'], $items)),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function remove(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        $cart = (array) Session::get('cart', []);
        unset($cart[(string) $request->post('product_key', '')]);
        Session::set('cart', $cart);
        return Response::redirect('/cart');
    }

    public function checkout(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customerId = (int) Session::get('customer_id', 0);
        if (!$customerId) {
            Session::flash('error', 'Sign in or create an account before checkout.');
            return Response::redirect('/login');
        }
        $cart = $this->resolveCart((array) Session::get('cart', []));
        if (!$cart) {
            Session::flash('error', 'Your cart is empty.');
            return Response::redirect('/cart');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $rows = [];
            foreach ($cart as $key => $quantity) {
                $parsed = StoreCatalog::parseKey($key);
                $product = $parsed === null ? null : StoreCatalog::find($parsed['type'], $parsed['id']);
                if ($product === null || (int) $product['active'] !== 1) {
                    throw new \RuntimeException('A cart item is no longer available. Please review your cart.');
                }
                $product['quantity'] = $quantity;
                $rows[] = $product;
            }

            $total = 0.0;
            foreach ($rows as $row) {
                $total += (float) $row['price'] * (int) $row['quantity'];
            }
            if ($total <= 0) {
                throw new \RuntimeException('Order total must be greater than zero.');
            }

            $code = 'WPS-' . strtoupper(bin2hex(random_bytes(5)));
            $orderId = Db::insert(
                "INSERT INTO store_orders (order_code, customer_id, amount, currency, status, payment_expires_at, created_at) VALUES (?, ?, ?, 'USDT', 'pending', ?, ?)",
                [$code, $customerId, number_format($total, 2, '.', ''), gmdate('Y-m-d H:i:s', time() + 3600), gmdate('Y-m-d H:i:s')]
            );
            foreach ($rows as $row) {
                Db::run(
                    'INSERT INTO store_order_items (order_id, product_id, product_name, category, unit_price, quantity) VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $row['id'], $row['name'], $row['category'], $row['price'], (int) $row['quantity']]
                );
            }
            $pdo->commit();
            return Response::redirect('/checkout/' . rawurlencode($code));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Session::flash('error', $e->getMessage());
            return Response::redirect('/cart');
        }
    }

    public function payment(Request $request, array $params): Response
    {
        $order = $this->ownedOrder((string) $params['code']);
        if (!$order) {
            return Response::error('Order not found.', 404);
        }
        if ($order['status'] === 'paid') {
            Session::set('cart', []);
        }
        $order['items'] = StoreCatalog::attachDownloadInfo(
            Db::fetchAll('SELECT * FROM store_order_items WHERE order_id = ?', [$order['id']])
        );
        $order['included_products'] = [];
        if ($order['status'] === 'paid' && Db::value("SELECT id FROM store_order_items WHERE order_id = ? AND category = 'Plan'", [$order['id']]) !== null) {
            $order['included_products'] = $this->planPackages((int) $order['customer_id'], (string) $order['order_code']);
        }

        return $this->page('store/checkout', [
            'order' => $order,
            'wallet' => \Wordpresso\Controllers\Admin\StoreProductController::setting('store_wallet_address', ''),
            'contract' => \Wordpresso\Controllers\Admin\StoreProductController::setting('usdt_contract_address', '0x55d398326f99059fF775485246999027B3197955'),
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ]);
    }

    public function verify(Request $request, array $params): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        if (!RateLimiter::attempt('store-payment-verify:' . $request->ip, 15, 300)) {
            Session::flash('error', 'Too many verification attempts. Wait a few minutes and try again.');
            return Response::redirect('/checkout/' . rawurlencode((string) $params['code']));
        }
        $order = $this->ownedOrder((string) $params['code']);
        if (!$order || $order['status'] !== 'pending') {
            return Response::error('Pending order not found.', 404);
        }
        if (strtotime((string) $order['payment_expires_at'] . ' UTC') < time()) {
            Session::flash('error', 'This payment request expired. Please create a new order from your cart.');
            return Response::redirect('/cart');
        }
        $hash = strtolower(trim((string) $request->post('tx_hash', '')));
        if (!preg_match('/^0x[a-f0-9]{64}$/', $hash)) {
            Session::flash('error', 'Enter a valid transaction hash.');
            return Response::redirect('/checkout/' . rawurlencode($order['order_code']));
        }
        try {
            $this->verifyBep20Transfer($hash, (float) $order['amount'], (string) $order['created_at'], (string) $order['payment_expires_at']);
            StoreOrderActivation::confirm((int) $order['id'], $hash);
            Session::set('cart', []);
            Session::flash('success', 'Payment confirmed. Your purchased access is now active.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Payment was not verified: ' . $e->getMessage());
        }
        return Response::redirect('/checkout/' . rawurlencode($order['order_code']));
    }

    /**
     * Let a signed-in customer cancel their own order while it is still
     * waiting for payment. Paid, rejected or already cancelled orders are
     * left untouched; the cart is deliberately kept so the customer can try
     * again without re-adding products.
     */
    public function cancel(Request $request, array $params): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);

        $customerId = (int) Session::get('customer_id', 0);
        if (!$customerId) {
            Session::flash('error', 'Sign in to manage your orders.');
            return Response::redirect('/login');
        }

        $code = (string) ($params['code'] ?? '');
        $order = Db::fetch(
            'SELECT * FROM store_orders WHERE order_code = ? AND customer_id = ?',
            [$code, $customerId]
        );
        if ($order === null) {
            return Response::error('Order not found.', 404);
        }

        $isPlanOrder = Db::value(
            "SELECT id FROM store_order_items WHERE order_id = ? AND category = 'Plan'",
            [(int) $order['id']]
        ) !== null;
        $destination = $isPlanOrder ? '/account/subscriptions' : '/account/orders';

        if ($order['status'] !== 'pending') {
            Session::flash('error', 'Only orders that are still awaiting payment can be cancelled.');
            return Response::redirect($destination);
        }

        $updated = Db::run(
            "UPDATE store_orders SET status = 'cancelled' WHERE id = ? AND customer_id = ? AND status = 'pending'",
            [(int) $order['id'], $customerId]
        );
        if ($updated->rowCount() !== 1) {
            Session::flash('error', 'This order could not be cancelled because it is no longer pending.');
            return Response::redirect($destination);
        }

        Session::flash('success', 'Order ' . $code . ' was cancelled.');
        return Response::redirect($destination);
    }

    private function verifyBep20Transfer(string $hash, float $expected, string $createdAt, string $expiresAt): void
    {
        $wallet = strtolower(trim((string) \Wordpresso\Controllers\Admin\StoreProductController::setting('store_wallet_address', '')));
        $contract = strtolower(trim((string) \Wordpresso\Controllers\Admin\StoreProductController::setting('usdt_contract_address', '0x55d398326f99059fF775485246999027B3197955')));
        $rpc = (string) \Wordpresso\Controllers\Admin\StoreProductController::setting('bsc_rpc_url', 'https://bsc-dataseed.binance.org/');
        if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet) || !preg_match('/^0x[a-f0-9]{40}$/', $contract)) {
            throw new \RuntimeException('Store payment wallet is not configured.');
        }
        if (Db::value('SELECT id FROM store_orders WHERE tx_hash = ?', [$hash])) {
            throw new \RuntimeException('This transaction has already been used.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is required to verify payments.');
        }
        $rpcCall = static function (string $method, array $params) use ($rpc) {
            $ch = curl_init($rpc);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]),
                CURLOPT_TIMEOUT => 15,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if (!$raw || $status >= 400) {
                throw new \RuntimeException('Could not reach the BNB Smart Chain RPC: ' . ($error ?: 'HTTP ' . $status));
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || isset($data['error'])) {
                throw new \RuntimeException('The RPC could not verify this transaction.');
            }
            return $data['result'] ?? [];
        };
        if (hexdec((string) $rpcCall('eth_chainId', [])) !== 56) {
            throw new \RuntimeException('The configured RPC is not connected to BNB Smart Chain mainnet.');
        }
        $receipt = $rpcCall('eth_getTransactionReceipt', [$hash]);
        if (!$receipt || ($receipt['status'] ?? '') !== '0x1') {
            throw new \RuntimeException('Transaction is not confirmed successfully on BNB Smart Chain.');
        }
        $blockData = $rpcCall('eth_getBlockByNumber', [(string) ($receipt['blockNumber'] ?? '0x0'), false]);
        $txTime = isset($blockData['timestamp']) ? hexdec((string) $blockData['timestamp']) : 0;
        if ($txTime < strtotime($createdAt . ' UTC') || $txTime > strtotime($expiresAt . ' UTC')) {
            throw new \RuntimeException('This transfer was not made during this order’s payment window.');
        }
        $head = hexdec((string) $rpcCall('eth_blockNumber', []));
        $block = hexdec((string) ($receipt['blockNumber'] ?? '0x0'));
        if ($head - $block + 1 < 12) {
            throw new \RuntimeException('Transaction needs at least 12 block confirmations.');
        }
        $decimalsResult = $rpcCall('eth_call', [['to' => $contract, 'data' => '0x313ce567'], 'latest']);
        $decimals = hexdec((string) $decimalsResult);
        if ($decimals < 0 || $decimals > 36) {
            throw new \RuntimeException('Could not read the configured token decimals.');
        }
        $topic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        $received = 0.0;
        foreach (($receipt['logs'] ?? []) as $log) {
            if (strtolower((string) ($log['address'] ?? '')) !== $contract || strtolower((string) ($log['topics'][0] ?? '')) !== $topic) {
                continue;
            }
            $topics = $log['topics'] ?? [];
            if (count($topics) < 3) {
                continue;
            }
            $to = '0x' . substr(strtolower($topics[2]), -40);
            if ($to !== $wallet) {
                continue;
            }
            $value = hexdec(substr((string) ($log['data'] ?? '0x0'), 2));
            $received += $value / (10 ** $decimals);
        }
        if ($received + 0.0001 < $expected) {
            throw new \RuntimeException(sprintf('Received %.4f USDT; expected %.2f USDT to the configured store wallet.', $received, $expected));
        }
    }

    private function ownedOrder(string $code): ?array
    {
        $id = (int) Session::get('customer_id', 0);
        return $id ? Db::fetch('SELECT * FROM store_orders WHERE order_code = ? AND customer_id = ?', [$code, $id]) : null;
    }

    public function download(Request $request, array $params): Response
    {
        $order = $this->ownedOrder((string) $params['code']);
        $type = strtolower((string) $params['type']);
        $id = (int) $params['id'];

        if (!$order || $order['status'] !== 'paid' || !in_array($type, ['plugin', 'theme'], true)) {
            return Response::error('Purchased download not found.', 403);
        }
        if (!$this->orderGrants($order, $type, $id)) {
            return Response::error('Purchased download not found.', 403);
        }

        $product = StoreCatalog::find($type, $id);
        if (!$product || empty($product['package_path'])) {
            return Response::error('A download package is not available for this item yet.', 404);
        }

        $path = \Wordpresso\Services\PackageStorage::baseDir() . '/' . $product['package_path'];
        $real = realpath($path);
        $base = realpath(\Wordpresso\Services\PackageStorage::baseDir());
        if (!$real || !$base || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return Response::error('The purchased package is unavailable.', 404);
        }
        return Response::file($real, $product['slug'] . '.zip');
    }

    /**
     * Whether this paid order lets the customer download the given product:
     * bought in the order, recorded on a subscription, granted as a plugin
     * entitlement, or unlocked by an all-access plan.
     */
    private function orderGrants(array $order, string $type, int $id): bool
    {
        $category = StoreCatalog::categoryFromType($type);
        $customerId = (int) $order['customer_id'];

        if (Db::fetch(
            'SELECT id FROM store_order_items WHERE order_id = ? AND product_id = ? AND category = ?',
            [(int) $order['id'], $id, $category]
        ) !== null) {
            return true;
        }

        $active = "s.customer_id = ? AND s.status = 'active'
                   AND s.starts_at <= CURRENT_TIMESTAMP
                   AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)";

        if (Db::fetch(
            "SELECT sp.id FROM subscription_products sp
             JOIN subscriptions s ON s.id = sp.subscription_id
             WHERE {$active} AND sp.category = ? AND sp.product_id = ?",
            [$customerId, $category, $id]
        ) !== null) {
            return true;
        }

        if ($type === 'plugin' && Db::fetch(
            "SELECT spl.id FROM subscription_plugins spl
             JOIN subscriptions s ON s.id = spl.subscription_id
             WHERE {$active} AND spl.plugin_id = ?",
            [$customerId, $id]
        ) !== null) {
            return true;
        }

        return Db::fetch(
            "SELECT s.id FROM subscriptions s WHERE {$active} AND s.plan_id IS NOT NULL LIMIT 1",
            [$customerId]
        ) !== null;
    }

    /** Packages a paid plan order unlocks for the customer. */
    private function planPackages(int $customerId, string $orderCode): array
    {
        $subscription = Db::value(
            "SELECT id FROM subscriptions
             WHERE customer_id = ? AND plan_name LIKE ? AND plan_id IS NOT NULL
               AND status = 'active' AND starts_at <= CURRENT_TIMESTAMP
               AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
             LIMIT 1",
            [$customerId, '%(' . $orderCode . ')%']
        );
        return $subscription === null ? [] : StoreCatalog::planPackages();
    }

    /**
     * Keep only well-formed "type:id" cart entries and clamp their quantity.
     * Entries written before the catalogue split cannot be resolved to a
     * product type, so they are dropped and the customer re-adds them.
     *
     * @return array<string, int>
     */
    private function resolveCart(array $cart): array
    {
        $resolved = [];
        foreach ($cart as $key => $quantity) {
            if (StoreCatalog::parseKey((string) $key) !== null) {
                $resolved[(string) $key] = max(1, min(10, (int) $quantity));
            }
        }
        return $resolved;
    }

    private function page(string $template, array $vars = []): Response
    {
        return Response::html(View::page('store', $template, array_merge([
            'siteName' => Config::get('site_name'),
            'customer' => HomeController::currentCustomer(),
        ], $vars)));
    }
}
