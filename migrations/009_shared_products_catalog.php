<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

return static function (PDO $pdo): void {
    $hasProducts = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'products'"
    );
    if ($hasProducts > 0) {
        $hasSourceId = (int) Db::value(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'source_id'"
        );
        if ($hasSourceId === 0) {
            Db::run('RENAME TABLE products TO legacy_store_products');
        }
    }
    Db::run(
        'CREATE TABLE IF NOT EXISTS products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(20) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            package_path VARCHAR(500) NULL,
            image_path VARCHAR(100) NULL,
            active TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_products_source (category, source_id),
            UNIQUE KEY uq_products_slug (category, slug),
            KEY idx_products_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    Db::run(
        "INSERT IGNORE INTO products
            (source_id, category, slug, name, description, price, package_path, image_path, active, created_at)
         SELECT id, 'Plugin', slug, name, description, price, package_path, image_path, active, created_at
         FROM plugins"
    );
    Db::run(
        "INSERT IGNORE INTO products
            (source_id, category, slug, name, description, price, package_path, image_path, active, created_at)
         SELECT id, 'Theme', slug, name, description, price, package_path, image_path, active, created_at
         FROM themes"
    );
};
