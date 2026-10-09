<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'woff2' => 'font/woff2',
        'webmanifest' => 'application/manifest+json',
        'svg' => 'image/svg+xml',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    if (isset($types[$extension])) {
        header('Content-Type: ' . $types[$extension]);
    }
    header('Cache-Control: public, max-age=3600');
    readfile($file);
    return true;
}
require __DIR__ . '/public/index.php';
