<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;
use Wordpresso\Services\StoreCatalog;

final class Subscription
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM subscriptions WHERE id = ?', [$id]);
    }

    /** @return array<int, array> */
    public static function forCustomer(int $customerId): array
    {
        return Db::fetchAll(
            'SELECT s.*, COALESCE(p.website_limit, 0) AS website_limit
             FROM subscriptions s LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.customer_id = ? ORDER BY s.created_at DESC',
            [$customerId]
        );
    }

    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO subscriptions (customer_id, plan_name, status, updates_included,
                starts_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['plan_name'] ?? '',
                $data['status'] ?? 'active', (int) ($data['updates_included'] ?? 1),
                $data['starts_at'], $data['expires_at'] ?? null,
            ]
        );
    }

    /**
     * Create a subscription for a purchased store plan.
     *
     * Plans are all-access: the subscription records which plan was bought and
     * every active plugin/theme is granted through `plan_id` (see pluginIds()).
     * The plan website limit is read from the linked plan when websites are
     * connected; zero means unlimited.
     */
    public static function createFromStorePlan(int $customerId, array $plan, string $startsAt, ?string $expiresAt, string $name): int
    {
        $subscriptionId = self::create([
            'customer_id' => $customerId,
            'plan_name' => $name,
            'status' => 'active',
            'updates_included' => 1,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
        Db::run('UPDATE subscriptions SET plan_id=? WHERE id=?', [(int) $plan['id'], $subscriptionId]);
        return $subscriptionId;
    }

    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);
        $customerId = $data['customer_id'] ?? ($existing['customer_id'] ?? null);
        Db::run(
            'UPDATE subscriptions SET customer_id = ?, plan_name = ?, status = ?, updates_included = ?,
                starts_at = ?, expires_at = ? WHERE id = ?',
            [
                $customerId, $data['plan_name'] ?? ($existing['plan_name'] ?? ''), $data['status'] ?? ($existing['status'] ?? 'active'),
                (int) ($data['updates_included'] ?? ($existing['updates_included'] ?? 1)), $data['starts_at'] ?? ($existing['starts_at'] ?? ''),
                array_key_exists('expires_at', $data) ? $data['expires_at'] : ($existing['expires_at'] ?? null), $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM subscriptions WHERE id = ?', [$id]);
    }

    /** @return array<int, int> */
    public static function pluginIds(int $subscriptionId): array
    {
        $subscription = self::find($subscriptionId);
        if ($subscription !== null && !empty($subscription['plan_id'])) {
            return array_map('intval', array_column(
                Db::fetchAll("SELECT id FROM plugins WHERE enabled=1"),
                'id'
            ));
        }
        $rows = Db::fetchAll(
            'SELECT plugin_id FROM subscription_plugins WHERE subscription_id = ?',
            [$subscriptionId]
        );
        return array_map('intval', array_column($rows, 'plugin_id'));
    }

    /**
     * Products the subscription grants: a plan grants every active plugin and
     * theme, otherwise the directly purchased products are listed.
     *
     * @return array<int, array>
     */
    public static function storeProducts(int $subscriptionId): array
    {
        $subscription = self::find($subscriptionId);
        if ($subscription !== null && !empty($subscription['plan_id'])) {
            return StoreCatalog::listings(true);
        }

        $rows = [];
        foreach (Db::fetchAll(
            'SELECT category, product_id FROM subscription_products
             WHERE subscription_id = ? ORDER BY category, product_id',
            [$subscriptionId]
        ) as $grant) {
            $type = StoreCatalog::typeFromCategory((string) $grant['category']);
            $product = $type === null ? null : StoreCatalog::find($type, (int) $grant['product_id']);
            if ($product === null) {
                continue;
            }
            $rows[] = [
                'id' => (int) $product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'category' => $product['category'],
            ];
        }
        return $rows;
    }

    /** Mark time-based expired subscriptions as expired (opportunistic). */
    public static function expireStale(): void
    {
        Db::run(
            "UPDATE subscriptions SET status = 'expired'
             WHERE status = 'active' AND expires_at IS NOT NULL AND datetime(expires_at) <= datetime('now')"
        );
    }
}
