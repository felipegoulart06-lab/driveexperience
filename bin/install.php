<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envFile)) {
    copy($root . DIRECTORY_SEPARATOR . '.env.example', $envFile);
}

$env = (string) file_get_contents($envFile);
$set = static function (string $key, string $value) use (&$env): void {
    $line = $key . '=' . $value;
    if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $env)) {
        $env = (string) preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $line, $env, 1);
        return;
    }
    $env .= PHP_EOL . $line . PHP_EOL;
};
$get = static function (string $key) use (&$env): string {
    if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $env, $matches)) {
        return trim($matches[1]);
    }
    return '';
};

if (strlen($get('APP_KEY')) < 32) {
    $set('APP_KEY', bin2hex(random_bytes(32)));
}
if ($get('DB_DRIVER') === '') {
    $set('DB_DRIVER', 'sqlite');
}
$email = $get('ADMIN_EMAIL');
if ($email === '') {
    $email = 'admin@localhost';
    $set('ADMIN_EMAIL', $email);
}
$password = $get('ADMIN_PASSWORD');
$generated = false;
if ($password === '') {
    $password = 'Dx' . bin2hex(random_bytes(6)) . '9a';
    $generated = true;
    $set('ADMIN_PASSWORD', $password);
}
$demo = in_array('--demo', $argv, true) || $get('SEED_DEMO') === '1';
if ($demo) {
    $set('SEED_DEMO', '1');
    $set('APP_ENV', 'local');
}
file_put_contents($envFile, $env);

require $root . '/bootstrap/app.php';

use App\Database\Connection;
use App\Database\Installer;
use App\Database\Schema;

if (Schema::installed() && Connection::value('SELECT id FROM users LIMIT 1')) {
    fwrite(STDOUT, "O sistema já está instalado. A senha existente não foi alterada.\n");
    exit(0);
}

$result = Installer::run([
    'admin_name' => env('ADMIN_NAME', 'Administrador') ?: 'Administrador',
    'admin_email' => $email,
    'admin_password' => $password,
    'demo' => $demo,
]);

$notice = "E-mail: {$result['email']}\nSenha: {$password}\n";
$folder = $root . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($folder)) {
    mkdir($folder, 0775, true);
}
file_put_contents($folder . DIRECTORY_SEPARATOR . 'credenciais-locais.txt', $notice);
fwrite(STDOUT, "Instalação concluída.\n" . $notice);
fwrite(STDOUT, $generated
    ? "A senha foi gerada agora e gravada apenas em storage/credenciais-locais.txt, que não entra no repositório.\n"
    : "A senha usada foi a definida em ADMIN_PASSWORD.\n");
if ($demo) {
    fwrite(STDOUT, "Dados de demonstração carregados e marcados como fictícios.\n");
}
