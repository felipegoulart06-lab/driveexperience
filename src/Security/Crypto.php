<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class Crypto
{
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Falha ao proteger o segredo.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Segredo armazenado está inválido.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Não foi possível ler o segredo. Verifique se APP_KEY permanece o mesmo.');
        }
        return $plain;
    }

    private static function key(): string
    {
        $appKey = (string) env('APP_KEY', '');
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY ausente ou curta demais.');
        }
        return hash('sha256', $appKey, true);
    }
}
