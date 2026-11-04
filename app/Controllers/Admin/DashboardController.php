<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Db;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\UpdateLog;

final class DashboardController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();

        $staleMinutes = (int) Config::get('stale_check_minutes', 36);

        $stats = [
            'plugins' => (int) Db::value('SELECT COUNT(*) FROM plugins'),
            'themes' => (int) Db::value('SELECT COUNT(*) FROM themes'),
            'published_versions' => (int) Db::value("SELECT COUNT(*) FROM plugin_versions WHERE status = 'published'"),
            'customers' => (int) Db::value('SELECT COUNT(*) FROM customers'),
            'active_subscriptions' => (int) Db::value("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"),
            'websites' => (int) Db::value('SELECT COUNT(*) FROM websites'),
            'active_websites' => (int) Db::value("SELECT COUNT(*) FROM websites WHERE status = 'active'"),
            'checks_24h' => (int) Db::value("SELECT COUNT(*) FROM update_logs WHERE action = 'check' AND created_at > datetime('now', '-1 day')"),
            'downloads_24h' => (int) Db::value("SELECT COUNT(*) FROM update_logs WHERE action = 'download' AND outcome = 'success' AND created_at > datetime('now', '-1 day')"),
            'failures_24h' => (int) Db::value("SELECT COUNT(*) FROM update_logs WHERE outcome = 'failure' AND created_at > datetime('now', '-1 day')"),
        ];

        $staleSites = Db::fetchAll(
            "SELECT w.*, c.email AS customer_email FROM websites w
             JOIN customers c ON c.id = w.customer_id
             WHERE w.status = 'active'
               AND (w.last_check_at IS NULL OR w.last_check_at < ?)
             ORDER BY w.last_check_at IS NULL DESC, w.last_check_at
             LIMIT 10",
            [gmdate('Y-m-d H:i:s', time() - $staleMinutes * 60)]
        );

        $recentFailures = UpdateLog::search(['outcome' => 'failure'], 1, 10);

        return Response::html(View::page('admin', 'admin/dashboard', [
            'siteName' => Config::get('site_name'),
            'stats' => $stats,
            'staleSites' => $staleSites,
            'recentFailures' => $recentFailures['rows'],
            'flash' => Session::flash('success'),
        ]));
    }
}
