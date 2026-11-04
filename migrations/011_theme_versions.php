<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

return static function (PDO $pdo): void {
    Db::run(
        "CREATE TABLE IF NOT EXISTS theme_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            theme_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(64) NOT NULL,
            package_path VARCHAR(500) NOT NULL,
            package_sha256 CHAR(64) NOT NULL,
            package_size BIGINT UNSIGNED NOT NULL,
            changelog TEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            min_wp_version VARCHAR(32) NOT NULL DEFAULT '',
            min_php_version VARCHAR(32) NOT NULL DEFAULT '',
            tested_wp_version VARCHAR(32) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            published_at DATETIME NULL,
            UNIQUE KEY uq_theme_version (theme_id, version),
            KEY idx_theme_versions_theme (theme_id, status, is_current),
            CONSTRAINT fk_theme_versions_theme FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE
        )         ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
