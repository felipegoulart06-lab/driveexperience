<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Support\AuthException;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $permissions = [], bool $public = false): void
    {
        $this->add('GET', $path, $handler, $permissions, $public, false);
    }

    public function post(string $path, array $handler, array $permissions = [], bool $public = false, bool $csrf = true): void
    {
        $this->add('POST', $path, $handler, $permissions, $public, $csrf);
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $params = $this->match($route['path'], $request->path());
            if ($params === null) {
                continue;
            }
            if (!$route['public']) {
                Auth::requireLogin();
                foreach ($route['permissions'] as $permission) {
                    Auth::require($permission);
                }
            }
            if ($route['csrf']) {
                Csrf::verify($request->str('_csrf'));
            }
            [$class, $action] = $route['handler'];
            (new $class())->{$action}($request, ...$params);
            return;
        }
        throw new \App\Support\NotFoundException('Página não encontrada.');
    }

    private function add(string $method, string $path, array $handler, array $permissions, bool $public, bool $csrf): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'permissions', 'public', 'csrf');
    }

    private function match(string $route, string $path): ?array
    {
        $pattern = preg_replace_callback('/\{(\w+)\}/', static function (array $matches): string {
            $name = $matches[1];
            $expression = $name === 'id' ? '\d+' : '[A-Za-z0-9_-]+';
            return '(?P<' . $name . '>' . $expression . ')';
        }, $route);
        if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[] = $value;
            }
        }
        return $params;
    }
}
