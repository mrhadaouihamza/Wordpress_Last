<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Storefront;

use Wordpresso\Core\Config;
use Wordpresso\Core\RateLimiter;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Core\View;
use Wordpresso\Models\Customer;

final class AuthController
{
    public function loginForm(Request $request): Response
    {
        if (Session::get('customer_id') !== null) {
            return Response::redirect('/account');
        }
        return Response::html(View::page('store', 'store/login', [
            'siteName' => Config::get('site_name'),
            'customer' => null,
            'error' => Session::flash('error'),
        ]));
    }

    public function login(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        if (!RateLimiter::attempt('customer-login:' . $request->ip, 10, 300)) {
            Session::flash('error', 'Too many login attempts. Please wait a few minutes.');
            return Response::redirect('/login');
        }

        $email = strtolower(trim((string) $request->post('email', '')));
        $password = (string) $request->post('password', '');

        $customer = Customer::findByEmail($email);
        if ($customer === null || $customer['status'] !== 'active'
            || !password_verify($password, $customer['password_hash'])) {
            Session::flash('error', 'Invalid email or password.');
            return Response::redirect('/login');
        }

        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        Session::set('customer_email', $customer['email']);
        return Response::redirect('/account');
    }

    public function registerForm(Request $request): Response
    {
        return Response::html(View::page('store', 'store/register', [
            'siteName' => Config::get('site_name'),
            'customer' => null,
            'error' => Session::flash('error'),
        ]));
    }

    public function register(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        if (!RateLimiter::attempt('customer-register:' . $request->ip, 5, 3600)) {
            Session::flash('error', 'Too many attempts. Please try again later.');
            return Response::redirect('/register');
        }

        $email = strtolower(trim((string) $request->post('email', '')));
        $name = trim((string) $request->post('name', ''));
        $password = (string) $request->post('password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            return Response::redirect('/register');
        }
        if (strlen($password) < 10) {
            Session::flash('error', 'Your password must be at least 10 characters long.');
            return Response::redirect('/register');
        }
        if (Customer::findByEmail($email) !== null) {
            Session::flash('error', 'An account with this email already exists — please sign in.');
            return Response::redirect('/login');
        }

        $id = Customer::create([
            'email' => $email,
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        \Wordpresso\Models\UpdateLog::record('customer_register', 'success', 'Customer self-registered.', null, $id, null, $request->ip);

        Session::regenerate();
        Session::set('customer_id', $id);
        Session::set('customer_email', $email);
        Session::flash('success', 'Welcome! Your account is ready. Subscriptions are activated by the store after purchase.');
        return Response::redirect('/account');
    }

    public function logout(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        Session::destroy();
        return Response::redirect('/');
    }
}
