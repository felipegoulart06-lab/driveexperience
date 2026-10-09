<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Database\Installer;
use App\Database\Schema;
use App\Http\Request;
use App\Services\Settings;

final class InstallController
{
    public function form(Request $request): void
    {
        if (Schema::installed() && Connection::value('SELECT id FROM users LIMIT 1')) {
            view('errors/status', ['title' => 'Sistema instalado', 'message' => 'A instalação já foi concluída. Use o login.', '_layout' => 'layouts/guest.php']);
            return;
        }
        view('auth/install', ['title' => 'Instalar', '_layout' => 'layouts/guest.php', 'error' => null]);
    }

    public function store(Request $request): void
    {
        $this->token($request->str('token'), 'INSTALL_TOKEN');
        if (env('APP_ENV') === 'production' && $request->bool('demo')) {
            view('auth/install', ['title' => 'Instalar', '_layout' => 'layouts/guest.php', 'error' => 'Dados de demonstração não podem ser carregados em produção.']);
            return;
        }
        try {
            $result = Installer::run([
                'admin_name' => $request->str('admin_name', 'Administrador'),
                'admin_email' => $request->str('admin_email'),
                'admin_password' => (string) $request->input('admin_password', ''),
                'demo' => $request->bool('demo'),
            ]);
        } catch (\Throwable $exception) {
            view('auth/install', ['title' => 'Instalar', '_layout' => 'layouts/guest.php', 'error' => $exception->getMessage()]);
            return;
        }
        view('auth/installed', ['title' => 'Instalado', '_layout' => 'layouts/guest.php', 'result' => $result]);
    }

    public function emergency(Request $request): void
    {
        $this->token($request->str('token'), 'EMERGENCY_RESET_TOKEN');
        Installer::guardPassword((string) $request->input('password', ''));
        $email = mb_strtolower($request->str('email'));
        $user = Connection::one('SELECT id FROM users WHERE email = ?', [$email]);
        if (!$user) {
            json_out(['error' => 'Usuário não encontrado.'], 404);
        }
        Connection::run('UPDATE users SET password_hash = ?, active = 1, updated_at = ? WHERE id = ?', [password_hash((string) $request->input('password'), PASSWORD_DEFAULT), now(), (int) $user['id']]);
        audit('emergency_reset', 'user', (int) $user['id']);
        json_out(['ok' => true]);
    }

    private function token(string $given, string $envKey): void
    {
        $expected = (string) env($envKey, '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            http_response_code(404);
            exit;
        }
    }
}
