<?php

declare(strict_types=1);

namespace Wordpresso\Models;

use Wordpresso\Core\Db;

final class StoreSettings
{
    private static ?array $values = null;

    public static function get(string $key, mixed $fallback = ''): mixed
    {
        if (self::$values === null) {
            self::$values = [];
            try {
                foreach (Db::fetchAll('SELECT setting_key, setting_value FROM store_settings') as $setting) {
                    self::$values[(string)$setting['setting_key']] = $setting['setting_value'];
                }
            } catch (\Throwable) {
                // Keep configured defaults available if the optional store table is unavailable.
            }
        }
        return self::$values[$key] ?? $fallback;
    }

    public static function save(array $values): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $key = (string)$key;
                $value = (string)$value;
                if (Db::value('SELECT setting_key FROM store_settings WHERE setting_key=?', [$key]) !== null) {
                    Db::run('UPDATE store_settings SET setting_value=? WHERE setting_key=?', [$value, $key]);
                } else {
                    Db::run('INSERT INTO store_settings (setting_key,setting_value) VALUES (?,?)', [$key, $value]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::$values = null;
    }
}
