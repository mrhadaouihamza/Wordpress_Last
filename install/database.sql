-- Wordpresso Store schema for MySQL 8.0+ / MariaDB 10.5+.
-- In cPanel, create/select your database in MySQL Databases, then import this file
-- through phpMyAdmin. This file creates tables only; it does not create a DB/user.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL UNIQUE,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Plugin updater data remains in `plugins`, theme data in `themes`, and the
-- shared store catalogue is maintained in `products`.
CREATE TABLE IF NOT EXISTS plugins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(191) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  basename VARCHAR(255) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  changelog TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  package_path VARCHAR(500) NULL,
  image_path VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  auto_update_allowed TINYINT(1) NOT NULL DEFAULT 1,
  min_wp_version VARCHAR(32) NOT NULL DEFAULT '',
  min_php_version VARCHAR(32) NOT NULL DEFAULT '',
  tested_wp_version VARCHAR(32) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_plugins_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS themes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  package_path VARCHAR(500) NULL,
  image_path VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_themes_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  term_months INT NOT NULL DEFAULT 12,
  website_limit INT NOT NULL DEFAULT 0,
  image_path VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_plans_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS plugin_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  plugin_id BIGINT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_plugin_version (plugin_id, version),
  KEY idx_versions_plugin (plugin_id, status, is_current),
  CONSTRAINT fk_versions_plugin FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS theme_versions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  plan_name VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  updates_included TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_subs_customer (customer_id, status),
  KEY idx_subs_plan (plan_id),
  CONSTRAINT fk_subs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS subscription_plugins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subscription_id BIGINT UNSIGNED NOT NULL,
  plugin_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_subscription_plugin (subscription_id, plugin_id),
  KEY idx_subplugins_plugin (plugin_id),
  CONSTRAINT fk_subplugins_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_subplugins_plugin FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS websites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(2048) NOT NULL,
  api_key VARCHAR(80) NOT NULL UNIQUE,
  secret_encrypted TEXT NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  wp_version VARCHAR(32) NOT NULL DEFAULT '',
  php_version VARCHAR(32) NOT NULL DEFAULT '',
  last_check_at DATETIME NULL,
  last_check_ip VARCHAR(45) NULL,
  connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  KEY idx_websites_customer (customer_id),
  CONSTRAINT fk_websites_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS download_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  website_id BIGINT UNSIGNED NOT NULL,
  plugin_version_id BIGINT UNSIGNED NOT NULL,
  expires_at BIGINT NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tokens_website (website_id, expires_at),
  CONSTRAINT fk_tokens_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tokens_version FOREIGN KEY (plugin_version_id) REFERENCES plugin_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS api_nonces (
  nonce_hash CHAR(64) NOT NULL PRIMARY KEY,
  created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_hash CHAR(64) NOT NULL,
  window_start BIGINT NOT NULL,
  hits INT NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket_hash, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS update_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  website_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  plugin_id BIGINT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  outcome VARCHAR(24) NOT NULL DEFAULT 'success',
  message TEXT NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  context LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_created (created_at),
  KEY idx_logs_website (website_id),
  KEY idx_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations (name) VALUES ('001_init');

CREATE TABLE IF NOT EXISTS store_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(32) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'USDT',
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  tx_hash VARCHAR(80) NULL UNIQUE,
  payment_expires_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_store_orders_customer (customer_id, created_at),
  CONSTRAINT fk_store_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS store_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  category VARCHAR(20) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  KEY idx_store_order_items_order (order_id),
  CONSTRAINT fk_store_order_items_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS store_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS subscription_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subscription_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(20) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_subscription_product (subscription_id, category, product_id),
  KEY idx_subscription_products_product (category, product_id),
  CONSTRAINT fk_subscription_products_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations (name) VALUES ('001_init'), ('002_storefront_commerce'), ('003_store_plan_products'), ('004_website_limits'), ('005_product_images'), ('006_catalog_tables'), ('007_remove_store_products'), ('008_rename_store_products_to_products'), ('009_shared_products_catalog'), ('010_plan_website_limits');
