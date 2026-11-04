<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * Crypto helpers: secret encryption at rest (AES-256-GCM) and hashing.
 *
 * Website API secrets must be recoverable for HMAC verification, so they are
 * encrypted (not hashed) with a key derived from the server APP_KEY.
 */
final class Crypto
{
    /** Derive a stable 32-byte encryption key from the configured APP_KEY. */
    public static function appKey(): string
    {
        $key = (string) Config::get('app_key');
        if ($key === '') {
            $keyFile = (string) Config::get('app_key_file');
            if (is_file($keyFile)) {
                $key = trim((string) file_get_contents($keyFile));
            }
            if ($key === '') {
                $key = bin2hex(random_bytes(32));
                $dir = dirname($keyFile);
                if (!is_dir($dir)) {
                    mkdir($dir, 0770, true);
                }
                file_put_contents($keyFile, $key, LOCK_EX);
                chmod($keyFile, 0600);
            }
        }
        return hash('sha256', $key, true);
    }

    /** Encrypt a secret; output is base64(iv|tag|ciphertext). */
    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::appKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::appKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed (APP_KEY rotated?).');
        }
        return $plain;
    }

    /** Random URL-safe token of $bytes entropy. */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Hash for storing single-use tokens. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
