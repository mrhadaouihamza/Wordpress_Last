<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Crypto;
use Wordpresso\Core\Db;

final class Website
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM websites WHERE id = ?', [$id]);
    }

    public static function findByApiKey(string $apiKey): ?array
    {
        return Db::fetch('SELECT * FROM websites WHERE api_key = ?', [$apiKey]);
    }

    /** @return array<int, array> */
    public static function forCustomer(int $customerId): array
    {
        return Db::fetchAll(
            'SELECT * FROM websites WHERE customer_id = ? ORDER BY connected_at DESC',
            [$customerId]
        );
    }

    public static function countForCustomer(int $customerId): int
    {
        return (int)Db::value('SELECT COUNT(*) FROM websites WHERE customer_id=?', [$customerId]);
    }

    /** Permanently remove credentials and tokens while retaining unlinked audit history. */
    public static function delete(int $id): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            self::purge($id);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Same cleanup as delete(), but the caller manages the surrounding transaction. */
    public static function purge(int $id): void
    {
        Db::run('UPDATE update_logs SET website_id=NULL WHERE website_id=?', [$id]);
        Db::run('DELETE FROM download_tokens WHERE website_id=?', [$id]);
        Db::run('DELETE FROM websites WHERE id=?', [$id]);
    }

    /**
     * @return array<int, array> joined with customer email/status for the admin list
     */
    public static function allWithCustomer(): array
    {
        return Db::fetchAll(
            'SELECT w.*, c.email AS customer_email, c.status AS customer_status, c.name AS customer_name
             FROM websites w JOIN customers c ON c.id = w.customer_id
             ORDER BY w.connected_at DESC'
        );
    }

    /**
     * Create a website connection and return [row, plaintextSecret].
     * The secret is shown to the customer exactly once; only the ciphertext is stored.
     */
    public static function create(int $customerId, string $name, string $url): array
    {
        $apiKey = 'wpk_' . bin2hex(random_bytes(16));
        $secret = Crypto::token(32);
        $id = Db::insert(
            'INSERT INTO websites (customer_id, name, url, api_key, secret_encrypted)
             VALUES (?, ?, ?, ?, ?)',
            [$customerId, $name, $url, $apiKey, Crypto::encrypt($secret)]
        );
        return [self::find($id), $secret];
    }

    /** Rotate the API secret; returns the new plaintext secret. */
    public static function rotateSecret(int $id): string
    {
        $secret = Crypto::token(32);
        Db::run('UPDATE websites SET secret_encrypted = ? WHERE id = ?', [Crypto::encrypt($secret), $id]);
        return $secret;
    }

    public static function revoke(int $id): void
    {
        Db::run(
            "UPDATE websites SET status = 'revoked', revoked_at = datetime('now') WHERE id = ?",
            [$id]
        );
        // Kill outstanding download tokens immediately.
        Db::run('DELETE FROM download_tokens WHERE website_id = ?', [$id]);
    }

    public static function activate(int $id): void
    {
        Db::run("UPDATE websites SET status = 'active', revoked_at = NULL WHERE id = ?", [$id]);
    }

    public static function markCheck(int $id, string $wpVersion, string $phpVersion, string $ip): void
    {
        Db::run(
            "UPDATE websites SET last_check_at = datetime('now'), last_check_ip = ?,
                wp_version = ?, php_version = ? WHERE id = ?",
            [$ip, $wpVersion, $phpVersion, $id]
        );
    }
}
