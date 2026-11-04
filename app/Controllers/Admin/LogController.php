<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Models\Website;

final class LogController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();

        $filters = [
            'action' => (string) $request->query('action', ''),
            'outcome' => (string) $request->query('outcome', ''),
            'website_id' => (string) $request->query('website_id', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = UpdateLog::search($filters, $page, 50);

        return Response::html(View::page('admin', 'admin/logs/index', [
            'siteName' => \Wordpresso\Core\Config::get('site_name'),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 50,
            'filters' => $filters,
            'actions' => UpdateLog::actions(),
            'websites' => Website::allWithCustomer(),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]));
    }

    public function prune(Request $request): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $days = (string) $request->post('days', '30');
        $action = trim((string) $request->post('action', ''));
        $actionFilter = $action !== '' ? $action : null;

        try {
            if ($days === 'all') {
                if ($actionFilter !== null) {
                    $deleted = UpdateLog::prune(0, $actionFilter);
                    Session::flash('success', "Deleted {$deleted} log entries for action '{$actionFilter}'.");
                } else {
                    $deleted = UpdateLog::clearAll();
                    Session::flash('success', "Cleared all database logs ({$deleted} entries deleted).");
                }
            } else {
                $daysInt = max(1, (int) $days);
                $deleted = UpdateLog::prune($daysInt, $actionFilter);
                $actionNote = $actionFilter ? " for action '{$actionFilter}'" : '';
                Session::flash('success', "Deleted {$deleted} log entries older than {$daysInt} days{$actionNote}.");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to delete logs: ' . $e->getMessage());
        }

        return Response::redirect('/admin/logs');
    }
}
