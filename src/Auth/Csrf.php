<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Session;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verify(string $token): void
    {
        $known = Session::get('_csrf');
        if (!is_string($known) || $token === '' || !hash_equals($known, $token)) {
            throw new \App\Support\AuthException('A sessão do formulário expirou. Atualize a página e tente novamente.');
        }
    }
}
