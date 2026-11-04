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
use Wordpresso\Services\StoreCatalog;

/**
 * Subscription plans live in their own `plans` table. Plans are all-access:
 * every published plan includes all active plugins and themes, so there is no
 * per-plan product selection and no website limit.
 */
final class SubscriptionController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        $plans = StoreCatalog::plans(false);
        foreach ($plans as &$plan) {
            $plan['included_count'] = count(StoreCatalog::listings(true));
        }
        unset($plan);

        return Response::html(View::page('admin', 'admin/subscriptions/index', [
            'siteName' => Config::get('site_name'),
            'plans' => $plans,
            'flash' => Session::flash('success'),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();
        return $this->form(null);
    }

    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $plan = StoreCatalog::find('plan', (int) $params['id']);
        if (!$plan) {
            return Response::error('Subscription plan not found.', 404);
        }
        return $this->form($plan);
    }

    public function save(Request $request, array $params = []): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $id = (int) ($params['id'] ?? $request->post('plan_id', 0));
        $existing = $id ? StoreCatalog::find('plan', $id) : null;
        if ($id && !$existing) {
            return Response::error('Subscription plan not found.', 404);
        }

        $name = trim((string) $request->post('name', ''));
        $slug = strtolower(trim((string) $request->post('slug', '')));
        $description = trim((string) $request->post('description', ''));
        $price = (float) $request->post('price', 0);
        $months = (int) $request->post('term_months', 12);
        $websiteLimit = max(0, (int) $request->post('website_limit', 0));
        $active = $request->post('active') !== null ? 1 : (int) ($existing['active'] ?? 1);

        $duplicate = Db::value('SELECT id FROM plans WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($name === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) || $price < 0 || $months < 0 || $duplicate !== null) {
            Session::flash('error', 'Enter a name, unique lowercase slug, non-negative price, and valid access period.');
            return Response::redirect($id ? '/admin/subscriptions/' . $id . '/edit' : '/admin/subscriptions/new');
        }
        // Checkout refuses a zero-total order, so a published plan must have a
        // price; a $0 plan can still be saved as a hidden draft.
        if ($active === 1 && $price <= 0) {
            Session::flash('error', 'Set a price above zero to publish this plan, or save it as a hidden draft.');
            return Response::redirect($id ? '/admin/subscriptions/' . $id . '/edit' : '/admin/subscriptions/new');
        }

        $newImage = null;
        $imageUpload = $_FILES['product_image'] ?? null;
        if ($imageUpload && (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newImage = \Wordpresso\Services\ProductImageStorage::store($imageUpload);
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
                return Response::redirect($id ? '/admin/subscriptions/' . $id . '/edit' : '/admin/subscriptions/new');
            }
        }
        $oldImage = $existing['image_path'] ?? null;

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($existing) {
                Db::run(
                    'UPDATE plans SET slug = ?, name = ?, description = ?, price = ?, term_months = ?, website_limit = ?, image_path = ?, active = ? WHERE id = ?',
                    [$slug, $name, $description, $price, $months, $websiteLimit, $newImage ?? $oldImage, $active, $id]
                );
            } else {
                $id = Db::insert(
                    'INSERT INTO plans (slug, name, description, price, term_months, website_limit, image_path, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$slug, $name, $description, $price, $months, $websiteLimit, $newImage, $active]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \Wordpresso\Services\ProductImageStorage::delete($newImage);
            Session::flash('error', 'Could not save the subscription plan. Check the database schema.');
            return Response::redirect($existing ? '/admin/subscriptions/' . $id . '/edit' : '/admin/subscriptions/new');
        }

        if ($newImage && $oldImage) {
            \Wordpresso\Services\ProductImageStorage::delete($oldImage);
        }
        Session::flash('success', $existing ? 'Subscription plan updated.' : 'Subscription plan created. ' . ($active ? 'It is published and ready to sell.' : 'Save it as published when it is ready to sell.'));
        return Response::redirect('/admin/subscriptions');
    }

    public function toggle(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        Db::run('UPDATE plans SET active = 1 - active WHERE id = ?', [(int) $params['id']]);
        Session::flash('success', 'Subscription plan visibility updated.');
        return Response::redirect('/admin/subscriptions');
    }

    public function delete(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $id = (int) $params['id'];
        $plan = StoreCatalog::find('plan', $id);
        if (!$plan) {
            return Response::error('Subscription plan not found.', 404);
        }
        \Wordpresso\Services\ProductImageStorage::delete($plan['image_path'] ?? null);
        Db::run('DELETE FROM plans WHERE id = ?', [$id]);
        Session::flash('success', 'Subscription plan deleted.');
        return Response::redirect('/admin/subscriptions');
    }

    private function form(?array $plan): Response
    {
        return Response::html(View::page('admin', 'admin/subscriptions/form', [
            'siteName' => Config::get('site_name'),
            'plan' => $plan,
            'error' => Session::flash('error'),
        ]));
    }
}
