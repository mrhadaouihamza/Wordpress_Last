<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Api;

use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Services\ApiAuth;

/**
 * Thin gateway so API controllers share one auth entry point and one
 * consistent error response built from ApiAuth::$lastError.
 */
final class ApiAuthGateway
{
    public static function auth(Request $request): ?array
    {
        ApiAuth::clearLastError();
        return ApiAuth::authenticate($request);
    }

    public static function errorResponse(): Response
    {
        $error = ApiAuth::$lastError ?? [401, 'Authentication failed.', 'unknown'];
        return Response::json(['success' => false, 'error' => $error[1], 'code' => $error[2]], $error[0]);
    }
}
