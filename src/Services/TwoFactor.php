<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Session;
use App\Security\Crypto;
use App\Security\QrImage;
use App\Security\Totp;
use RuntimeException;

final class TwoFactor
{
    public static function enabled(): bool
    {
        $userId = Auth::id();
        return $userId !== null && self::has($userId);
    }

    public static function has(int $userId): bool
    {
        $row = Connection::one('SELECT totp_enabled, totp_secret FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
        return $row !== null && (int) $row['totp_enabled'] === 1 && trim((string) $row['totp_secret']) !== '';
    }

    public static function begin(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('Entre na plataforma antes de ativar o 2FA.');
        }
        if (self::has($userId)) {
            throw new RuntimeException('O 2FA já está ativo nesta conta.');
        }
        $secret = Totp::secret();
        Session::put('totp_setup', Crypto::encrypt($secret));
        $account = (string) (Auth::user()['email'] ?? 'whatsapp');
        return self::provision($secret, $account);
    }

    public static function activate(string $code): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('Entre na plataforma antes de ativar o 2FA.');
        }
        if (self::has($userId)) {
            return;
        }
        $secret = self::pendingSetup();
        if ($secret === '' || !Totp::verify($secret, $code)) {
            throw new RuntimeException('Código do 2FA inválido.');
        }
        self::save($userId, $secret);
        Session::forget('totp_setup');
    }

    public static function assertCode(string $code): void
    {
        $userId = Auth::id();
        if ($userId === null || !self::has($userId)) {
            throw new RuntimeException('Ative o 2FA antes de conectar ou desconectar o WhatsApp.');
        }
        if (!self::check($userId, $code)) {
            throw new RuntimeException('Informe o código atual do 2FA.');
        }
    }

    public static function check(int $userId, string $code): bool
    {
        $secret = self::secretFor($userId);
        return $secret !== '' && Totp::verify($secret, $code);
    }

    public static function save(int $userId, string $secret): void
    {
        Connection::run(
            'UPDATE users SET totp_secret = ?, totp_enabled = 1, updated_at = ? WHERE id = ?',
            [Crypto::encrypt($secret), now(), $userId]
        );
    }

    public static function provision(string $secret, string $account): array
    {
        $uri = Totp::provisioningUri($secret, $account, 'Drive Xperience');
        return [
            'secret' => $secret,
            'qr' => QrImage::dataUri($uri),
        ];
    }

    private static function pendingSetup(): string
    {
        $stored = trim((string) Session::get('totp_setup', ''));
        if ($stored === '') {
            $stored = trim((string) Settings::get('wa_2fa_pending', ''));
        }
        if ($stored === '') {
            return '';
        }
        try {
            return Crypto::decrypt($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function secretFor(int $userId): string
    {
        $stored = trim((string) Connection::value('SELECT totp_secret FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]));
        if ($stored === '') {
            return '';
        }
        try {
            return Crypto::decrypt($stored);
        } catch (\Throwable) {
            return '';
        }
    }
}
