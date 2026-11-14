<?php

declare(strict_types=1);

define('CLIENT_PATH', __DIR__);
if (!defined('CLIENT_BASE')) {
    define('CLIENT_BASE', '');
}

require dirname(__DIR__) . '/bootstrap/app.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cliente\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = CLIENT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (getenv('VERCEL')) {
        session_save_path('/tmp');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_name('dx_cliente');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => CLIENT_BASE === '' ? '/' : CLIENT_BASE,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
    session_start();
}

if (CLIENT_BASE !== '') {
    $clientBase = CLIENT_BASE;
    header_register_callback(static function () use ($clientBase): void {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location: /') !== 0) {
                continue;
            }
            $target = trim(substr($header, strlen('Location:')));
            if (!str_starts_with($target, '/') || str_starts_with($target, $clientBase . '/') || $target === $clientBase) {
                continue;
            }
            header_remove('Location');
            header('Location: ' . $clientBase . $target);
            break;
        }
    });
    ob_start(static function (string $html) use ($clientBase): string {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && !str_contains(strtolower($header), 'html')) {
                return $html;
            }
        }
        $html = preg_replace('#\b(href|src|action)=([\'"])/(?!/)#', '$1=$2' . $clientBase . '/', $html) ?? $html;
        return preg_replace('#url\(([\'"])/assets/#', 'url($1' . $clientBase . '/assets/', $html) ?? $html;
    });
}

Cliente\Database\Schema::ensure();
