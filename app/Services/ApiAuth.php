<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Config;
use Wordpresso\Core\Crypto;
use Wordpresso\Core\Db;
use Wordpresso\Core\RateLimiter;
use Wordpresso\Core\Request;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Models\Website;

/**
 * API request authentication.
 *
 * Scheme: the client signs, with the HMAC-SHA256 of its secret, the
 * canonical string:
 *
 *     METHOD \n PATH \n TIMESTAMP \n sha256(RAW_BODY)
 *
 * Headers:
 *     X-WP-Key:       public api_key
 *     X-WP-Timestamp: unix seconds
 *     X-WP-Nonce:     random single-use nonce (replay protection)
 *     X-WP-Signature: hex hmac
 *
 * The secret is stored encrypted and decrypted only inside this class.
 */
final class ApiAuth
{
    /**
     * Authenticate an API request. Returns the website row on success,
     * or a Response-style error tuple [null, status, message, actionContext].
     */
    public static function authenticate(Request $request): ?array
    {
        $apiKey = (string) $request->header('x-wp-key', '');
        $timestamp = (string) $request->header('x-wp-timestamp', '');
        $nonce = (string) $request->header('x-wp-nonce', '');
        $signature = (string) $request->header('x-wp-signature', '');

        if ($apiKey === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            self::deny($request, 'missing_credentials', 'Missing authentication headers.');
            return null;
        }

        // Timestamp window.
        $window = (int) Config::get('api.timestamp_window', 300);
        if (abs(time() - (int) $timestamp) > $window) {
            self::deny($request, 'stale_timestamp', 'Timestamp outside allowed window.');
            return null;
        }

        // Website lookup (rate limit first to avoid oracle behaviour).
        if (!RateLimiter::attempt('anon:' . $request->ip, (int) Config::get('api.rate_limit_max_anon', 30), (int) Config::get('api.rate_limit_window', 300))) {
            self::deny($request, 'rate_limited', 'Too many requests.', null, 429);
            return null;
        }

        $website = Website::findByApiKey($apiKey);
        if ($website === null) {
            self::deny($request, 'unknown_key', 'Unknown API key.', null, 401);
            return null;
        }

        if (!RateLimiter::attempt(
            'website:' . $website['id'],
            (int) Config::get('api.rate_limit_max', 120),
            (int) Config::get('api.rate_limit_window', 300)
        )) {
            self::deny($request, 'rate_limited', 'Rate limit exceeded.', $website, 429);
            return null;
        }

        if ($website['status'] !== 'active') {
            self::deny($request, 'revoked', 'Website connection has been revoked.', $website, 403);
            return null;
        }

        // Single-use nonce (replay protection) — checked before the (expensive)
        // decryption so an attacker cannot burn secrets with garbage signatures.
        $nonceHash = Crypto::hashToken($nonce);
        $nonceRow = Db::fetch('SELECT created_at FROM api_nonces WHERE nonce_hash = ?', [$nonceHash]);
        $nonceTtl = (int) Config::get('api.nonce_ttl', 600);
        if ($nonceRow !== null) {
            self::deny($request, 'replayed_nonce', 'Nonce already used.', $website, 401);
            return null;
        }
        Db::run('INSERT INTO api_nonces (nonce_hash, created_at) VALUES (?, ?)', [$nonceHash, time()]);
        if (random_int(1, 20) === 1) {
            Db::run('DELETE FROM api_nonces WHERE created_at < ?', [time() - $nonceTtl * 10]);
        }

        // Signature verification.
        $secret = Crypto::decrypt($website['secret_encrypted']);
        $canonical = strtoupper($request->method) . "\n"
            . $request->path . "\n"
            . $timestamp . "\n"
            . hash('sha256', $request->rawBody);
        $expected = hash_hmac('sha256', $canonical, $secret);

        if (!hash_equals($expected, strtolower($signature))) {
            self::deny($request, 'bad_signature', 'Signature verification failed.', $website, 401);
            return null;
        }

        return $website;
    }

    private static function deny(
        Request $request,
        string $reason,
        string $message,
        ?array $website = null,
        int $status = 401,
    ): void {
        UpdateLog::record(
            'auth_failure',
            'failure',
            $message,
            $website['id'] ?? null,
            $website['customer_id'] ?? null,
            null,
            $request->ip,
            ['reason' => $reason, 'method' => $request->method, 'path' => $request->path]
        );
        // The caller returns null; the actual HTTP response is built by the
        // controller wrapper. We set a flag on the request thread via a static.
        self::$lastError = [$status, $message, $reason];
    }

    public static ?array $lastError = null;

    public static function clearLastError(): void
    {
        self::$lastError = null;
    }
}
