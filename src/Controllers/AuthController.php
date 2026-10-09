<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Http\Request;
use App\Support\ValidationException;

final class AuthController
{
    public function form(Request $request): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        view('auth/login', ['title' => 'Entrar', '_layout' => 'layouts/guest.php']);
    }

    public function login(Request $request): void
    {
        $ok = false;
        try {
            $ok = Auth::attempt($request->str('email'), (string) $request->input('password', ''), $request->ip());
        } catch (ValidationException $exception) {
            back_with_errors($exception->errors, '/login');
        }
        if (!$ok) {
            back_with_errors(['email' => 'E-mail ou senha inválidos.'], '/login');
        }
        redirect(Auth::intended('/'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        redirect('/login');
    }
}
