<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Api;

use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Models\Customer;
use Wordpresso\Models\Subscription;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Models\Website;
use Wordpresso\Services\UpdateService;

/**
 * POST /api/v1/updates
 *
 * Body: {wp_version, php_version, plugins: [{basename, version}]}
 * Returns available updates for entitled, compatible plugins.
 */
final class UpdateController
{
    public function check(Request $request): Response
    {
        $website = ApiAuthGateway::auth($request);
        if ($website === null) {
            return ApiAuthGateway::errorResponse();
        }

        $wpVersion = substr((string) $request->input('wp_version', ''), 0, 32);
        $phpVersion = substr((string) $request->input('php_version', ''), 0, 32);
        $plugins = $request->input('plugins', []);
        if (!is_array($plugins) || count($plugins) > 200) {
            return Response::error('Invalid plugins payload.', 422);
        }

        // Opportunistically expire stale subscriptions so the dashboard and
        // entitlement checks agree.
        Subscription::expireStale();
        // Opportunistically prune old logs and expired tokens (probabilistically, 1 in 100 checks)
        if (random_int(1, 100) === 1) {
            $retentionDays = (int) \Wordpresso\Core\Config::get('log_retention_days', 30);
            UpdateLog::pruneExpired($retentionDays);
        }

        $customer = Customer::find((int) $website['customer_id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }

        Website::markCheck((int) $website['id'], $wpVersion, $phpVersion, $request->ip);

        $result = UpdateService::resolve($customer, $plugins, $wpVersion, $phpVersion);

        $subscriptions = Subscription::forCustomer((int) $customer['id']);
        $activeSubscriptions = array_values(array_filter($subscriptions, static function (array $subscription): bool {
            return $subscription['status'] === 'active'
                && (int) $subscription['updates_included'] === 1
                && ($subscription['expires_at'] === null || strtotime((string) $subscription['expires_at']) > time());
        }));
        $accountStatus = $customer['status'] !== 'active'
            ? 'suspended'
            : (count($activeSubscriptions) > 0 ? 'active' : 'no_active_subscription');

        // Strip internal keys before responding.
        $updates = [];
        foreach ($result['updates'] as $basename => $update) {
            unset($update['_plugin_id'], $update['_version_id']);
            $updates[$basename] = $update;
        }

        // Tell the client which of its plugins are in our catalog so it can
        // filter locally without extra requests.
        $known = [];
        foreach ($plugins as $item) {
            $basename = (string) ($item['basename'] ?? '');
            if ($basename !== '' && \Wordpresso\Models\Plugin::findByBasename($basename) !== null) {
                $known[] = $basename;
            }
        }

        UpdateLog::record(
            'check',
            'success',
            sprintf('Checked %d plugin(s), %d update(s) offered.', count($plugins), count($updates)),
            (int) $website['id'],
            (int) $customer['id'],
            null,
            $request->ip,
            ['offered' => array_keys($updates), 'denied' => $result['denied']]
        );

        return Response::json([
            'success' => true,
            'account' => [
                'email' => $customer['email'],
                'status' => $accountStatus,
                'active_subscriptions' => count($activeSubscriptions),
            ],
            'server_time' => time(),
            'check_interval' => HOUR_IN_SECONDS_API * 12,
            'known' => $known,
            'updates' => $updates,
        ]);
    }

    /**
     * POST /api/v1/status
     *
     * Lets the client report the outcome of an installation attempt so the
     * dashboard can monitor real update results.
     * Body: {plugin, version, result: success|failure, message?}
     */
    public function status(Request $request): Response
    {
        $website = ApiAuthGateway::auth($request);
        if ($website === null) {
            return ApiAuthGateway::errorResponse();
        }

        $basename = substr((string) $request->input('plugin', ''), 0, 190);
        $version = substr((string) $request->input('version', ''), 0, 64);
        $result = (string) $request->input('result', '') === 'success' ? 'success' : 'failure';
        $message = substr((string) $request->input('message', ''), 0, 500);

        $plugin = \Wordpresso\Models\Plugin::findByBasename($basename);

        UpdateLog::record(
            'install',
            $result,
            $message !== '' ? $message : sprintf('Plugin %s %s.', $basename, $result),
            (int) $website['id'],
            (int) $website['customer_id'],
            $plugin !== null ? (int) $plugin['id'] : null,
            $request->ip,
            ['version' => $version]
        );

        return Response::json(['success' => true]);
    }
}

/** API uses seconds; avoid a WordPress constant dependency on the server. */
const HOUR_IN_SECONDS_API = 3600;
