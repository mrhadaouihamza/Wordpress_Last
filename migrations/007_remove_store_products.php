<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

/**
 * Drop the polymorphic store_products catalogue and the website-limit
 * bookkeeping once 006_catalog_tables has moved everything across.
 *
 * Website limits are gone entirely: connecting a website now only requires
 * the account to own at least one plan, plugin or theme.
 */
return static function (PDO $pdo): void
{
    // Children first — they hold foreign keys into store_products / subscriptions.
    Db::run('DROP TABLE IF EXISTS store_plan_products');
    Db::run('DROP TABLE IF EXISTS subscription_store_products');
    Db::run('DROP TABLE IF EXISTS store_plan_website_limits');
    Db::run('DROP TABLE IF EXISTS subscription_website_limits');
    Db::run('DROP TABLE IF EXISTS store_products');
};
