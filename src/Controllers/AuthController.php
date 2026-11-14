<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Session;
use App\Security\Crypto;
use App\Services\TwoFactor;
use App\Support\ValidationException;

final class AuthController
{
    public function form(Request $request): void
    {
        if ((string) $request->query('cancelar', '') === '1') {
            Auth::cancelPending();
        }
        if (Auth::check()) {
            redirect('/');
        }
        if (Auth::pendingTwoFactor() !== null) {
            redirect('/login/2fa');
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
        redirect('/login/2fa');
    }

    public function twoFactorForm(Request $request): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $pending = Auth::pendingTwoFactor();
        if ($pending === null) {
            redirect('/login');
        }
        $enroll = (string) ($pending['enroll'] ?? '') !== '';
        $secret = '';
        $qr = '';
        if ($enroll) {
            try {
                $secret = Crypto::decrypt((string) $pending['enroll']);
            } catch (\Throwable) {
                Auth::cancelPending();
                redirect('/login');
            }
            $email = (string) Connection::value('SELECT email FROM users WHERE id = ?', [(int) $pending['user_id']]);
            $card = TwoFactor::provision($secret, $email !== '' ? $email : 'conta');
            $secret = $card['secret'];
            $qr = $card['qr'];
        }
        view('auth/two-factor', [
            'title' => '2FA',
            '_layout' => 'layouts/guest.php',
            'enroll' => $enroll,
            'secret' => $secret,
            'qr' => $qr,
        ]);
    }

    public function twoFactor(Request $request): void
    {
        try {
            Auth::confirmPending($request->str('code'));
        } catch (ValidationException $exception) {
            if (Auth::pendingTwoFactor() === null) {
                Session::putErrors($exception->errors, ['email' => '']);
                redirect('/login');
            }
            back_with_errors($exception->errors, '/login/2fa');
        }
        redirect(Auth::intended('/'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        redirect('/login');
    }
}
