<?php

declare(strict_types=1);

$database = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dx-schema-test.sqlite';
if (is_file($database)) {
    unlink($database);
}
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . $database);
putenv('APP_KEY=test-key-with-32-characters-minimum');
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = $database;
$_ENV['APP_KEY'] = 'test-key-with-32-characters-minimum';

require dirname(__DIR__) . '/bootstrap/app.php';

App\Database\Connection::reset();
App\Database\Schema::migrate();
App\Database\Catalog::seed();

echo 'vehicles ' . App\Database\Connection::value('SELECT COUNT(*) FROM vehicles') . PHP_EOL;
echo 'prices ' . App\Database\Connection::value('SELECT COUNT(*) FROM vehicle_prices') . PHP_EOL;
echo 'permissions ' . App\Database\Connection::value('SELECT COUNT(*) FROM permissions') . PHP_EOL;
echo 'templates ' . App\Database\Connection::value('SELECT COUNT(*) FROM document_templates') . PHP_EOL;
