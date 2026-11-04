<?php

declare(strict_types=1);

use Wordpresso\Core\Db;

return static function (PDO $pdo): void {
    $columns = array_column(Db::fetchAll('SHOW COLUMNS FROM plans'), 'Field');
    if (!in_array('website_limit', $columns, true)) {
        Db::run('ALTER TABLE plans ADD COLUMN website_limit INT NOT NULL DEFAULT 0 AFTER term_months');
    }
};
