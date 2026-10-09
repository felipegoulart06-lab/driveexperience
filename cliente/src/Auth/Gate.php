<?php

declare(strict_types=1);

namespace Cliente\Auth;

use App\Database\Connection;
use App\Support\ValidationException;

final class Gate
{
    public static function id(): ?int
    {
        $id = $_SESSION['client_account_id'] ?? null;
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function user(): ?array
    {
        $id = self::id();
        if (!$id) {
            return null;
        }
        return Connection::one(
            'SELECT a.*, c.full_name, c.phone, c.whatsapp, c.email AS crm_email, c.birth_date, c.is_demo
             FROM client_accounts a JOIN clients c ON c.id = a.client_id
             WHERE a.id = ? AND a.deleted_at IS NULL',
            [$id]
        );
    }

    public static function require(): array
    {
        $user = self::user();
        if (!$user) {
            header('Location: /entrar');
            exit;
        }
        return $user;
    }

    public static function csrf(): string
    {
        $token = $_SESSION['_csrf'] ?? '';
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $token;
        }
        return $token;
    }

    public static function checkCsrf(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        $known = (string) ($_SESSION['_csrf'] ?? '');
        if ($known === '' || !hash_equals($known, $sent)) {
            throw new ValidationException(['form' => 'A sessão expirou. Atualize a página.']);
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
