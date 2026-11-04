<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Db;

/**
 * Reads the storefront catalogue across the three product tables.
 *
 * Plugins live in `plugins` (the updater catalogue, extended with storefront
 * columns), themes in `themes` and subscription plans in `plans`. Because the
 * numeric id spaces are independent, every row carries a `type`
 * ('plugin' | 'theme' | 'plan') and a `category` ('Plugin' | 'Theme' |
 * 'Plan') so callers can build URLs and interpret ids without ambiguity.
 */
final class StoreCatalog
{
    /** Cart / download keys look like "plugin:12". */
    public const KEY_PATTERN = '/^(plugin|theme|plan):(\d+)$/';

    public static function categoryFromType(string $type): ?string
    {
        return match ($type) {
            'plugin' => 'Plugin',
            'theme' => 'Theme',
            'plan' => 'Plan',
            default => null,
        };
    }

    public static function typeFromCategory(string $category): ?string
    {
        return match ($category) {
            'Plugin' => 'plugin',
            'Theme' => 'theme',
            'Plan' => 'plan',
            default => null,
        };
    }

    public static function tableFor(string $type): ?string
    {
        return match ($type) {
            'plugin' => 'plugins',
            'theme' => 'themes',
            'plan' => 'plans',
            default => null,
        };
    }

    public static function key(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /** Parse a "plugin:12" key into its type, id and category. */
    public static function parseKey(string $key): ?array
    {
        if (!preg_match(self::KEY_PATTERN, $key, $matches)) {
            return null;
        }
        return [
            'type' => $matches[1],
            'id' => (int) $matches[2],
            'category' => self::categoryFromType($matches[1]),
        ];
    }

    /**
     * Purchasable plugins and themes with normalised storefront columns.
     *
     * Direct plugin/theme purchases are lifetime, so `term_months` is 0 —
     * the value the templates already render as "Lifetime".
     *
     * @return array<int, array>
     */
    public static function listings(bool $activeOnly = true): array
    {
        $activeClause = $activeOnly ? ' AND active = 1' : '';
        $rows = [];

        foreach (Db::fetchAll(
            "SELECT source_id AS id, slug, name, description, price, package_path, image_path, active, created_at
             FROM products WHERE category = 'Plugin'{$activeClause} ORDER BY name"
        ) as $row) {
            $release = \Wordpresso\Models\PluginVersion::current((int) $row['id']);
            $row['package_path'] = $release['package_path'] ?? $row['package_path'] ?? null;
            $row['current_version'] = $release['version'] ?? null;
            $row['current_release'] = $release;
            $row['type'] = 'plugin';
            $row['category'] = 'Plugin';
            $row['term_months'] = 0;
            $rows[] = $row;
        }

        foreach (Db::fetchAll(
            "SELECT source_id AS id, slug, name, description, price, package_path, image_path, active, created_at
             FROM products WHERE category = 'Theme'{$activeClause} ORDER BY name"
        ) as $row) {
            $release = \Wordpresso\Models\ThemeVersion::current((int) $row['id']);
            $row['package_path'] = $release['package_path'] ?? $row['package_path'] ?? null;
            $row['current_version'] = $release['version'] ?? null;
            $row['current_release'] = $release;
            $row['type'] = 'theme';
            $row['category'] = 'Theme';
            $row['term_months'] = 0;
            $rows[] = $row;
        }

        usort($rows, static fn(array $a, array $b): int => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);
        return $rows;
    }

    /** One product by type + id, or null when it does not exist. */
    public static function find(string $type, int $id): ?array
    {
        $category = self::categoryFromType($type);
        $table = self::tableFor($type);
        if ($category === null || $table === null || $id < 1) {
            return null;
        }
        if ($type === 'plan') {
            $row = Db::fetch('SELECT * FROM plans WHERE id = ?', [$id]);
            if ($row === null) {
                return null;
            }
            $row['type'] = $type;
            $row['category'] = $category;
            return $row;
        }
        $row = Db::fetch('SELECT * FROM products WHERE category = ? AND source_id = ?', [$category, $id]);
        if ($row === null) {
            $row = Db::fetch("SELECT * FROM {$table} WHERE id = ?", [$id]);
            if ($row === null) {
                return null;
            }
        }
        $row['id'] = $id;
        if ($type === 'plugin') {
            $release = \Wordpresso\Models\PluginVersion::current($id);
            $row['package_path'] = $release['package_path'] ?? $row['package_path'] ?? null;
            $row['current_release'] = $release;
        } elseif ($type === 'theme') {
            $release = \Wordpresso\Models\ThemeVersion::current($id);
            $row['package_path'] = $release['package_path'] ?? ($row['package_path'] ?? null);
            $row['current_release'] = $release;
        }
        $row['type'] = $type;
        $row['category'] = self::categoryFromType($type);
        $row['term_months'] = (int) ($row['term_months'] ?? 0);
        return $row;
    }

    /** @return array<int, array> published plans, newest first */
    public static function plans(bool $activeOnly = true): array
    {
        $plans = Db::fetchAll('SELECT * FROM plans' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY price, name');
        foreach ($plans as &$plan) {
            $plan['type'] = 'plan';
            $plan['category'] = 'Plan';
        }
        unset($plan);
        return $plans;
    }

    /**
     * Apply the storefront search, category and sort controls to a listing set.
     *
     * Search is token based: every whitespace-separated term must appear in the
     * product name, description, category, slug or current version. That makes
     * multi-word queries such as "seo plugin" behave as customers expect, and
     * it also matches version numbers, which the previous exact-substring
     * filter could not do reliably.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    public static function refine(array $rows, string $search = '', string $category = 'All', string $sort = 'featured'): array
    {
        $rows = self::matchSearch($rows, $search);
        if ($category !== 'All') {
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => (string) ($row['category'] ?? '') === $category
            ));
        }
        return self::sortListings($rows, $sort);
    }

    /**
     * Keep only the rows where every search term occurs somewhere in the row.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    public static function matchSearch(array $rows, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return $rows;
        }
        $tokens = preg_split('/\s+/u', self::lower($search), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || $tokens === []) {
            return $rows;
        }
        return array_values(array_filter($rows, static function (array $row) use ($tokens): bool {
            $haystack = self::lower(implode(' ', [
                (string) ($row['name'] ?? ''),
                (string) ($row['description'] ?? ''),
                (string) ($row['category'] ?? ''),
                (string) ($row['category'] ?? '') . 's',
                (string) ($row['slug'] ?? ''),
                (string) ($row['current_version'] ?? ''),
                (string) ($row['type'] ?? ''),
            ]));
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Order a listing set for the storefront sort control.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    public static function sortListings(array $rows, string $sort): array
    {
        if ($sort === 'price-asc') {
            usort($rows, static fn(array $a, array $b): int =>
                ((float) ($a['price'] ?? 0) <=> (float) ($b['price'] ?? 0))
                ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        } elseif ($sort === 'price-desc') {
            usort($rows, static fn(array $a, array $b): int =>
                ((float) ($b['price'] ?? 0) <=> (float) ($a['price'] ?? 0))
                ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        } elseif ($sort === 'name') {
            usort($rows, static fn(array $a, array $b): int =>
                strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        }
        return $rows;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * Resolve `slug` and `package_path` for rows that carry a `category` and
     * a `product_id` (order items, subscription grants).
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    public static function attachDownloadInfo(array $rows): array
    {
        foreach ($rows as &$row) {
            $type = self::typeFromCategory((string) ($row['category'] ?? ''));
            $product = $type === null
                ? null
                : self::find($type, (int) ($row['product_id'] ?? $row['id'] ?? 0));
            $row['slug'] = $product['slug'] ?? null;
            $row['package_path'] = $product['package_path'] ?? null;
        }
        unset($row);
        return $rows;
    }

    /**
     * Every downloadable plugin/theme package a paid plan unlocks.
     *
     * @return array<int, array>
     */
    public static function planPackages(): array
    {
        $rows = [];
        foreach (Db::fetchAll(
            "SELECT source_id AS id, slug, name FROM products
             WHERE category = 'Plugin' AND active = 1 ORDER BY name"
        ) as $row) {
            $release = \Wordpresso\Models\PluginVersion::current((int) $row['id']);
            if ($release === null) {
                continue;
            }
            $row['package_path'] = $release['package_path'];
            $row['type'] = 'plugin';
            $row['category'] = 'Plugin';
            $rows[] = $row;
        }
        foreach (Db::fetchAll(
            "SELECT source_id AS id, slug, name, package_path FROM products
             WHERE category = 'Theme' AND active = 1 ORDER BY name"
        ) as $row) {
            $release = \Wordpresso\Models\ThemeVersion::current((int) $row['id']);
            if ($release === null) {
                continue;
            }
            $row['package_path'] = $release['package_path'] ?? ($row['package_path'] ?? null);
            $row['type'] = 'theme';
            $row['category'] = 'Theme';
            $rows[] = $row;
        }
        return $rows;
    }
}
