<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class ThemeVersion
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM theme_versions WHERE id = ?', [$id]);
    }

    public static function current(int $themeId): ?array
    {
        return Db::fetch(
            "SELECT * FROM theme_versions WHERE theme_id = ? AND is_current = 1 AND status = 'published'",
            [$themeId]
        );
    }

    /** @return array<int, array> */
    public static function forTheme(int $themeId): array
    {
        return Db::fetchAll(
            'SELECT * FROM theme_versions WHERE theme_id = ? ORDER BY created_at DESC, id DESC',
            [$themeId]
        );
    }

    public static function findByVersion(int $themeId, string $version): ?array
    {
        return Db::fetch('SELECT * FROM theme_versions WHERE theme_id = ? AND version = ?', [$themeId, $version]);
    }

    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO theme_versions (theme_id, version, package_path, package_sha256,
                package_size, changelog, status, is_current, min_wp_version, min_php_version,
                tested_wp_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [
                $data['theme_id'], $data['version'], $data['package_path'],
                $data['package_sha256'], $data['package_size'], $data['changelog'] ?? '',
                $data['status'] ?? 'draft', $data['min_wp_version'] ?? '',
                $data['min_php_version'] ?? '', $data['tested_wp_version'] ?? '',
            ]
        );
    }

    public static function publish(int $id): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $version = self::find($id);
            if ($version === null) {
                throw new \RuntimeException('Theme version not found.');
            }
            Db::run('UPDATE theme_versions SET is_current = 0 WHERE theme_id = ?', [$version['theme_id']]);
            Db::run(
                "UPDATE theme_versions SET status = 'published', is_current = 1,
                    published_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$id]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function withdraw(int $id): void
    {
        Db::run("UPDATE theme_versions SET status = 'withdrawn', is_current = 0 WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        $version = self::find($id);
        if ($version === null) {
            throw new \RuntimeException('Theme version not found.');
        }
        \Wordpresso\Services\PackageStorage::delete((string) $version['package_path']);
        Db::run('DELETE FROM theme_versions WHERE id = ?', [$id]);
    }
}
