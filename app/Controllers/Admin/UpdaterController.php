<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Db;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;

final class UpdaterController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        $plugins = Plugin::all();
        foreach ($plugins as &$plugin) {
            $plugin['current_release'] = PluginVersion::current((int)$plugin['id']);
            $plugin['versions'] = PluginVersion::forPlugin((int)$plugin['id']);
            $plugin['draft_release'] = null;
            foreach ($plugin['versions'] as $version) {
                if ($version['status'] === 'draft') { $plugin['draft_release'] = $version; break; }
            }
        }
        unset($plugin);
        return Response::html(View::page('admin', 'admin/updater/index', [
            'siteName' => Config::get('site_name'),
            'plugins' => $plugins,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]));
    }

    public function toggleAutoUpdates(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $plugin = Plugin::find((int)$params['id']);
        if (!$plugin) return Response::error('Plugin not found.', 404);
        Db::run('UPDATE plugins SET auto_update_allowed=1-auto_update_allowed, updated_at=datetime(\'now\') WHERE id=?', [(int)$plugin['id']]);
        Session::flash('success', 'Automatic update eligibility updated for ' . $plugin['name'] . '.');
        return Response::redirect('/admin/updates');
    }
}
