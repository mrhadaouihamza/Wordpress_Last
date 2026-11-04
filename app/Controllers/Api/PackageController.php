<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Api;

use Wordpresso\Core\Config;
use Wordpresso\Core\Response;
use Wordpresso\Models\Customer;
use Wordpresso\Models\DownloadToken;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Services\EntitlementService;
use Wordpresso\Services\PackageStorage;

/**
 * Package distribution, in two steps:
 *
 *  1. POST /api/v1/package  {plugin: slug, version: x.y.z}
 *     — HMAC-authenticated. Re-validates entitlement and compatibility,
 *       issues a single-use, short-lived download token.
 *
 *  2. GET /api/v1/package/{token}
 *     — token-only (it is single-use and short-lived, so it carries no
 *       standing authorization). Streams the ZIP after re-verifying its
 *       SHA-256 integrity.
 */
final class PackageController
{
    public function authorize(\Wordpresso\Core\Request $request): Response
    {
        $website = ApiAuthGateway::auth($request);
        if ($website === null) {
            return ApiAuthGateway::errorResponse();
        }

        $slug = (string) $request->input('plugin', '');
        $version = trim((string) $request->input('version', ''));

        $plugin = Plugin::findBySlug($slug);
        if ($plugin === null || (int) $plugin['enabled'] !== 1) {
            UpdateLog::record('download', 'failure', 'Unknown or disabled plugin.', (int) $website['id'], (int) $website['customer_id'], null, $request->ip, ['slug' => $slug]);
            return Response::error('Plugin not available.', 404);
        }

        // The version must be the currently published one — customers can
        // never pull withdrawn or superseded-by-withdrawal packages.
        $current = PluginVersion::current((int) $plugin['id']);
        if ($current === null || ($version !== '' && $current['version'] !== $version)) {
            UpdateLog::record('download', 'failure', 'Requested version is not the published one.', (int) $website['id'], (int) $website['customer_id'], (int) $plugin['id'], $request->ip, ['requested' => $version]);
            return Response::error('Requested version is not available.', 404);
        }

        $customer = Customer::find((int) $website['customer_id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }

        $entitlement = EntitlementService::check($customer, $plugin);
        if (!$entitlement['allowed']) {
            UpdateLog::record('download', 'failure', 'Entitlement check failed: ' . $entitlement['reason'], (int) $website['id'], (int) $customer['id'], (int) $plugin['id'], $request->ip);
            return Response::error('You are not entitled to this product.', 403, ['code' => $entitlement['reason']]);
        }

        // Compatibility re-check at download time (site may have changed).
        $wpVersion = substr((string) $request->input('wp_version', ''), 0, 32);
        $phpVersion = substr((string) $request->input('php_version', ''), 0, 32);
        $minWp = $current['min_wp_version'] !== '' ? $current['min_wp_version'] : $plugin['min_wp_version'];
        $minPhp = $current['min_php_version'] !== '' ? $current['min_php_version'] : $plugin['min_php_version'];
        if (($minWp !== '' && $wpVersion !== '' && version_compare($wpVersion, $minWp) < 0)
            || ($minPhp !== '' && $phpVersion !== '' && version_compare($phpVersion, $minPhp) < 0)) {
            UpdateLog::record('download', 'failure', 'Environment incompatible.', (int) $website['id'], (int) $customer['id'], (int) $plugin['id'], $request->ip, ['wp' => $wpVersion, 'php' => $phpVersion]);
            return Response::error('Your environment does not meet the requirements for this version.', 409);
        }

        $ttl = (int) Config::get('api.download_token_ttl', 600);
        $token = DownloadToken::issue((int) $website['id'], (int) $current['id'], $ttl);

        UpdateLog::record(
            'download', 'success', 'Download authorized.',
            (int) $website['id'], (int) $customer['id'], (int) $plugin['id'], $request->ip,
            ['version' => $current['version']]
        );

        return Response::json([
            'success' => true,
            'download_url' => '/api/v1/package/' . rawurlencode($token),
            'expires_in' => $ttl,
            'sha256' => $current['package_sha256'],
            'size' => (int) $current['package_size'],
        ]);
    }

    public function download(\Wordpresso\Core\Request $request, array $params): Response
    {
        $token = (string) ($params['token'] ?? '');
        if ($token === '') {
            return Response::error('Missing token.', 400);
        }

        $row = DownloadToken::consume($token);
        if ($row === null) {
            UpdateLog::record('download', 'failure', 'Invalid, expired or reused download token.', null, null, null, $request->ip);
            return Response::error('This download link is invalid or has expired.', 403);
        }

        $version = PluginVersion::find((int) $row['plugin_version_id']);
        if ($version === null) {
            return Response::error('Package not found.', 404);
        }

        $path = PackageStorage::absolutePath($version['package_path'], $version['package_sha256']);
        if ($path === null) {
            $website = \Wordpresso\Models\Website::find((int) $row['website_id']);
            UpdateLog::record('download', 'failure', 'Package missing or integrity check failed.', (int) $row['website_id'], $website['customer_id'] ?? null, null, $request->ip, ['version_id' => (int) $version['id']]);
            return Response::error('Package is temporarily unavailable.', 503);
        }

        $plugin = Plugin::find((int) $version['plugin_id']);
        $website = \Wordpresso\Models\Website::find((int) $row['website_id']);
        UpdateLog::record(
            'download', 'success', 'Package downloaded.',
            (int) $row['website_id'], $website['customer_id'] ?? null,
            (int) $version['plugin_id'], $request->ip,
            ['version' => $version['version']]
        );

        return Response::file($path, $plugin['slug'] . '-' . $version['version'] . '.zip');
    }
}
