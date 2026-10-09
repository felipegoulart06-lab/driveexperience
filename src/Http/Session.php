<?php

declare(strict_types=1);

namespace App\Http;

use App\Database\Connection;

final class Session
{
    private static bool $started = false;
    private static array $data = [];
    private static ?string $id = null;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        $cookie = (string) ($_COOKIE['dx_session'] ?? '');
        if ($cookie !== '' && preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            $row = Connection::one('SELECT id, payload, expires_at FROM sessions WHERE id = ?', [$cookie]);
            if ($row && (string) $row['expires_at'] >= now()) {
                self::$id = $cookie;
                $payload = json_decode((string) $row['payload'], true);
                self::$data = is_array($payload) ? $payload : [];
                self::touch();
                self::$started = true;
                return;
            }
        }
        self::regenerate();
        self::$started = true;
    }

    public static function regenerate(): void
    {
        $previous = self::$id;
        self::$id = bin2hex(random_bytes(32));
        self::$data = self::$data;
        self::persist();
        self::cookie(self::$id);
        if ($previous !== null && $previous !== self::$id) {
            Connection::run('DELETE FROM sessions WHERE id = ?', [$previous]);
        }
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return self::$data[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        self::$data[$key] = $value;
        self::persist();
    }

    public static function forget(string $key): void
    {
        self::start();
        unset(self::$data[$key]);
        self::persist();
    }

    public static function pull(string $key): mixed
    {
        if (!self::$started && !self::tableReady()) {
            return null;
        }
        self::start();
        $value = self::$data[$key] ?? null;
        if (array_key_exists($key, self::$data)) {
            unset(self::$data[$key]);
            self::persist();
        }
        return $value;
    }

    public static function flash(string $type, string $message): void
    {
        self::put('_flash', ['type' => $type, 'message' => $message]);
    }

    public static function putErrors(array $errors, array $old): void
    {
        self::put('_errors', $errors);
        self::put('_old', $old);
    }

    public static function destroy(): void
    {
        if (self::$id !== null) {
            Connection::run('DELETE FROM sessions WHERE id = ?', [self::$id]);
        }
        self::$data = [];
        self::$id = null;
        self::$started = false;
        self::cookie('', -3600);
    }

    public static function id(): ?string
    {
        return self::$id;
    }

    private static function persist(): void
    {
        if (self::$id === null) {
            return;
        }
        $hours = 8;
        try {
            $hours = max(1, (int) \App\Services\Settings::get('session_hours', '8'));
        } catch (\Throwable) {
        }
        $expires = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $payload = json_encode(self::$data, JSON_UNESCAPED_UNICODE);
        $existing = Connection::one('SELECT id FROM sessions WHERE id = ?', [self::$id]);
        if ($existing) {
            Connection::run('UPDATE sessions SET payload = ?, expires_at = ?, user_id = ? WHERE id = ?', [
                $payload,
                $expires,
                self::$data['user_id'] ?? null,
                self::$id,
            ]);
            return;
        }
        Connection::run('INSERT INTO sessions (id, user_id, payload, expires_at, created_at) VALUES (?, ?, ?, ?, ?)', [
            self::$id,
            self::$data['user_id'] ?? null,
            $payload,
            $expires,
            now(),
        ]);
    }

    private static function touch(): void
    {
        self::persist();
    }

    private static function cookie(string $value, int $ttl = 0): void
    {
        if (PHP_SAPI === 'cli') {
            if ($value === '') {
                unset($_COOKIE['dx_session']);
            } else {
                $_COOKIE['dx_session'] = $value;
            }
            return;
        }
        $hours = 8;
        try {
            $hours = max(1, (int) \App\Services\Settings::get('session_hours', '8'));
        } catch (\Throwable) {
        }
        setcookie('dx_session', $value, [
            'expires' => $ttl < 0 ? time() - 3600 : time() + ($hours * 3600),
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function tableReady(): bool
    {
        try {
            Connection::value('SELECT 1 FROM sessions LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
