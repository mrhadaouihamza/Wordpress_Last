<?php

declare(strict_types=1);

namespace Wordpresso\Services;

use Wordpresso\Core\Config;

/** Protected storage for public product artwork. */
final class ProductImageStorage
{
    public static function baseDir(): string
    {
        $dir = (string) Config::get('product_images_dir', dirname((string) Config::get('packages_dir')) . '/product-images');
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create product image storage.');
        }
        return $dir;
    }

    /** Validate and move an uploaded image; returns its storage filename. */
    public static function store(array $upload): string
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))
            || (int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('Choose an image smaller than 5 MB.');
        }
        $tmp = (string)$upload['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        if (!$info || !isset($types[$mime]) || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 20000000) {
            throw new \RuntimeException('Use a valid JPG, PNG, or WebP image up to 6000 × 6000 pixels.');
        }
        $name = bin2hex(random_bytes(20)) . '.' . $types[$mime];
        $target = self::baseDir() . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Could not save the uploaded image. Check that product image storage is writable.');
        }
        chmod($target, 0640);
        return $name;
    }

    public static function absolutePath(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/', $name)) return null;
        $path = self::baseDir() . '/' . $name;
        $real = realpath($path);
        $base = realpath(self::baseDir());
        return $real && $base && str_starts_with($real, $base . DIRECTORY_SEPARATOR) && is_file($real) ? $real : null;
    }

    public static function delete(?string $name): void
    {
        if ($name && ($path = self::absolutePath($name)) !== null) @unlink($path);
    }
}
