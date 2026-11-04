<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Models\Website;

final class WebsiteController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        $websites = Website::allWithCustomer();
        $staleMinutes = (int) Config::get('stale_check_minutes', 36);
        foreach ($websites as &$website) {
            $website['plugin_count'] = (int) \Wordpresso\Core\Db::value(
                "SELECT COUNT(*) FROM update_logs WHERE website_id = ? AND action = 'check'",
                [$website['id']]
            );
            $website['is_stale'] = $website['status'] === 'active'
                && ($website['last_check_at'] === null
                    || strtotime($website['last_check_at']) < time() - $staleMinutes * 60);
        }
        return Response::html(View::page('admin', 'admin/websites/index', [
            'siteName' => Config::get('site_name'),
            'websites' => $websites,
            'flash' => Session::flash('success'),
        ]));
    }

    public function revoke(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $website = Website::find((int) $params['id']);
        if ($website === null) {
            return Response::error('Website not found.', 404);
        }
        Website::revoke((int) $website['id']);
        UpdateLog::record('revoke', 'success', 'Website connection revoked by administrator.', (int) $website['id'], (int) $website['customer_id'], null, $request->ip);
        Session::flash('success', 'Website revoked. It can no longer check for or download updates.');
        return Response::redirect('/admin/websites');
    }

    public function activate(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $website = Website::find((int) $params['id']);
        if ($website === null) {
            return Response::error('Website not found.', 404);
        }
        Website::activate((int) $website['id']);
        Session::flash('success', 'Website reactivated.');
        return Response::redirect('/admin/websites');
    }

    public function delete(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $website = Website::find((int)$params['id']);
        if ($website === null) return Response::error('Website not found.', 404);
        UpdateLog::record('website_delete', 'success', 'Website and API credentials permanently deleted by administrator.', (int)$website['id'], (int)$website['customer_id'], null, $request->ip);
        Website::delete((int)$website['id']);
        Session::flash('success', 'Website and its API credentials were permanently deleted.');
        return Response::redirect('/admin/websites');
    }
}
