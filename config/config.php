<?php
/**
 * Wordpresso Auto Updater — central configuration.
 *
 * Every value can be overridden with an environment variable so the same
 * file works in development and production. See docs/DEPLOYMENT.md.
 */

$storage = dirname(__DIR__) . '/storage';

// getenv() returns the string '0' for a disabled flag, which is falsy in
// PHP — handle the explicit false case instead of relying on ?:.
$sessionSecureEnv = getenv('WORDPRESSO_SESSION_SECURE');
$sessionSecure = $sessionSecureEnv === false ? true : filter_var($sessionSecureEnv, FILTER_VALIDATE_BOOL);

return [
    // Public base URL of this cPanel deployment.
    'site_url' => getenv('WORDPRESSO_SITE_URL') ?: 'https://dashboard.fornax.host',
    'site_name' => getenv('WORDPRESSO_SITE_NAME') ?: 'Wordpresso',

    // This cPanel deployment uses MariaDB/MySQL only. There is no SQLite fallback.
    'db_driver' => 'mysql',
    'db_host' => getenv('WORDPRESSO_DB_HOST') ?: 'localhost',
    'db_name' => getenv('WORDPRESSO_DB_NAME') ?: '',
    'db_user' => getenv('WORDPRESSO_DB_USER') ?: '',
    'db_pass' => getenv('WORDPRESSO_DB_PASS') ?: '',
    'db_charset' => 'utf8mb4',
    // One-time GUI installer passphrase; set a private 32+ character value in
    // config.local.php before opening /install/.
    'installer_key' => getenv('WORDPRESSO_INSTALLER_KEY') ?: '',

    // Secret key used to encrypt website API secrets at rest (AES-256-GCM).
    // On first install, a random key is stored in storage/app_key (chmod 600).
    // Keep that file backed up and unchanged; rotating it invalidates website
    // credentials (customers must re-paste their secret).
    'app_key' => getenv('WORDPRESSO_APP_KEY') ?: '',
    'app_key_file' => getenv('WORDPRESSO_APP_KEY_FILE') ?: $storage . '/app_key',

    // Protected package storage (never inside the docroot).
    'packages_dir' => getenv('WORDPRESSO_PACKAGES_DIR') ?: $storage . '/packages',

    // Directory for runtime log files.
    'logs_dir' => getenv('WORDPRESSO_LOGS_DIR') ?: $storage . '/logs',

    // Upload / package limits.
    'max_package_bytes' => (int) (getenv('WORDPRESSO_MAX_PACKAGE_BYTES') ?: 64 * 1024 * 1024),

    // API security parameters.
    'api' => [
        'timestamp_window' => 300,          // seconds of allowed clock skew
        'nonce_ttl' => 600,                 // seconds a nonce stays valid (replay protection)
        'download_token_ttl' => 600,        // seconds a download token stays valid
        'rate_limit_window' => 300,         // rate limiting window in seconds
        'rate_limit_max' => 120,            // max API requests per website per window
        'rate_limit_max_anon' => 30,        // max API requests per IP per window (unauthenticated)
    ],

    // Update checks older than this are considered stale in the dashboard.
    'stale_check_minutes' => 36,

    // Operational database log retention in days (0 to disable automatic pruning).
    'log_retention_days' => (int) (getenv('WORDPRESSO_LOG_RETENTION_DAYS') ?: 30),

    // Session cookie configuration.
    'session' => [
        'name' => 'wordpresso_sess',
        'lifetime' => 86400 * 7,
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
