<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;

final class PluginController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        $plugins = Plugin::all();
        foreach ($plugins as &$plugin) {
            $plugin['current_version'] = PluginVersion::current((int) $plugin['id'])['version'] ?? null;
            $plugin['version_count'] = (int) \Wordpresso\Core\Db::value(
                'SELECT COUNT(*) FROM plugin_versions WHERE plugin_id = ?',
                [$plugin['id']]
            );
        }
        return Response::html(View::page('admin', 'admin/plugins/index', [
            'siteName' => Config::get('site_name'),
            'plugins' => $plugins,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();
        return Response::html(View::page('admin', 'admin/plugins/form', [
            'siteName' => Config::get('site_name'),
            'plugin' => null,
            'error' => Session::flash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $data = $this->extract($request);
        $error = $this->validate($data);
        if ($error !== null) {
            Session::flash('error', $error);
            return Response::redirect('/admin/plugins/new');
        }

        Plugin::create($data);
        Session::flash('success', 'Plugin created. Now upload its first version.');
        return Response::redirect('/admin/plugins');
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $plugin = Plugin::find((int) $params['id']);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }
        return Response::html(View::page('admin', 'admin/plugins/show', [
            'siteName' => Config::get('site_name'),
            'plugin' => $plugin,
            'versions' => PluginVersion::forPlugin((int) $plugin['id']),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]));
    }

    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $plugin = Plugin::find((int) $params['id']);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }
        return Response::html(View::page('admin', 'admin/plugins/form', [
            'siteName' => Config::get('site_name'),
            'plugin' => $plugin,
            'error' => Session::flash('error'),
        ]));
    }

    public function update(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $plugin = Plugin::find((int) $params['id']);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }

        $data = $this->extract($request);
        $error = $this->validate($data, (int) $plugin['id']);
        if ($error !== null) {
            Session::flash('error', $error);
            return Response::redirect('/admin/plugins/' . $plugin['id'] . '/edit');
        }

        Plugin::update((int) $plugin['id'], $data);
        Session::flash('success', 'Plugin updated.');
        return Response::redirect('/admin/plugins/' . $plugin['id']);
    }

    public function toggle(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $plugin = Plugin::find((int) $params['id']);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }
        Plugin::toggleEnabled((int) $plugin['id']);
        Session::flash('success', 'Update distribution toggled.');
        return Response::redirect('/admin/plugins/' . $plugin['id']);
    }

    private function extract(Request $request): array
    {
        return [
            'slug' => strtolower(trim((string) $request->post('slug', ''))),
            'name' => trim((string) $request->post('name', '')),
            'basename' => trim((string) $request->post('basename', '')),
            'description' => trim((string) $request->post('description', '')),
            'auto_update_allowed' => (int) $request->post('auto_update_allowed', 0),
            'min_wp_version' => trim((string) $request->post('min_wp_version', '')),
            'min_php_version' => trim((string) $request->post('min_php_version', '')),
            'tested_wp_version' => trim((string) $request->post('tested_wp_version', '')),
        ];
    }

    private function validate(array $data, ?int $ignoreId = null): ?string
    {
        if ($data['slug'] === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $data['slug'])) {
            return 'Slug is required (lowercase letters, digits, dashes).';
        }
        if ($data['name'] === '') {
            return 'Name is required.';
        }
        if ($data['basename'] === '' || !str_contains($data['basename'], '.php')) {
            return 'Technical identifier (basename) is required, e.g. elementor-pro/elementor-pro.php';
        }
        $existingSlug = Plugin::findBySlug($data['slug']);
        if ($existingSlug !== null && (int) $existingSlug['id'] !== ($ignoreId ?? -1)) {
            return 'A plugin with this slug already exists.';
        }
        $existingBase = Plugin::findByBasename($data['basename']);
        if ($existingBase !== null && (int) $existingBase['id'] !== ($ignoreId ?? -1)) {
            return 'A plugin with this basename already exists.';
        }
        return null;
    }
}
