<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Session;
use App\Security\Crypto;
use App\Security\Totp;
use App\Services\TwoFactor;
use App\Support\AuthException;
use App\Support\ValidationException;

final class Auth
{
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private static ?array $user = null;
    private static bool $resolved = false;

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }
        $user = Connection::one(
            'SELECT u.id, u.name, u.email, u.role_id, u.active, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL',
            [(int) $id]
        );
        if (!$user || !(int) $user['active']) {
            Session::destroy();
            self::$user = null;
            return null;
        }
        $permissions = Connection::all(
            'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [(int) $user['role_id']]
        );
        $user['permissions'] = array_column($permissions, 'slug');
        self::$user = $user;
        return $user;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user['role_slug'] === 'administrador') {
            return true;
        }
        return in_array($permission, $user['permissions'], true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $request = new Request();
            if ($request->method() === 'GET') {
                Session::put('_intended', $request->path());
            }
            redirect('/login');
        }
    }

    public static function require(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            throw new AuthException('Seu perfil não possui acesso a esta função.');
        }
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $email = mb_strtolower(trim($email));
        $since = date('Y-m-d H:i:s', time() - 900);
        $attempts = (int) Connection::value(
            'SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND created_at >= ?',
            [$email, $ip, $since]
        );
        if ($attempts >= 8) {
            throw new ValidationException(['email' => 'Muitas tentativas. Aguarde 15 minutos e tente novamente.']);
        }
        $user = Connection::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);
        if (!$user || !$valid || !(int) $user['active']) {
            Connection::run(
                'INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, ?)',
                [$email, $ip, now()]
            );
            return false;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Connection::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        }
        Session::forget('user_id');
        self::$resolved = false;
        self::$user = null;
        Session::regenerate();
        $userId = (int) $user['id'];
        $enroll = '';
        if (!TwoFactor::has($userId)) {
            $enroll = Crypto::encrypt(Totp::secret());
        }
        Session::put('pending_2fa', [
            'user_id' => $userId,
            'until' => time() + 600,
            'enroll' => $enroll,
            'tries' => 0,
        ]);
        return true;
    }

    public static function pendingTwoFactor(): ?array
    {
        $pending = Session::get('pending_2fa');
        if (!is_array($pending) || (int) ($pending['until'] ?? 0) < time() || (int) ($pending['user_id'] ?? 0) <= 0) {
            if (is_array($pending)) {
                Session::forget('pending_2fa');
            }
            return null;
        }
        return $pending;
    }

    public static function cancelPending(): void
    {
        Session::forget('pending_2fa');
    }

    public static function confirmPending(string $code): void
    {
        $pending = self::pendingTwoFactor();
        if ($pending === null) {
            throw new ValidationException(['email' => 'A confirmação expirou. Entre novamente com e-mail e senha.']);
        }
        $userId = (int) $pending['user_id'];
        $user = Connection::one('SELECT id, email, active FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
        if (!$user || !(int) $user['active']) {
            self::cancelPending();
            throw new ValidationException(['email' => 'Esta conta não pode entrar.']);
        }
        $enrolling = (string) ($pending['enroll'] ?? '') !== '';
        $secret = '';
        $valid = false;
        if ($enrolling) {
            try {
                $secret = Crypto::decrypt((string) $pending['enroll']);
            } catch (\Throwable) {
                $secret = '';
            }
            $valid = $secret !== '' && Totp::verify($secret, $code);
        } else {
            $valid = TwoFactor::check($userId, $code);
        }
        if (!$valid) {
            $pending['tries'] = (int) ($pending['tries'] ?? 0) + 1;
            Connection::run(
                'INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, ?)',
                [(string) $user['email'], request_ip(), now()]
            );
            if ($pending['tries'] >= 5) {
                self::cancelPending();
                throw new ValidationException(['email' => 'Muitas tentativas do 2FA. Entre novamente com e-mail e senha.']);
            }
            Session::put('pending_2fa', $pending);
            throw new ValidationException(['code' => 'Código do 2FA inválido.']);
        }
        if ($enrolling) {
            TwoFactor::save($userId, $secret);
        }
        self::cancelPending();
        self::login($userId);
        Connection::run('DELETE FROM login_attempts WHERE email = ?', [(string) $user['email']]);
        Connection::run('UPDATE users SET last_login_at = ?, failed_logins = 0 WHERE id = ?', [now(), $userId]);
        audit('login', 'user', $userId);
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        self::$resolved = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        $id = self::$user['id'] ?? Session::get('user_id');
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
        if ($id) {
            try {
                \App\Database\Connection::run(
                    'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes_json, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [(int) $id, 'logout', 'user', (int) $id, null, request_ip(), now()]
                );
            } catch (\Throwable) {
            }
        }
    }

    public static function intended(string $fallback = '/'): string
    {
        $path = Session::pull('_intended');
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }
        return $path;
    }
}
