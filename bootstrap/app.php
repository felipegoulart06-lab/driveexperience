<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

$autoload = BASE_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    require BASE_PATH . '/src/Support/helpers.php';
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = BASE_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

if (!function_exists('env_load')) {
    require BASE_PATH . '/src/Support/helpers.php';
}

env_load(BASE_PATH . DIRECTORY_SEPARATOR . '.env');
