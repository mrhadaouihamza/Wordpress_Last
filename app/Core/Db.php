<?php

declare(strict_types=1);

namespace Wordpresso\Core;

use PDO;

/**
 * PDO database singleton with prepared-statement helpers.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ];
            if (Config::get('db_driver', 'sqlite') === 'mysql') {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Config::get('db_host'), Config::get('db_name'));
                self::$pdo = new PDO($dsn, (string) Config::get('db_user'), (string) Config::get('db_pass'), $options);
            } else {
                $path = (string) Config::get('db_path');
                $dir = dirname($path);
                if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                    throw new \RuntimeException('Cannot create database directory: ' . $dir);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            }
        }
        return self::$pdo;
    }

    /** Prepare and execute a statement, return it. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        if (Config::get('db_driver', 'sqlite') === 'mysql') {
            $sql = preg_replace_callback("/datetime\\('now',\\s*'-(\\d+) (day|days|hour|hours|minute|minutes)'\\)/i", static function (array $m): string {
                $unit = rtrim(strtoupper($m[2]), 'S');
                return 'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . (int) $m[1] . ' ' . $unit . ')';
            }, $sql);
            $sql = preg_replace("/datetime\\('now'\\)/i", 'CURRENT_TIMESTAMP', $sql);
            $sql = preg_replace('/datetime\\(([^()]*)\\)/i', '$1', $sql);
            $sql = str_ireplace('INSERT OR IGNORE INTO', 'INSERT IGNORE INTO', $sql);
        }
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $val = self::run($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** For tests: point the connection at another database file. */
    public static function reset(?PDO $pdo = null): void
    {
        self::$pdo = $pdo;
    }
}
