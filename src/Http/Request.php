<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public function int(string $key): int
    {
        return (int) $this->str($key, '0');
    }

    public function bool(string $key): bool
    {
        return in_array((string) $this->input($key, ''), ['1', 'on', 'true', 'sim'], true);
    }

    public function all(): array
    {
        return $_POST;
    }

    public function list(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? array_values($value) : [];
    }

    public function header(string $name): string
    {
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return trim((string) ($_SERVER[$server] ?? ''));
    }

    public function rawBody(): string
    {
        $body = file_get_contents('php://input');
        return $body === false ? '' : $body;
    }

    public function json(): array
    {
        $data = json_decode($this->rawBody(), true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        return request_ip();
    }
}
