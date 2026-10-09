<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\UserService;

final class UserController
{
    public function index(Request $request): void
    {
        $users = new UserService();
        view('users/index', [
            'title' => 'Usuários',
            'users' => $users->list(),
            'roles' => Connection::all('SELECT * FROM roles ORDER BY name'),
            'permissions' => $users->permissions(),
            'grants' => Connection::all('SELECT role_id, permission_id FROM role_permissions'),
        ]);
    }

    public function store(Request $request): void
    {
        (new UserService())->save($request->all(), null, (int) Auth::id());
        redirect_with('/usuarios', 'ok', 'Usuário criado.');
    }

    public function update(Request $request, string $id): void
    {
        (new UserService())->save($request->all(), (int) $id, (int) Auth::id());
        redirect_with('/usuarios', 'ok', 'Usuário atualizado.');
    }

    public function password(Request $request, string $id): void
    {
        (new UserService())->changePassword((int) $id, (string) $request->input('password', ''), (string) $request->input('password_confirmation', ''));
        redirect_with('/usuarios', 'ok', 'Senha alterada.');
    }

    public function permissions(Request $request, string $id): void
    {
        (new UserService())->savePermissions((int) $id, (array) $request->input('permissions', []));
        $role = Connection::one('SELECT slug FROM roles WHERE id = ?', [(int) $id]);
        redirect_with('/usuarios?perfil=' . rawurlencode((string) ($role['slug'] ?? '')), 'ok', 'Permissões atualizadas.');
    }
}
