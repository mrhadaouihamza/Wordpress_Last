<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * PHP-template view renderer with a layout wrapper and escaping helper.
 */
final class View
{
    private static string $templateDir = '';

    public static function init(string $templateDir): void
    {
        self::$templateDir = rtrim($templateDir, '/');
    }

    /** Render a template file into a string. */
    public static function render(string $template, array $vars = []): string
    {
        $file = self::$templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /**
     * Render a template inside a layout.
     * Layout 'admin' wraps admin pages, 'store' wraps storefront pages, etc.
     */
    public static function page(string $layout, string $template, array $vars = []): string
    {
        $content = self::render($template, $vars);
        return self::render('layouts/' . $layout, array_merge($vars, ['content' => $content]));
    }
}

/** Global HTML-escaping helper available inside templates. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
