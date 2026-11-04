<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Crypto;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Db;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Customer;
use Wordpresso\Models\Subscription;
use Wordpresso\Models\UpdateLog;

final class CustomerController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->requireAdmin();
        $customers = Customer::all();
        foreach ($customers as &$customer) {
            $customer['website_count'] = (int) Db::value('SELECT COUNT(*) FROM websites WHERE customer_id = ?', [$customer['id']]);
            $customer['active_subscriptions'] = (int) Db::value(
                "SELECT COUNT(*) FROM subscriptions WHERE customer_id = ? AND status = 'active'",
                [$customer['id']]
            );
        }
        return Response::html(View::page('admin', 'admin/customers/index', [
            'siteName' => Config::get('site_name'),
            'customers' => $customers,
            'flash' => Session::flash('success'),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();
        return Response::html(View::page('admin', 'admin/customers/form', [
            'siteName' => Config::get('site_name'),
            'customer' => null,
            'subscriptions' => [],
            'generatedPassword' => Session::flash('generated_password'),
            'error' => Session::flash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $email = strtolower(trim((string) $request->post('email', '')));
        $name = trim((string) $request->post('name', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'A valid email address is required.');
            return Response::redirect('/admin/customers/new');
        }
        if (Customer::findByEmail($email) !== null) {
            Session::flash('error', 'A customer with this email already exists.');
            return Response::redirect('/admin/customers/new');
        }

        $password = Crypto::token(10);
        $id = Customer::create([
            'email' => $email,
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        Session::flash('generated_password', $password);
        Session::flash('success', "Customer created. Initial password for {$email}: {$password}");
        return Response::redirect('/admin/customers/' . $id);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }
        return $this->showResponse($customer, Session::flash('generated_password'));
    }

    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }
        return Response::html(View::page('admin', 'admin/customers/form', [
            'siteName' => Config::get('site_name'),
            'customer' => $customer,
            'subscriptions' => Customer::subscriptions((int) $customer['id']),
            'generatedPassword' => null,
            'error' => Session::flash('error'),
        ]));
    }

    public function update(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }

        $email = strtolower(trim((string) $request->post('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'A valid email address is required.');
            return Response::redirect('/admin/customers/' . $customer['id'] . '/edit');
        }
        $existing = Customer::findByEmail($email);
        if ($existing !== null && (int) $existing['id'] !== (int) $customer['id']) {
            Session::flash('error', 'Another customer already uses this email.');
            return Response::redirect('/admin/customers/' . $customer['id'] . '/edit');
        }

        Customer::update((int) $customer['id'], [
            'email' => $email,
            'name' => trim((string) $request->post('name', '')),
        ]);

        // Optional password reset.
        $newPassword = trim((string) $request->post('new_password', ''));
        if ($newPassword !== '') {
            if (strlen($newPassword) < 10) {
                Session::flash('error', 'The new password must be at least 10 characters.');
                return Response::redirect('/admin/customers/' . $customer['id'] . '/edit');
            }
            Customer::setPassword((int) $customer['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        }

        Session::flash('success', 'Customer updated.');
        return Response::redirect('/admin/customers/' . $customer['id']);
    }

    public function toggleSuspend(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }
        Customer::setStatus((int) $customer['id'], $customer['status'] === 'active' ? 'suspended' : 'active');
        Session::flash('success', $customer['status'] === 'active' ? 'Customer suspended.' : 'Customer reactivated.');
        return Response::redirect('/admin/customers/' . $customer['id']);
    }

    /**
     * Create or update a subscription with its per-plugin entitlements.
     * POST fields: id? plan_name status updates_included starts_at expires_at
     */
    public function saveSubscription(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) {
            return Response::error('Customer not found.', 404);
        }

        $startsAt = trim((string) $request->post('starts_at', ''));
        $expiresAt = trim((string) $request->post('expires_at', ''));
        if ($startsAt === '' || ($startsAt !== '' && strtotime($startsAt) === false)) {
            Session::flash('error', 'A valid start date is required.');
            return Response::redirect('/admin/customers/' . $customer['id']);
        }
        if ($expiresAt !== '' && strtotime($expiresAt) === false) {
            Session::flash('error', 'The expiry date is invalid (leave empty for no expiry).');
            return Response::redirect('/admin/customers/' . $customer['id']);
        }

        $data = [
            'customer_id' => (int) $customer['id'],
            'plan_name' => trim((string) $request->post('plan_name', '')),
            'status' => in_array($request->post('status'), ['active', 'expired', 'cancelled'], true) ? $request->post('status') : 'active',
            'updates_included' => (int) $request->post('updates_included', 0),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
        ];

        $subscriptionId = (int) $request->post('subscription_id', 0);
        if ($subscriptionId <= 0) {
            Session::flash('error', 'Customer subscriptions are created automatically from store purchases. Create or edit subscription plans under Admin → Subscriptions.');
            return Response::redirect('/admin/customers/' . $customer['id']);
        }
        if ($subscriptionId > 0) {
            $existing = Subscription::find($subscriptionId);
            if ($existing === null || (int) $existing['customer_id'] !== (int) $customer['id']) {
                return Response::error('Subscription not found.', 404);
            }
            Subscription::update($subscriptionId, $data);
            Session::flash('success', 'Subscription updated.');
        }

        return Response::redirect('/admin/customers/' . $customer['id']);
    }

    public function assignPlan(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customer = Customer::find((int)$params['id']);
        if ($customer === null) return Response::error('Customer not found.', 404);
        $plan = Db::fetch('SELECT * FROM plans WHERE id = ? AND active = 1', [(int)$request->post('plan_id', 0)]);
        if ($plan === null) {
            Session::flash('error', 'Choose a published subscription plan.');
            return Response::redirect('/admin/customers/' . $customer['id']);
        }

        $startsAt = gmdate('Y-m-d H:i:s');
        $months = (int)$plan['term_months'];
        $expiresAt = $months <= 0 || $months >= 1200 ? null : gmdate('Y-m-d H:i:s', strtotime("+{$months} months"));
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $subscriptionId = Subscription::createFromStorePlan(
                (int)$customer['id'], $plan, $startsAt, $expiresAt, $plan['name'] . ' (admin assigned)'
            );
            $pdo->commit();
            UpdateLog::record('subscription_assigned', 'success', 'Store subscription plan assigned by administrator.', null, (int)$customer['id'], null, $request->ip, ['subscription_id' => $subscriptionId, 'plan_id' => (int)$plan['id']]);
            Session::flash('success', 'Subscription plan assigned and included product access activated.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Session::flash('error', 'Could not assign this subscription plan: ' . $e->getMessage());
        }
        return Response::redirect('/admin/customers/' . $customer['id']);
    }

    public function deleteSubscription(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $subscription = Subscription::find((int) $params['id']);
        if ($subscription === null) {
            return Response::error('Subscription not found.', 404);
        }
        Subscription::delete((int) $subscription['id']);
        Session::flash('success', 'Subscription deleted.');
        return Response::redirect('/admin/customers/' . $subscription['customer_id']);
    }

    public function delete(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $customer = Customer::find((int) $params['id']);
        if ($customer === null) return Response::error('Customer not found.', 404);
        Customer::deleteAccount((int) $customer['id']);
        Session::flash('success', 'Customer and related data deleted.');
        return Response::redirect('/admin/customers');
    }

    private function showResponse(array $customer, ?string $generatedPassword): Response
    {
        return Response::html(View::page('admin', 'admin/customers/show', [
            'siteName' => Config::get('site_name'),
            'customer' => $customer,
            'subscriptions' => Customer::subscriptions((int) $customer['id']),
            'plans' => Db::fetchAll('SELECT * FROM plans WHERE active = 1 ORDER BY name'),
            'websites' => \Wordpresso\Models\Website::forCustomer((int) $customer['id']),
            'generatedPassword' => $generatedPassword,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]));
    }
}
