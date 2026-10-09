<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (self::$cache === null || !array_key_exists($key, self::$cache)) {
            return $default;
        }
        return self::$cache[$key];
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        $existing = Connection::one('SELECT setting_key FROM settings WHERE setting_key = ?', [$key]);
        if ($existing) {
            Connection::run(
                'UPDATE settings SET setting_value = ?, updated_at = ?, updated_by = ? WHERE setting_key = ?',
                [$value, now(), $userId, $key]
            );
        } else {
            Connection::run(
                'INSERT INTO settings (setting_key, setting_value, updated_at, updated_by) VALUES (?, ?, ?, ?)',
                [$key, $value, now(), $userId]
            );
        }
        self::flush();
    }

    public static function setMany(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            self::set((string) $key, (string) $value, $userId);
        }
    }

    public static function all(): array
    {
        self::load();
        return self::$cache ?? [];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        foreach (Connection::all('SELECT setting_key, setting_value FROM settings') as $row) {
            self::$cache[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }
}
