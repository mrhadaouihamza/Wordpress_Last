<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * Session-bound CSRF token generation and verification for HTML forms.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function verify(?string $token): bool
    {
        $stored = Session::get('_csrf');
        return is_string($stored) && is_string($token) && $token !== ''
            && hash_equals($stored, $token);
    }

    /** Throws a 419 response when the token is invalid. */
    public static function requireValid(?string $token): void
    {
        if (!self::verify($token)) {
            Response::html('<h1>419 — Session expired</h1><p>Please go back, refresh the page and try again.</p>', 419)->send();
            exit;
        }
    }
}
