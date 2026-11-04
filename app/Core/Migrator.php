<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * File-based forward-only database migrator.
 *
 * Each file in migrations/ returns a closure(PDO): void and is named
 * NNN_description.php. Applied migrations are tracked in schema_migrations.
 */
final class Migrator
{
    public function __construct(
        private readonly string $migrationsDir,
    ) {
    }

    /** @return string[] descriptions of applied migrations */
    public function migrate(): array
    {
        $pdo = Db::pdo();
        if (Config::get('db_driver', 'sqlite') === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )');
        }

        $applied = Db::fetchAll('SELECT name FROM schema_migrations');
        $applied = array_column($applied, 'name');

        $files = glob($this->migrationsDir . '/*.php') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            if (!$migration instanceof \Closure) {
                throw new \RuntimeException("Migration {$name} must return a closure.");
            }
            $migration($pdo);
            Db::run('INSERT INTO schema_migrations (name) VALUES (?)', [$name]);
            $ran[] = $name;
        }
        return $ran;
    }

    /**
     * Throttled, lock-protected boot-time runner used by the front controller.
     *
     * Keeps the per-request cost to a single filemtime() check, and the
     * exclusive lock guarantees two concurrent requests never apply the same
     * migration twice. When the throttle expires the caller runs migrate();
     * on failure the state file is left untouched so the next request retries.
     *
     * @return string[] applied migration names
     */
    public static function autoMigrate(string $migrationsDir, string $stateFile, int $ttlSeconds): array
    {
        $dir = dirname($stateFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return [];
        }

        $lock = @fopen($stateFile . '.lock', 'c');
        if ($lock === false) {
            return [];
        }
        try {
            // Wait for an in-flight migration, then re-read the state file:
            // the request ahead of us will have refreshed it.
            if (!flock($lock, LOCK_EX)) {
                return [];
            }
            $mtime = @filemtime($stateFile);
            if ($mtime !== false && (time() - $mtime) < max(0, $ttlSeconds)) {
                return [];
            }
            $applied = (new self($migrationsDir))->migrate();
            @touch($stateFile);
            return $applied;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
