<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Db;
use Wordpresso\Models\Subscription;

final class StoreOrderActivation
{
    /** Mark a pending order paid and grant its products within one transaction. */
    public static function confirm(int $orderId, ?string $txHash = null): void
    {
        $order = Db::fetch('SELECT * FROM store_orders WHERE id=?', [$orderId]);
        if ($order === null || $order['status'] !== 'pending') {
            throw new \RuntimeException('Only pending orders can be confirmed.');
        }
        if ($txHash !== null && Db::value('SELECT id FROM store_orders WHERE tx_hash=? AND id<>?', [$txHash, $orderId]) !== null) {
            throw new \RuntimeException('That transaction hash is already attached to another order.');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $updated = Db::run("UPDATE store_orders SET status='paid',tx_hash=?,paid_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'", [$txHash, $orderId]);
            if ($updated->rowCount() !== 1) throw new \RuntimeException('This order is no longer pending.');

            $items = Db::fetchAll('SELECT * FROM store_order_items WHERE order_id=?', [$orderId]);
            foreach ($items as $item) {
                $category = (string) $item['category'];
                $productId = (int) $item['product_id'];
                $planName = $item['product_name'] . ' (' . $order['order_code'] . ')';
                $startsAt = gmdate('Y-m-d H:i:s');

                if ($category === 'Plan') {
                    $plan = Db::fetch('SELECT * FROM plans WHERE id=?', [$productId]);
                    if ($plan === null) throw new \RuntimeException('The subscription plan for this order no longer exists.');
                    $months = (int) $plan['term_months'];
                    $expiresAt = $months <= 0 || $months >= 1200 ? null : gmdate('Y-m-d H:i:s', strtotime("+{$months} months"));
                    Subscription::createFromStorePlan((int) $order['customer_id'], $plan, $startsAt, $expiresAt, $planName);
                    continue;
                }

                // Plugins and themes are lifetime purchases: one subscription
                // recording what was bought. Website limits no longer exist —
                // adding a website only requires owning a product or plan.
                $subscriptionId = Subscription::create([
                    'customer_id' => (int) $order['customer_id'],
                    'plan_name' => $planName,
                    'status' => 'active',
                    'updates_included' => 1,
                    'starts_at' => $startsAt,
                    'expires_at' => null,
                ]);
                Db::run('INSERT OR IGNORE INTO subscription_products (subscription_id,category,product_id) VALUES (?,?,?)', [
                    $subscriptionId, $category, $productId,
                ]);
                // Plugin purchases also grant WordPress update access.
                if ($category === 'Plugin' && Db::value('SELECT id FROM plugins WHERE id=?', [$productId]) !== null) {
                    Db::run('INSERT OR IGNORE INTO subscription_plugins (subscription_id,plugin_id) VALUES (?,?)', [$subscriptionId, $productId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
