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

final class LoginController extends BaseController
{
    public function form(Request $request): Response
    {
        if (Session::get('admin_id') !== null) {
            return Response::redirect('/admin');
        }
        return Response::html(View::page('admin', 'admin/login', [
            'siteName' => Config::get('site_name'),
            'error' => Session::flash('error'),
        ]));
    }

    public function login(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);

        // Simple per-IP throttle for the login form.
        if (!\Wordpresso\Core\RateLimiter::attempt('admin-login:' . $request->ip, 10, 300)) {
            Session::flash('error', 'Too many login attempts. Try again later.');
            return Response::redirect('/admin/login');
        }

        $email = strtolower(trim((string) $request->post('email', '')));
        $password = (string) $request->post('password', '');

        $admin = Db::fetch('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($admin === null || !password_verify($password, $admin['password_hash'])) {
            Session::flash('error', 'Invalid credentials.');
            return Response::redirect('/admin/login');
        }

        Session::regenerate();
        Session::set('admin_id', (int) $admin['id']);
        Session::set('admin_email', $admin['email']);
        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        Csrf::requireValid($request->post('_csrf') ?? null);
        Session::destroy();
        return Response::redirect('/admin/login');
    }
}
