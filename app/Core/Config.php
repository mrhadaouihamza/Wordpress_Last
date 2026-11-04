<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * Lazy-loading configuration accessor.
 */
final class Config
{
    private static ?array $values = null;

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Configuration file not found: ' . $file);
        }
        self::$values = require $file;
        $local = dirname($file) . '/config.local.php';
        if (is_file($local)) {
            self::$values = array_replace(self::$values, require $local);
        }
    }

    /**
     * Dot-notation getter with optional default: Config::get('api.timestamp_window').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$values === null) {
            throw new \RuntimeException('Configuration not loaded.');
        }
        $segments = explode('.', $key);
        $value = self::$values;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$values ?? [];
    }
}
