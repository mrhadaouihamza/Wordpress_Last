<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

return static function (PDO $pdo): void {
    if (\Wordpresso\Core\Config::get('db_driver', 'sqlite') !== 'mysql') {
        return;
    }
    $exists = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'store_products'"
    );
    $targetExists = (int) Db::value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'products'"
    );
    if ($exists > 0 && $targetExists === 0) {
        Db::run('RENAME TABLE store_products TO products');
    }
};
