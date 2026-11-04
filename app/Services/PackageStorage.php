<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Config;

/**
 * Protected package storage.
 *
 * ZIP files live in storage/packages/{slug}/{version}-{sha8}.zip —
 * OUTSIDE the web docroot, so they can never be fetched by a static URL.
 * The only way to obtain a package is the authorized download endpoint.
 */
final class PackageStorage
{
    public static function baseDir(): string
    {
        $dir = (string) Config::get('packages_dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir;
    }

    /**
     * Move an uploaded temp file into protected storage under a content-
     * addressed name. Returns the path relative to the base directory.
     */
    public static function store(string $tmpPath, string $pluginSlug, string $version, string $sha256): string
    {
        $dir = self::baseDir() . '/' . self::safeSegment($pluginSlug);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $relative = self::safeSegment($pluginSlug) . '/' . self::safeSegment($version)
            . '-' . substr($sha256, 0, 8) . '.zip';
        $target = self::baseDir() . '/' . $relative;

        if (!rename($tmpPath, $target)) {
            // Cross-device fallback.
            if (!copy($tmpPath, $target)) {
                throw new \RuntimeException('Failed to move the package into storage.');
            }
            unlink($tmpPath);
        }
        chmod($target, 0640);
        return $relative;
    }

    /** Absolute path for a stored relative path; verifies integrity. */
    public static function absolutePath(string $relative, string $expectedSha256): ?string
    {
        $path = self::baseDir() . '/' . $relative;
        if (!is_file($path)) {
            return null;
        }
        // Defense in depth: never serve a file that escaped the base dir.
        $real = realpath($path);
        $base = realpath(self::baseDir());
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        $actual = hash_file('sha256', $path);
        if ($actual === false || !hash_equals($expectedSha256, $actual)) {
            return null; // integrity failure — refuse to serve
        }
        return $real;
    }

    public static function delete(string $relative): void
    {
        $path = self::baseDir() . '/' . $relative;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function safeSegment(string $segment): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '-', $segment);
        return trim((string) $clean, '.-') ?: 'unnamed';
    }
}
