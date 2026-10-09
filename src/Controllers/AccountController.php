<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;

final class AccountController
{
    public function form(Request $request): void
    {
        view('auth/account', ['title' => 'Minha conta']);
    }

    public function password(Request $request): void
    {
        $user = Connection::one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $password = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');
        if (!password_verify((string) $request->input('current_password', ''), (string) $user['password_hash']) || !password_is_strong($password) || $password !== $confirmation) {
            back_with_errors(['password' => 'Confira a senha atual e use uma nova senha com 12 caracteres, letras e números.'], '/conta');
        }
        Connection::run('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now(), Auth::id()]);
        audit('password', 'user', (int) Auth::id());
        redirect_with('/conta', 'ok', 'Senha alterada.');
    }
}
