<?php

declare(strict_types=1);

define('CLIENT_BASE', '/app');

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/app');
$parts = parse_url($uri);
$path = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : '/app';
$query = is_array($parts) && isset($parts['query']) ? '?' . $parts['query'] : '';
if ($path === '/app') {
    $path = '/';
} elseif (str_starts_with($path, '/app/')) {
    $path = substr($path, strlen('/app')) ?: '/';
}
$_SERVER['REQUEST_URI'] = $path . $query;
$_SERVER['SCRIPT_NAME'] = '/app/index.php';

require dirname(__DIR__) . '/cliente/public/index.php';
