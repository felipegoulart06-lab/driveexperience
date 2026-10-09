<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class UserService
{
    public function list(): array
    {
        return Connection::all('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL ORDER BY u.name');
    }

    public function find(int $id): ?array
    {
        return Connection::one('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL', [$id]);
    }

    public function save(array $data, ?int $id, int $actorId): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $role = Connection::one('SELECT * FROM roles WHERE id = ?', [(int) ($data['role_id'] ?? 0)]);
        $errors = [];
        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) && !preg_match('/^[^@\s]+@localhost$/', $email)) {
            $errors['email'] = 'E-mail inválido.';
        }
        if (!$role) {
            $errors['role_id'] = 'Selecione o perfil.';
        }
        $duplicate = Connection::one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int) $id]);
        if ($duplicate) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }
        $password = (string) ($data['password'] ?? '');
        if (!$id && !password_is_strong($password)) {
            $errors['password'] = 'A senha precisa ter 12 caracteres, com letras e números.';
        }
        if (!$id && $password !== (string) ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'A confirmação da senha não confere.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->guardLastAdmin($id, (string) $role['slug'], !empty($data['active']));
        $stamp = now();
        if ($id) {
            Connection::run('UPDATE users SET role_id = ?, name = ?, email = ?, active = ?, updated_at = ? WHERE id = ?', [(int) $role['id'], $name, $email, !empty($data['active']) ? 1 : 0, $stamp, $id]);
            audit('update', 'user', $id, ['email' => $email]);
            return $id;
        }
        $newId = Connection::insert('INSERT INTO users (role_id, name, email, password_hash, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)', [(int) $role['id'], $name, $email, password_hash($password, PASSWORD_DEFAULT), !empty($data['is_demo']) ? 1 : 0, $stamp, $stamp]);
        audit('create', 'user', $newId, ['email' => $email, 'actor' => $actorId]);
        return $newId;
    }

    public function changePassword(int $id, string $password, string $confirmation): void
    {
        $user = $this->find($id);
        if (!$user) {
            throw new NotFoundException('Usuário não encontrado.');
        }
        $errors = [];
        if (!password_is_strong($password)) {
            $errors['password'] = 'A nova senha precisa ter 12 caracteres, com letras e números.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'A confirmação da senha não confere.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        Connection::run('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now(), $id]);
        audit('password', 'user', $id);
    }

    public function permissions(): array
    {
        return Connection::all('SELECT * FROM permissions ORDER BY module, name');
    }

    public function savePermissions(int $roleId, array $slugs): void
    {
        $role = Connection::one('SELECT * FROM roles WHERE id = ?', [$roleId]);
        if (!$role) {
            throw new NotFoundException('Perfil não encontrado.');
        }
        if ($role['slug'] === 'administrador') {
            throw new ValidationException(['role' => 'O administrador mantém acesso completo.']);
        }
        Connection::transaction(function () use ($roleId, $slugs): void {
            Connection::run('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
            foreach ($slugs as $slug) {
                $permissionId = Connection::value('SELECT id FROM permissions WHERE slug = ?', [$slug]);
                if ($permissionId) {
                    Connection::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, (int) $permissionId]);
                }
            }
        });
        audit('permissions', 'role', $roleId);
    }

    private function guardLastAdmin(?int $id, string $roleSlug, bool $active): void
    {
        if (!$id) {
            return;
        }
        $current = $this->find($id);
        if (!$current || $current['role_slug'] !== 'administrador') {
            return;
        }
        $admins = (int) Connection::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'administrador' AND u.active = 1 AND u.deleted_at IS NULL");
        if ($admins <= 1 && ($roleSlug !== 'administrador' || !$active)) {
            throw new ValidationException(['role_id' => 'Não é possível remover o último administrador ativo.']);
        }
    }
}
