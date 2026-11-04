<?php

declare(strict_types=1);

/**
 * CLI: apply database migrations.
 * Usage: php bin/migrate.php
 */

define('WORDPRESSO_ROOT', dirname(__DIR__));
require WORDPRESSO_ROOT . '/app/bootstrap.php';

use Wordpresso\Core\Config;
use Wordpresso\Core\Migrator;

Config::load(WORDPRESSO_ROOT . '/config/config.php');

$migrator = new Migrator(WORDPRESSO_ROOT . '/migrations');
$applied = $migrator->migrate();

if ($applied === []) {
    echo "Database is up to date.\n";
} else {
    foreach ($applied as $name) {
        echo "Applied: {$name}\n";
    }
}
