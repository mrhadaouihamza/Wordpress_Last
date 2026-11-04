<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class Customer
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::fetch('SELECT * FROM customers WHERE email = ?', [strtolower($email)]);
    }

    /** @return array<int, array> */
    public static function all(): array
    {
        return Db::fetchAll('SELECT * FROM customers ORDER BY created_at DESC');
    }

    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO customers (email, name, password_hash, status) VALUES (?, ?, ?, ?)',
            [
                strtolower($data['email']), $data['name'] ?? '',
                $data['password_hash'], $data['status'] ?? 'active',
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Db::run(
            'UPDATE customers SET email = ?, name = ? WHERE id = ?',
            [strtolower($data['email']), $data['name'] ?? '', $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Db::run('UPDATE customers SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function setPassword(int $id, string $passwordHash): void
    {
        Db::run('UPDATE customers SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    /**
     * Permanently delete a customer account and everything tied to it:
     * website credentials and tokens, subscriptions with their entitlements,
     * and store orders with their items. Audit logs are kept for the store
     * operator; website links inside them are unlinked like Website::delete().
     */
    public static function deleteAccount(int $id): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach (Website::forCustomer($id) as $website) {
                Website::purge((int) $website['id']);
            }
            Db::run('DELETE FROM subscriptions WHERE customer_id = ?', [$id]);
            Db::run('DELETE FROM store_order_items WHERE order_id IN (SELECT id FROM store_orders WHERE customer_id = ?)', [$id]);
            Db::run('DELETE FROM store_orders WHERE customer_id = ?', [$id]);
            Db::run('DELETE FROM customers WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array> */
    public static function subscriptions(int $customerId): array
    {
        return Subscription::forCustomer($customerId);
    }

    /** @return array<int, int> plugin ids granted by the customer's subscriptions */
    public static function entitledPluginIds(int $customerId): array
    {
        $rows = Db::fetchAll(
            "SELECT DISTINCT sp.plugin_id
             FROM subscription_plugins sp
             JOIN subscriptions s ON s.id = sp.subscription_id
             WHERE s.customer_id = ?
               AND s.status = 'active'
               AND s.updates_included = 1
               AND (s.expires_at IS NULL OR datetime(s.expires_at) > datetime('now'))",
            [$customerId]
        );
        return array_map('intval', array_column($rows, 'plugin_id'));
    }

    /** Whether the customer owns at least one active plan, plugin, or theme. */
    public static function ownsPlanOrProduct(int $customerId): bool
    {
        $active = "s.customer_id = ? AND s.status = 'active'
                   AND s.starts_at <= CURRENT_TIMESTAMP
                   AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)";

        // A purchased (or assigned) subscription plan.
        if (Db::value(
            "SELECT s.id FROM subscriptions s WHERE {$active} AND s.plan_id IS NOT NULL LIMIT 1",
            [$customerId]
        ) !== null) {
            return true;
        }

        // A plugin or theme recorded against one of the subscriptions.
        if (Db::value(
            "SELECT sp.id FROM subscription_products sp
             JOIN subscriptions s ON s.id = sp.subscription_id
             WHERE {$active} LIMIT 1",
            [$customerId]
        ) !== null) {
            return true;
        }

        // Plugin entitlement granted without a plan (direct plugin purchase).
        return Db::value(
            "SELECT spl.id FROM subscription_plugins spl
             JOIN subscriptions s ON s.id = spl.subscription_id
             WHERE {$active} LIMIT 1",
            [$customerId]
        ) !== null;
    }

    public static function canAddWebsite(int $customerId): bool
    {
        if (!self::ownsPlanOrProduct($customerId)) {
            return false;
        }
        $count = (int) Db::value('SELECT COUNT(*) FROM websites WHERE customer_id = ? AND status <> \'revoked\'', [$customerId]);
        $limits = Db::fetchAll(
            "SELECT p.website_limit
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.customer_id = ? AND s.status = 'active'
               AND s.starts_at <= CURRENT_TIMESTAMP
               AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)",
            [$customerId]
        );
        if ($limits === []) {
            return true;
        }
        foreach ($limits as $limit) {
            if ((int) $limit['website_limit'] === 0 || $count < (int) $limit['website_limit']) {
                return true;
            }
        }
        return false;
    }
}
