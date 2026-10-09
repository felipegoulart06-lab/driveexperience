<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\Settings;
use RuntimeException;

final class Installer
{
    public static function run(array $options): array
    {
        self::guardPassword((string) ($options['admin_password'] ?? ''));
        Schema::migrate();
        Catalog::seed();
        Settings::flush();
        $created = false;
        $password = (string) $options['admin_password'];
        if (!Connection::value('SELECT id FROM users LIMIT 1')) {
            $roleId = (int) Connection::value('SELECT id FROM roles WHERE slug = ?', ['administrador']);
            $stamp = now();
            Connection::insert(
                'INSERT INTO users (role_id, name, email, password_hash, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 0, ?, ?)',
                [
                    $roleId,
                    (string) ($options['admin_name'] ?: 'Administrador'),
                    mb_strtolower(trim((string) $options['admin_email'])),
                    password_hash($password, PASSWORD_DEFAULT),
                    $stamp,
                    $stamp,
                ]
            );
            $created = true;
            audit('install', 'user', (int) Connection::value('SELECT id FROM users ORDER BY id DESC LIMIT 1'));
        }
        if (!empty($options['demo']) && env('APP_ENV') !== 'production' && Settings::get('demo_loaded', '0') !== '1') {
            if (class_exists(\App\Database\DemoSeeder::class)) {
                (new DemoSeeder())->run();
                Settings::set('demo_loaded', '1');
            }
        }
        return [
            'created' => $created,
            'email' => mb_strtolower(trim((string) $options['admin_email'])),
            'password' => $created ? $password : null,
        ];
    }

    public static function guardPassword(string $password): void
    {
        $blocked = ['admin', 'administrator', 'password', '123456', '12345678', 'drivexperience', 'drive123456'];
        if (!password_is_strong($password) || in_array(mb_strtolower($password), $blocked, true)) {
            throw new RuntimeException('A senha do administrador precisa ter ao menos 12 caracteres, com letras e números, e não pode ser uma senha padrão.');
        }
    }
}
