<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Config;

/**
 * Validates an uploaded plugin ZIP package:
 *  - it is a real ZIP readable by ZipArchive
 *  - it is under the size limit
 *  - it contains the expected main plugin file (the basename registered in
 *    the catalog, e.g. "elementor-pro/elementor-pro.php")
 *  - the plugin header "Plugin Name" is present and the "Version" header
 *    matches (or can be overridden to) the version entered by the admin
 *  - it does not contain dangerous entries (path traversal, absolute paths)
 */
final class PackageValidator
{
    /**
     * @return array{ok: bool, errors: string[], headers: array<string,string>, size: int, sha256: string}
     */
    public static function validate(string $tmpPath, string $basename, string $expectedVersion): array
    {
        $errors = [];
        $headers = [];
        $size = filesize($tmpPath) ?: 0;
        $maxBytes = (int) Config::get('max_package_bytes', 67108864);

        if ($size === 0) {
            return self::fail(['Uploaded file is empty.']);
        }
        if ($size > $maxBytes) {
            return self::fail([sprintf('Package exceeds the maximum size of %d MB.', (int) ($maxBytes / 1048576))]);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return self::fail(['The uploaded file is not a valid ZIP archive.']);
        }

        try {
            // Reject traversal / absolute paths inside the archive.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (str_starts_with($entry, '/') || str_contains($entry, '..')) {
                    $errors[] = 'Archive contains an unsafe path: ' . $entry;
                    break;
                }
            }

            // The main plugin file must exist at the declared basename.
            $mainFile = $zip->getFromName($basename);
            if ($mainFile === false) {
                $errors[] = sprintf(
                    'Main plugin file "%s" not found in the archive. Expected a plugin folder matching the registered basename.',
                    $basename
                );
            } else {
                $headers = self::parsePluginHeaders($mainFile);
                if (!isset($headers['PluginName']) && !isset($headers['Name'])) {
                    $errors[] = 'The main plugin file has no "Plugin Name" header — it does not look like a WordPress plugin.';
                }
                $archiveVersion = $headers['Version'] ?? '';
                if ($archiveVersion === '') {
                    $errors[] = 'The main plugin file has no "Version" header.';
                } elseif (strtolower($archiveVersion) !== strtolower($expectedVersion)) {
                    $errors[] = sprintf(
                        'Version mismatch: the plugin file declares "%s" but you entered "%s".',
                        $archiveVersion,
                        $expectedVersion
                    );
                }
            }

            // Guard against absurdly large file counts (zip bomb heuristic).
            if ($zip->numFiles > 5000) {
                $errors[] = 'Archive contains too many files.';
            }
        } finally {
            $zip->close();
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'headers' => $headers,
            'size' => $size,
            'sha256' => hash_file('sha256', $tmpPath) ?: '',
        ];
    }

    /**
     * Parse the standard plugin file headers from the first 8 KB.
     * Mirrors WordPress get_plugin_data() without requiring WordPress.
     *
     * @return array<string,string>
     */
    public static function parsePluginHeaders(string $fileContents): array
    {
        $header = substr($fileContents, 0, 8192);
        $map = [
            'PluginName' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'TextDomain' => 'Text Domain',
            'DomainPath' => 'Domain Path',
            'Network' => 'Network',
            'RequiresWP' => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
            'License' => 'License',
            'UpdateURI' => 'Update URI',
        ];
        $headers = [];
        foreach ($map as $key => $label) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($label, '/') . ':(.*)$/mi', $header, $m)) {
                $headers[$key] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1]));
            }
        }
        return $headers;
    }

    private static function fail(array $errors): array
    {
        return [
            'ok' => false,
            'errors' => $errors,
            'headers' => [],
            'size' => 0,
            'sha256' => '',
        ];
    }
}
