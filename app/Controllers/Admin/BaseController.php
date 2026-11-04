<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Response;
use Wordpresso\Core\Session;

/**
 * Base controller for the admin area: enforces an authenticated admin
 * session on every action except an explicitly allowed list.
 */
abstract class BaseController
{
    protected function requireAdmin(): void
    {
        if (Session::get('admin_id') === null) {
            Response::redirect('/admin/login')->send();
            exit;
        }
    }
}
