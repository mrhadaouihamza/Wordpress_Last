<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * Database-backed fixed-window rate limiter.
 */
final class RateLimiter
{
    /**
     * Record a hit for $bucket and return true when under the limit.
     */
    public static function attempt(string $bucket, int $max, int $window): bool
    {
        $windowStart = intdiv(time(), $window) * $window;
        $key = hash('sha256', $bucket);

        if (Config::get('db_driver', 'sqlite') === 'mysql') {
            Db::run('INSERT INTO rate_limits (bucket_hash, window_start, hits) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE hits = hits + 1', [$key, $windowStart]);
        } else {
            Db::run('INSERT INTO rate_limits (bucket_hash, window_start, hits) VALUES (?, ?, 1)
                ON CONFLICT(bucket_hash, window_start) DO UPDATE SET hits = hits + 1', [$key, $windowStart]);
        }
        $hits = (int) Db::value(
            'SELECT hits FROM rate_limits WHERE bucket_hash = ? AND window_start = ?',
            [$key, $windowStart]
        );

        // Opportunistic cleanup of expired windows.
        if ($hits % 100 === 0) {
            Db::run('DELETE FROM rate_limits WHERE window_start < ?', [$windowStart - $window * 2]);
        }
        return $hits <= $max;
    }
}
