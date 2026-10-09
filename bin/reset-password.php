<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Database\Connection;
use App\Database\Installer;

$email = '';
$password = '';
$token = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--email=')) {
        $email = substr($argument, 8);
    }
    if (str_starts_with($argument, '--password=')) {
        $password = substr($argument, 11);
    }
    if (str_starts_with($argument, '--token=')) {
        $token = substr($argument, 8);
    }
}

$expected = (string) env('EMERGENCY_RESET_TOKEN', '');
if ($expected === '' || !hash_equals($expected, $token)) {
    fwrite(STDERR, "Defina EMERGENCY_RESET_TOKEN e informe o mesmo valor em --token.\n");
    exit(1);
}
Installer::guardPassword($password);
$user = Connection::one('SELECT id FROM users WHERE email = ?', [mb_strtolower($email)]);
if (!$user) {
    fwrite(STDERR, "Usuário não encontrado.\n");
    exit(1);
}
Connection::run('UPDATE users SET password_hash = ?, active = 1, updated_at = ? WHERE id = ?', [
    password_hash($password, PASSWORD_DEFAULT),
    now(),
    (int) $user['id'],
]);
fwrite(STDOUT, "Senha redefinida para {$email}.\n");
