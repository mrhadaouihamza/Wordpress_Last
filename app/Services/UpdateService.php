<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;

/**
 * Resolves update requests against the catalog and WordPress compatibility.
 */
final class UpdateService
{
    /**
     * Compare two version strings (returns -1, 0, 1). Handles numeric and
     * alpha segments the way WordPress does, loosely.
     */
    public static function versionCompare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    /**
     * Build the update response entries for an authenticated website.
     *
     * @param array $customer customers row
     * @param array $requestedItems [{basename, version}]
     * @param string $wpVersion
     * @param string $phpVersion
     * @return array{updates: array<string,array>, denied: array<string,string>}
     */
    public static function resolve(array $customer, array $requestedItems, string $wpVersion, string $phpVersion): array
    {
        $updates = [];
        $denied = [];

        foreach ($requestedItems as $item) {
            $basename = (string) ($item['basename'] ?? '');
            if ($basename === '') {
                continue;
            }
            $installedVersion = (string) ($item['version'] ?? '0');

            $plugin = Plugin::findByBasename($basename);
            if ($plugin === null || (int) $plugin['enabled'] !== 1) {
                continue; // not in our catalog — silently ignored
            }

            $current = PluginVersion::current((int) $plugin['id']);
            if ($current === null) {
                continue;
            }

            // Version comparison: only offer when newer than installed.
            if (self::versionCompare($current['version'], $installedVersion) <= 0) {
                continue;
            }

            // Entitlement gate.
            $entitlement = EntitlementService::check($customer, $plugin);
            if (!$entitlement['allowed']) {
                $denied[$basename] = $entitlement['reason'];
                continue;
            }

            // Compatibility gates.
            $minWp = $current['min_wp_version'] !== '' ? $current['min_wp_version'] : $plugin['min_wp_version'];
            $minPhp = $current['min_php_version'] !== '' ? $current['min_php_version'] : $plugin['min_php_version'];
            if ($minWp !== '' && $wpVersion !== '' && self::versionCompare($wpVersion, $minWp) < 0) {
                $denied[$basename] = 'incompatible_wp';
                continue;
            }
            if ($minPhp !== '' && $phpVersion !== '' && self::versionCompare($phpVersion, $minPhp) < 0) {
                $denied[$basename] = 'incompatible_php';
                continue;
            }

            $updates[$basename] = [
                'slug' => $plugin['slug'],
                'plugin_name' => $plugin['name'],
                'old_version' => $installedVersion,
                'new_version' => $current['version'],
                'requires' => $minWp,
                'requires_php' => $minPhp,
                'tested' => $current['tested_wp_version'] !== '' ? $current['tested_wp_version'] : $plugin['tested_wp_version'],
                'changelog' => $current['changelog'] !== '' ? $current['changelog'] : $plugin['changelog'],
                // The client intercepts this URL and performs an authenticated
                // download; it is not a directly fetchable link.
                'package' => '/api/v1/download?plugin=' . rawurlencode($plugin['slug'])
                    . '&version=' . rawurlencode($current['version']),
                'auto_update' => EntitlementService::allowsAutoUpdate($customer, $plugin) ? 1 : 0,
                '_plugin_id' => (int) $plugin['id'],
                '_version_id' => (int) $current['id'],
            ];
        }

        return ['updates' => $updates, 'denied' => $denied];
    }
}
