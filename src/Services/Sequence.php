<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class Sequence
{
    public static function next(string $key): int
    {
        if (Connection::driver() === 'mysql') {
            Connection::run(
                'INSERT INTO counters (name, current_value) VALUES (?, LAST_INSERT_ID(1))
                 ON DUPLICATE KEY UPDATE current_value = LAST_INSERT_ID(current_value + 1)',
                [$key]
            );
            return (int) Connection::pdo()->lastInsertId();
        }
        $row = Connection::one(
            'INSERT INTO counters (name, current_value) VALUES (?, 1)
             ON CONFLICT(name) DO UPDATE SET current_value = current_value + 1
             RETURNING current_value',
            [$key]
        );
        return (int) ($row['current_value'] ?? 0);
    }
}
