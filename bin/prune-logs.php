<?php

declare(strict_types=1);

/**
 * Wordpresso — CLI / Cron script to delete old database logs.
 *
 * Usage:
 *   php bin/prune-logs.php [retention_days]
 *
 * Example cPanel Cron Job (run daily at 2:00 AM):
 *   0 2 * * * php /home/USERNAME/public_html/bin/prune-logs.php 30 >/dev/null 2>&1
 */

define('WORDPRESSO_ROOT', dirname(__DIR__));
require WORDPRESSO_ROOT . '/app/bootstrap.php';

use Wordpresso\Core\Config;
use Wordpresso\Models\UpdateLog;

Config::load(WORDPRESSO_ROOT . '/config/config.php');

$daysArg = $argv[1] ?? null;
$days = $daysArg !== null ? (int) $daysArg : (int) Config::get('log_retention_days', 30);
$days = max(1, $days);

echo '[' . date('Y-m-d H:i:s') . "] Starting database log cleanup (retention: {$days} days)...\n";

$result = UpdateLog::pruneExpired($days);

echo '[' . date('Y-m-d H:i:s') . "] Finished successfully:\n";
echo "  - Deleted update logs:       {$result['logs']}\n";
echo "  - Deleted expired nonces:    {$result['nonces']}\n";
echo "  - Deleted download tokens:   {$result['tokens']}\n";
echo "  - Deleted rate limit rows:   {$result['rate_limits']}\n";
