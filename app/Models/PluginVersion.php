<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class PluginVersion
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM plugin_versions WHERE id = ?', [$id]);
    }

    /** @return array<int, array> */
    public static function forPlugin(int $pluginId): array
    {
        return Db::fetchAll(
            'SELECT * FROM plugin_versions WHERE plugin_id = ? ORDER BY created_at DESC, id DESC',
            [$pluginId]
        );
    }

    public static function current(int $pluginId): ?array
    {
        return Db::fetch(
            "SELECT * FROM plugin_versions WHERE plugin_id = ? AND is_current = 1 AND status = 'published'",
            [$pluginId]
        );
    }

    public static function findByVersion(int $pluginId, string $version): ?array
    {
        return Db::fetch(
            'SELECT * FROM plugin_versions WHERE plugin_id = ? AND version = ?',
            [$pluginId, $version]
        );
    }

    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO plugin_versions (plugin_id, version, package_path, package_sha256,
                package_size, changelog, status, is_current, min_wp_version, min_php_version,
                tested_wp_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [
                $data['plugin_id'], $data['version'], $data['package_path'],
                $data['package_sha256'], $data['package_size'], $data['changelog'] ?? '',
                $data['status'] ?? 'draft', $data['min_wp_version'] ?? '',
                $data['min_php_version'] ?? '', $data['tested_wp_version'] ?? '',
            ]
        );
    }

    /**
     * Publish a version and make it the single current version of the plugin.
     * Runs in a transaction so only one version is current at any time.
     */
    public static function publish(int $id): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $version = self::find($id);
            if ($version === null) {
                throw new \RuntimeException('Version not found.');
            }
            Db::run('UPDATE plugin_versions SET is_current = 0 WHERE plugin_id = ?', [$version['plugin_id']]);
            Db::run(
                "UPDATE plugin_versions SET status = 'published', is_current = 1,
                    published_at = datetime('now') WHERE id = ?",
                [$id]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Withdraw a version; if it was current, no version stays current. */
    public static function withdraw(int $id): void
    {
        Db::run("UPDATE plugin_versions SET status = 'withdrawn', is_current = 0 WHERE id = ?", [$id]);
    }

    public static function saveChangelog(int $id, string $changelog): void
    {
        Db::run('UPDATE plugin_versions SET changelog = ? WHERE id = ?', [$changelog, $id]);
    }

    public static function delete(int $id): void
    {
        $version = self::find($id);
        if ($version === null) {
            throw new \RuntimeException('Version not found.');
        }
        \Wordpresso\Services\PackageStorage::delete((string) $version['package_path']);
        Db::run('DELETE FROM plugin_versions WHERE id = ?', [$id]);
    }
}
