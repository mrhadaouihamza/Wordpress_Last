<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Config;

final class ThemePackageValidator
{
    /** @return array{ok: bool, errors: string[], size: int, sha256: string} */
    public static function validate(string $tmpPath, string $slug, string $expectedVersion): array
    {
        $errors = [];
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
            $stylesheet = $zip->getFromName($slug . '/style.css');
            if ($stylesheet === false) {
                $errors[] = sprintf('Theme stylesheet "%s/style.css" not found in the archive.', $slug);
            } elseif (!preg_match('/^[ \t]*Theme Name\s*:/mi', $stylesheet)) {
                $errors[] = 'The theme stylesheet has no "Theme Name" header.';
            } elseif (!preg_match('/^[ \t]*Version\s*:\s*(.+)$/mi', $stylesheet, $match)) {
                $errors[] = 'The theme stylesheet has no "Version" header.';
            } elseif (trim($match[1]) !== $expectedVersion) {
                $errors[] = sprintf('Version mismatch: the stylesheet declares "%s" but you entered "%s".', trim($match[1]), $expectedVersion);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (str_starts_with($entry, '/') || str_contains($entry, '..')) {
                    $errors[] = 'Archive contains an unsafe path: ' . $entry;
                    break;
                }
            }
            if ($zip->numFiles > 10000) {
                $errors[] = 'Archive contains too many files.';
            }
        } finally {
            $zip->close();
        }
        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'size' => $size,
            'sha256' => hash_file('sha256', $tmpPath) ?: '',
        ];
    }

    private static function fail(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors, 'size' => 0, 'sha256' => ''];
    }
}
