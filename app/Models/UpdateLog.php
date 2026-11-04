<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class UpdateLog
{
    /**
     * Record an operational event. $context must never contain secrets —
     * callers are responsible for sanitizing what they put in it.
     */
    public static function record(
        string $action,
        string $outcome = 'success',
        string $message = '',
        ?int $websiteId = null,
        ?int $customerId = null,
        ?int $pluginId = null,
        string $ip = '',
        array $context = [],
    ): void {
        Db::run(
            'INSERT INTO update_logs (website_id, customer_id, plugin_id, action, outcome,
                message, ip, context)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $websiteId, $customerId, $pluginId, $action, $outcome, $message, $ip,
                json_encode($context, JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    /**
     * Filtered query for the admin log view.
     *
     * @return array{rows: array<int, array>, total: int}
     */
    public static function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['action'])) {
            $where[] = 'l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['outcome'])) {
            $where[] = 'l.outcome = ?';
            $params[] = $filters['outcome'];
        }
        if (!empty($filters['website_id'])) {
            $where[] = 'l.website_id = ?';
            $params[] = (int) $filters['website_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'l.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(l.message LIKE ? OR w.url LIKE ? OR c.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $total = (int) Db::value(
            "SELECT COUNT(*) FROM update_logs l
             LEFT JOIN websites w ON w.id = l.website_id
             LEFT JOIN customers c ON c.id = l.customer_id
             WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Db::fetchAll(
            "SELECT l.*, w.url AS website_url, c.email AS customer_email, p.name AS plugin_name
             FROM update_logs l
             LEFT JOIN websites w ON w.id = l.website_id
             LEFT JOIN customers c ON c.id = l.customer_id
             LEFT JOIN plugins p ON p.id = l.plugin_id
             WHERE {$whereSql}
             ORDER BY l.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Distinct action values present in the log (for the filter dropdown). */
    public static function actions(): array
    {
        return array_column(Db::fetchAll('SELECT DISTINCT action FROM update_logs ORDER BY action'), 'action');
    }

    /**
     * Delete log entries older than $days.
     * If $action is provided, only delete entries matching that action.
     * Returns the number of deleted records.
     */
    public static function prune(int $days, ?string $action = null): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(0, $days) * 86400);
        if ($action !== null && $action !== '') {
            $stmt = Db::run('DELETE FROM update_logs WHERE created_at < ? AND action = ?', [$cutoff, $action]);
        } else {
            $stmt = Db::run('DELETE FROM update_logs WHERE created_at < ?', [$cutoff]);
        }
        return $stmt->rowCount();
    }

    /**
     * Delete all log entries (truncate content).
     * Returns the number of deleted records.
     */
    public static function clearAll(): int
    {
        $stmt = Db::run('DELETE FROM update_logs');
        return $stmt->rowCount();
    }

    /**
     * Prune expired database records:
     * - update_logs older than $retentionDays (if > 0)
     * - expired api_nonces (older than 1 hour)
     * - expired download_tokens (expires_at < current timestamp)
     * - expired rate_limits (window_start older than 1 hour)
     *
     * @return array{logs: int, nonces: int, tokens: int, rate_limits: int}
     */
    public static function pruneExpired(int $retentionDays = 30): array
    {
        $deletedLogs = 0;
        if ($retentionDays > 0) {
            $deletedLogs = self::prune($retentionDays);
        }

        $now = time();
        try {
            $nonceCutoff = $now - 3600;
            $deletedNonces = Db::run('DELETE FROM api_nonces WHERE created_at < ?', [$nonceCutoff])->rowCount();
        } catch (\Throwable) {
            $deletedNonces = 0;
        }

        try {
            $deletedTokens = Db::run('DELETE FROM download_tokens WHERE expires_at < ?', [$now])->rowCount();
        } catch (\Throwable) {
            $deletedTokens = 0;
        }

        try {
            $rateLimitCutoff = $now - 3600;
            $deletedRateLimits = Db::run('DELETE FROM rate_limits WHERE window_start < ?', [$rateLimitCutoff])->rowCount();
        } catch (\Throwable) {
            $deletedRateLimits = 0;
        }

        return [
            'logs' => $deletedLogs,
            'nonces' => $deletedNonces,
            'tokens' => $deletedTokens,
            'rate_limits' => $deletedRateLimits,
        ];
    }
}
