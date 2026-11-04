<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Crypto;
use Wordpresso\Core\Db;

final class DownloadToken
{
    /**
     * Issue a single-use download token for a website + plugin version.
     * Returns the plaintext token (returned to the client once).
     */
    public static function issue(int $websiteId, int $versionId, int $ttl): string
    {
        $token = Crypto::token(32);
        Db::run(
            'INSERT INTO download_tokens (token_hash, website_id, plugin_version_id, expires_at)
             VALUES (?, ?, ?, ?)',
            [Crypto::hashToken($token), $websiteId, $versionId, time() + $ttl]
        );
        // Opportunistic cleanup.
        Db::run('DELETE FROM download_tokens WHERE expires_at < ?', [time() - 86400]);
        return $token;
    }

    /**
     * Atomically consume a token: valid, unexpired, unused. Returns the row or null.
     */
    public static function consume(string $token): ?array
    {
        $hash = Crypto::hashToken($token);
        $row = Db::fetch('SELECT * FROM download_tokens WHERE token_hash = ?', [$hash]);
        if ($row === null) {
            return null;
        }
        if ($row['used_at'] !== null || (int) $row['expires_at'] < time()) {
            return null;
        }
        $updated = Db::run(
            'UPDATE download_tokens SET used_at = datetime(\'now\')
             WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ?',
            [$hash, time()]
        );
        if ($updated->rowCount() === 0) {
            return null; // concurrent use — single-use semantics enforced
        }
        return $row;
    }
}
