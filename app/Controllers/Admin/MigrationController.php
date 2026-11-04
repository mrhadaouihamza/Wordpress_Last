<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Db;
use Wordpresso\Core\Config;
use Wordpresso\Core\Migrator;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;

final class MigrationController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        return $this->page('admin/migrations/index', [
            'applied' => $this->applied(),
            'pending' => $this->pending($this->applied()),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function run(Request $request): Response
    {
        $this->requireAdmin();
        \Wordpresso\Core\Csrf::requireValid($request->post('_csrf') ?? null);
        try {
            $ran = (new Migrator(WORDPRESSO_ROOT . '/migrations'))->migrate();
            Session::flash('success', $ran ? 'Migrations applied: ' . implode(', ', $ran) : 'Database is already up to date.');
        } catch (\Throwable $e) {
            error_log('Wordpresso migration failed: ' . $e->getMessage());
            Session::flash('error', 'Migration failed. Check the server log for details.');
        }
        return Response::redirect('/admin/migrations');
    }

    private function applied(): array
    {
        try {
            return array_column(Db::fetchAll('SELECT name FROM schema_migrations ORDER BY name'), 'name');
        } catch (\Throwable) {
            return [];
        }
    }

    private function pending(array $applied): array
    {
        $pending = [];
        foreach (glob(WORDPRESSO_ROOT . '/migrations/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        sort($pending);
        return $pending;
    }

    private function page(string $template, array $vars = []): Response
    {
        return Response::html(View::page('admin', $template, array_merge([
            'siteName' => Config::get('site_name'),
            'title' => 'Database migrations',
        ], $vars)));
    }
}
