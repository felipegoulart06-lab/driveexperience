<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    private static int $depth = 0;

    public static function driver(): string
    {
        self::pdo();
        return self::$driver;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $driver = env('DB_DRIVER', 'sqlite') ?? 'sqlite';
        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException('DB_DRIVER deve ser sqlite, mysql ou pgsql.');
        }
        if ($driver === 'sqlite' && getenv('VERCEL')) {
            throw new RuntimeException('Na Vercel o banco precisa ser MySQL ou PostgreSQL externo. O sistema de arquivos da função é somente leitura e não guarda dados.');
        }
        try {
            if ($driver === 'sqlite') {
                $path = env('DB_DATABASE') ?: base_path('storage/database.sqlite');
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Não foi possível preparar a pasta do banco local.');
                }
                $pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA busy_timeout = 5000');
                try {
                    $pdo->exec('PRAGMA journal_mode = WAL');
                } catch (PDOException) {
                }
            } elseif ($driver === 'pgsql') {
                $host = env('DB_HOST', '127.0.0.1');
                $port = env('DB_PORT', '5432');
                $database = env('DB_DATABASE', '');
                if ($database === null || $database === '') {
                    throw new RuntimeException('DB_DATABASE não foi informado.');
                }
                $ssl = env('DB_SSLMODE', 'require') ?: 'require';
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                    $host,
                    $port,
                    $database,
                    $ssl
                );
                $pdo = new PDO($dsn, (string) env('DB_USERNAME', ''), (string) env('DB_PASSWORD', ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
            } else {
                $host = env('DB_HOST', '127.0.0.1');
                $port = env('DB_PORT', '3306');
                $database = env('DB_DATABASE', '');
                if ($database === null || $database === '') {
                    throw new RuntimeException('DB_DATABASE não foi informado.');
                }
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
                $pdo = new PDO($dsn, (string) env('DB_USERNAME', ''), (string) env('DB_PASSWORD', ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $pdo->exec("SET time_zone = '-03:00'");
            }
        } catch (PDOException $exception) {
            error_log('Falha de conexão com o banco. SQLSTATE ' . $exception->getCode());
            throw new RuntimeException('Não foi possível conectar ao banco de dados. Verifique as variáveis DB_*.');
        }
        self::$pdo = $pdo;
        self::$driver = $driver;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$depth = 0;
    }

    public static function all(string $sql, array $params = []): array
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $row = self::one($sql, $params);
        if ($row === null) {
            return null;
        }
        return array_values($row)[0];
    }

    public static function run(string $sql, array $params = []): int
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $nested = self::$depth > 0;
        if (!$nested) {
            self::begin();
        }
        self::$depth++;
        try {
            $result = $callback();
            self::$depth--;
            if (!$nested) {
                self::commit();
            }
            return $result;
        } catch (\Throwable $exception) {
            self::$depth--;
            if (!$nested) {
                self::rollback();
            }
            throw $exception;
        }
    }

    private static function begin(): void
    {
        if (self::driver() === 'sqlite') {
            self::pdo()->exec('BEGIN IMMEDIATE');
            return;
        }
        self::pdo()->beginTransaction();
    }

    private static function commit(): void
    {
        if (self::$driver === 'sqlite') {
            self::pdo()->exec('COMMIT');
            return;
        }
        self::pdo()->commit();
    }

    private static function rollback(): void
    {
        try {
            if (self::$driver === 'sqlite') {
                self::pdo()->exec('ROLLBACK');
                return;
            }
            if (self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }
        } catch (PDOException) {
        }
    }
}
