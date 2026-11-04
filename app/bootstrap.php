<?php

declare(strict_types=1);

/**
 * Shared bootstrap: autoloader + common imports for CLI entry points.
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Wordpresso\\')) {
        return;
    }
    $relative = substr($class, strlen('Wordpresso\\'));
    $file = WORDPRESSO_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Templates use a global e() helper defined alongside View.
require_once WORDPRESSO_ROOT . '/app/Core/View.php';
