<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

/**
 * Replace the polymorphic store_products catalogue with dedicated tables:
 *
 *   - plugins  gains the storefront columns (price, package, image, active)
 *   - themes   becomes its own catalogue table
 *   - plans    becomes its own catalogue table (subscription plans)
 *   - subscription_products replaces subscription_store_products
 *
 * Numeric ids are preserved for themes and plans so historic order items and
 * download links keep working; plugin order items are remapped from the
 * store_products id onto the real plugins.id.
 */
return static function (PDO $pdo): void
{
    // A fresh install imports install/database.sql, which already carries the
    // final schema and records this migration — nothing to move across.
    $hasLegacyCatalog = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'store_products'"
    ) > 0;
    if (!$hasLegacyCatalog) {
        return;
    }

    // --- 1. Storefront columns on the plugins table ------------------------
    // `active` defaults to 0: a plugin is only sold once an administrator
    // lists it, which keeps plugins created through Admin → Plugins out of
    // the shop until they are priced and published.
    $pluginColumns = array_column(Db::fetchAll('SHOW COLUMNS FROM plugins'), 'Field');
    $add = [];
    if (!in_array('price', $pluginColumns, true)) {
        $add[] = 'ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0';
    }
    if (!in_array('package_path', $pluginColumns, true)) {
        $add[] = 'ADD COLUMN package_path VARCHAR(500) NULL';
    }
    if (!in_array('image_path', $pluginColumns, true)) {
        $add[] = 'ADD COLUMN image_path VARCHAR(100) NULL';
    }
    if (!in_array('active', $pluginColumns, true)) {
        $add[] = 'ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 0';
    }
    if ($add !== []) {
        $pdo->exec('ALTER TABLE plugins ' . implode(', ', $add));
    }

    // --- 2. Dedicated themes and plans tables ------------------------------
    Db::run("CREATE TABLE IF NOT EXISTS themes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    Db::run("CREATE TABLE IF NOT EXISTS plans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        term_months INT NOT NULL DEFAULT 12,
        image_path VARCHAR(100) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_plans_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // --- 3. subscriptions.plan_id on databases older than the base schema --
    $subscriptionColumns = array_column(Db::fetchAll('SHOW COLUMNS FROM subscriptions'), 'Field');
    $addSubscription = [];
    if (!in_array('plan_id', $subscriptionColumns, true)) {
        $addSubscription[] = 'ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER customer_id';
    }
    $hasPlanIndex = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'subscriptions' AND index_name = 'idx_subs_plan'"
    ) > 0;
    if (!$hasPlanIndex) {
        $addSubscription[] = 'ADD KEY idx_subs_plan (plan_id)';
    }
    if ($addSubscription !== []) {
        $pdo->exec('ALTER TABLE subscriptions ' . implode(', ', $addSubscription));
    }

    // --- 4. Move themes and plans across (their ids are preserved) ---------
    Db::run("INSERT OR IGNORE INTO themes (id, slug, name, description, price, package_path, image_path, active, created_at)
        SELECT id, slug, name, description, price, package_path, image_path, active, created_at
        FROM store_products WHERE category = 'Theme'");

    Db::run("INSERT OR IGNORE INTO plans (id, slug, name, description, price, term_months, image_path, active, created_at)
        SELECT id, slug, name, description, price, term_months, image_path, active, created_at
        FROM store_products WHERE category = 'Plan'");

    // --- 5. Storefront columns on the matching plugins ---------------------
    // Every store_products Plugin row should already have a plugins row (the
    // product form creates both in one transaction). Rows whose plugin was
    // removed are re-created as hidden listings so historic orders keep
    // resolving; anything that cannot be given a unique basename is skipped.
    foreach (Db::fetchAll(
        "SELECT id, plugin_id, slug, name, description, price, package_path, image_path, active
         FROM store_products WHERE category = 'Plugin'"
    ) as $listing) {
        $pluginId = null;
        if (!empty($listing['plugin_id'])) {
            $pluginId = Db::value('SELECT id FROM plugins WHERE id = ?', [(int) $listing['plugin_id']]);
        }
        if ($pluginId === null) {
            $pluginId = Db::value('SELECT id FROM plugins WHERE slug = ?', [(string) $listing['slug']]);
        }
        $basename = $listing['slug'] . '/' . $listing['slug'] . '.php';
        if ($pluginId === null) {
            $pluginId = Db::value('SELECT id FROM plugins WHERE basename = ?', [$basename]);
        }
        if ($pluginId === null) {
            $pluginId = Db::insert(
                'INSERT INTO plugins (slug, name, basename, description, changelog, price, package_path, image_path, active, auto_update_allowed)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
                [
                    $listing['slug'], $listing['name'], $basename,
                    $listing['description'], '',
                    $listing['price'], $listing['package_path'], $listing['image_path'],
                    // A resurrected listing starts hidden: the administrator
                    // decides whether it should be sold again.
                    0,
                ]
            );
        }
        Db::run(
            'UPDATE plugins SET price = ?, package_path = ?, image_path = ?, active = ? WHERE id = ?',
            [$listing['price'], $listing['package_path'], $listing['image_path'], $listing['active'], (int) $pluginId]
        );
    }

    // --- 6. subscription_products replaces subscription_store_products ------
    Db::run('CREATE TABLE IF NOT EXISTS subscription_products (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        subscription_id BIGINT UNSIGNED NOT NULL,
        category VARCHAR(20) NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        UNIQUE KEY uq_subscription_product (subscription_id, category, product_id),
        KEY idx_subscription_products_product (category, product_id),
        CONSTRAINT fk_subscription_products_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $hasLegacyGrants = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'subscription_store_products'"
    ) > 0;
    $legacyGrants = $hasLegacyGrants ? Db::fetchAll(
        'SELECT ssp.subscription_id, p.category, p.id AS product_id, p.plugin_id, p.slug
         FROM subscription_store_products ssp
         JOIN store_products p ON p.id = ssp.store_product_id'
    ) : [];

    foreach ($legacyGrants as $grant) {
        $productId = (int) $grant['product_id'];
        if ($grant['category'] === 'Plugin') {
            $pluginId = !empty($grant['plugin_id'])
                ? Db::value('SELECT id FROM plugins WHERE id = ?', [(int) $grant['plugin_id']])
                : null;
            if ($pluginId === null) {
                $pluginId = Db::value('SELECT id FROM plugins WHERE slug = ?', [(string) $grant['slug']]);
            }
            if ($pluginId === null) {
                continue;
            }
            $productId = (int) $pluginId;
        }
        Db::run(
            'INSERT OR IGNORE INTO subscription_products (subscription_id, category, product_id) VALUES (?, ?, ?)',
            [(int) $grant['subscription_id'], (string) $grant['category'], $productId]
        );
    }

    // --- 7. Backfill subscriptions.plan_id from the plan name --------------
    foreach (Db::fetchAll('SELECT id, plan_name FROM subscriptions WHERE plan_id IS NULL') as $subscription) {
        $planId = null;
        $planName = (string) $subscription['plan_name'];
        if (preg_match('/\((WPS-[A-F0-9]{10})\)$/', $planName, $matches)) {
            // Order items keep their product_id for Plan rows, which already
            // points at plans.id after step 4.
            $planId = Db::value(
                "SELECT i.product_id FROM store_order_items i
                 JOIN store_orders o ON o.id = i.order_id
                 WHERE o.order_code = ? AND i.category = 'Plan' LIMIT 1",
                [$matches[1]]
            );
        } elseif (str_ends_with($planName, ' (admin assigned)')) {
            $planId = Db::value(
                'SELECT id FROM plans WHERE name = ? LIMIT 1',
                [substr($planName, 0, -strlen(' (admin assigned)'))]
            );
        }
        if ($planId !== null) {
            Db::run('UPDATE subscriptions SET plan_id = ? WHERE id = ?', [(int) $planId, (int) $subscription['id']]);
        }
    }

    // --- 8. Point historic plugin order items at plugins.id ----------------
    // Themes and plans keep their original ids, so only Plugin rows move.
    //
    // store_products ids and plugins.id are independent auto-increment
    // spaces and routinely collide, so the rewrite has to happen as ONE
    // statement: applying the mappings one row at a time would let an
    // already-remapped item be caught again by a later mapping.
    $remap = [];
    foreach (Db::fetchAll(
        "SELECT id, plugin_id, slug FROM store_products WHERE category = 'Plugin'"
    ) as $listing) {
        $pluginId = !empty($listing['plugin_id'])
            ? Db::value('SELECT id FROM plugins WHERE id = ?', [(int) $listing['plugin_id']])
            : null;
        if ($pluginId === null) {
            $pluginId = Db::value('SELECT id FROM plugins WHERE slug = ?', [(string) $listing['slug']]);
        }
        if ($pluginId === null || (int) $pluginId === (int) $listing['id']) {
            continue;
        }
        $remap[(int) $listing['id']] = (int) $pluginId;
    }
    if ($remap !== []) {
        $cases = implode(' ', array_map(
            static fn(int $from, int $to): string => "WHEN {$from} THEN {$to}",
            array_keys($remap),
            array_values($remap),
        ));
        $in = implode(',', array_map('intval', array_keys($remap)));
        Db::run(
            "UPDATE store_order_items
             SET product_id = CASE product_id {$cases} END
             WHERE category = 'Plugin' AND product_id IN ({$in})"
        );
    }
};
