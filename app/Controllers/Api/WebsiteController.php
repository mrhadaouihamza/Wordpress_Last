<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Api;

use Wordpresso\Core\Config;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Models\UpdateLog;

/**
 * POST /api/v1/register
 *
 * Associates a WordPress site with the customer account that owns the API
 * key. The request must be HMAC-signed with the customer's API secret, so
 * only someone who holds the credentials can register a website.
 *
 * Body: {url, name?, wp_version?, php_version?}
 * Returns: {success, website: {id, name, url, status}}
 */
final class WebsiteController
{
    public function register(Request $request): Response
    {
        $website = ApiAuthGateway::auth($request);
        if ($website === null) {
            return ApiAuthGateway::errorResponse();
        }

        $url = rtrim((string) $request->input('url', ''), '/');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'http')) {
            UpdateLog::record('register', 'failure', 'Invalid site URL.', (int) $website['id'], (int) $website['customer_id'], null, $request->ip);
            return Response::error('A valid site URL is required.', 422);
        }

        $name = trim((string) $request->input('name', ''));
        $wpVersion = substr((string) $request->input('wp_version', ''), 0, 32);
        $phpVersion = substr((string) $request->input('php_version', ''), 0, 32);

        // Update the registration details (the key was created by the customer
        // in the storefront; this call confirms which site uses it).
        \Wordpresso\Core\Db::run(
            'UPDATE websites SET url = ?, name = ?, wp_version = ?, php_version = ?,
                status = \'active\', revoked_at = NULL WHERE id = ?',
            [$url, $name !== '' ? $name : $url, $wpVersion, $phpVersion, $website['id']]
        );

        UpdateLog::record(
            'register', 'success', 'Website registered.',
            (int) $website['id'], (int) $website['customer_id'], null, $request->ip,
            ['url' => $url]
        );

        return Response::json([
            'success' => true,
            'website' => [
                'id' => (int) $website['id'],
                'name' => $name !== '' ? $name : $url,
                'url' => $url,
                'status' => 'active',
            ],
        ]);
    }
}
