<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Models\Customer;
use Wordpresso\Models\Subscription;

/**
 * Decides whether a customer may receive updates for a plugin.
 *
 * Checks, in order:
 *  1. customer account is active
 *  2. at least one subscription is active
 *  3. the subscription has not expired by date
 *  4. the subscription includes updates
 *  5. the specific plugin is granted by that subscription
 */
final class EntitlementService
{
    public const UPDATER_SLUG = 'wordpresso-auto-updater';
    public const UPDATER_BASENAME = 'wordpresso-auto-updater/wordpresso-auto-updater.php';

    public static function isUpdaterPlugin(array $plugin): bool
    {
        $slug = (string) ($plugin['slug'] ?? '');
        $basename = (string) ($plugin['basename'] ?? '');

        return $slug === self::UPDATER_SLUG
            || $basename === self::UPDATER_BASENAME;
    }

    /**
     * @param array $customer  customers row
     * @param array $plugin    plugins row
     * @return array{allowed: bool, reason: string, subscription: ?array}
     */
    public static function check(array $customer, array $plugin): array
    {
        if ($customer['status'] !== 'active') {
            return self::deny('customer_suspended');
        }

        // The Wordpresso Auto Updater plugin is the client delivery agent.
        // It is universally available to all connected active customer sites
        // so it can receive bug fixes, security updates, and protocol changes.
        if (self::isUpdaterPlugin($plugin)) {
            return [
                'allowed' => true,
                'reason' => 'entitled',
                'subscription' => null,
            ];
        }

        $subscriptions = Subscription::forCustomer((int) $customer['id']);
        if ($subscriptions === []) {
            return self::deny('no_subscription');
        }

        $pluginId = (int) $plugin['id'];
        foreach ($subscriptions as $subscription) {
            if ($subscription['status'] !== 'active') {
                continue;
            }
            if ($subscription['expires_at'] !== null && strtotime($subscription['expires_at']) <= time()) {
                continue;
            }
            if ((int) $subscription['updates_included'] !== 1) {
                continue;
            }
            if (empty($subscription['plan_id']) && !in_array($pluginId, Subscription::pluginIds((int) $subscription['id']), true)) {
                continue;
            }
            return [
                'allowed' => true,
                'reason' => 'entitled',
                'subscription' => $subscription,
            ];
        }

        return self::deny('not_entitled');
    }

    /**
     * Whether the customer's entitlement allows NATURAL automatic updates
     * (in addition to WordPress-side auto-update settings).
     */
    public static function allowsAutoUpdate(array $customer, array $plugin): bool
    {
        if ((int) $plugin['auto_update_allowed'] !== 1) {
            return false;
        }
        $result = self::check($customer, $plugin);
        return $result['allowed'];
    }

    private static function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason, 'subscription' => null];
    }
}
