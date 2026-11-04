<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class Plugin
{
    public const UPDATER_SLUG = 'wordpresso-auto-updater';
    public const UPDATER_BASENAME = 'wordpresso-auto-updater/wordpresso-auto-updater.php';

    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM plugins WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Db::fetch('SELECT * FROM plugins WHERE slug = ?', [$slug]);
    }

    public static function findByBasename(string $basename): ?array
    {
        return Db::fetch('SELECT * FROM plugins WHERE basename = ?', [$basename]);
    }

    /** @return array<int, array> */
    public static function all(): array
    {
        return Db::fetchAll(
            'SELECT id, slug, name, basename, description, changelog,
                    price, package_path, image_path, active, enabled,
                    auto_update_allowed, min_wp_version, min_php_version,
                    tested_wp_version, created_at, updated_at
             FROM plugins ORDER BY name ASC'
        );
    }

    /** @return array<int, array> enabled plugins with their current published version */
    public static function catalog(): array
    {
        return Db::fetchAll(
            "SELECT p.*, v.version AS current_version, v.changelog AS current_changelog,
                    v.published_at AS current_published_at
             FROM plugins p
             LEFT JOIN plugin_versions v ON v.plugin_id = p.id
                 AND v.is_current = 1 AND v.status = 'published'
             WHERE p.enabled = 1
             ORDER BY p.name"
        );
    }

    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO plugins (slug, name, basename, description, changelog, enabled,
                auto_update_allowed, min_wp_version, min_php_version, tested_wp_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['slug'], $data['name'], $data['basename'],
                $data['description'] ?? '', $data['changelog'] ?? '',
                (int) ($data['enabled'] ?? 1), (int) ($data['auto_update_allowed'] ?? 1),
                $data['min_wp_version'] ?? '', $data['min_php_version'] ?? '',
                $data['tested_wp_version'] ?? '',
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Db::run(
            'UPDATE plugins SET slug = ?, name = ?, basename = ?, description = ?,
                auto_update_allowed = ?, min_wp_version = ?, min_php_version = ?,
                tested_wp_version = ?, updated_at = datetime(\'now\')
             WHERE id = ?',
            [
                $data['slug'], $data['name'], $data['basename'],
                $data['description'] ?? '', (int) ($data['auto_update_allowed'] ?? 1),
                $data['min_wp_version'] ?? '', $data['min_php_version'] ?? '',
                $data['tested_wp_version'] ?? '', $id,
            ]
        );
    }

    public static function toggleEnabled(int $id): void
    {
        Db::run('UPDATE plugins SET enabled = 1 - enabled, updated_at = datetime(\'now\') WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        $plugin = self::find($id);
        if ($plugin === null) {
            throw new \RuntimeException('Plugin not found.');
        }
        foreach (PluginVersion::forPlugin($id) as $version) {
            \Wordpresso\Services\PackageStorage::delete((string) $version['package_path']);
        }
        Db::run('DELETE FROM plugin_versions WHERE plugin_id = ?', [$id]);
        Db::run('DELETE FROM plugins WHERE id = ?', [$id]);
    }
}
