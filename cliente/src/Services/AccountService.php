<?php

declare(strict_types=1);

namespace Cliente\Services;

use App\Database\Connection;
use App\Support\ValidationException;
use Cliente\Auth\Gate;

final class AccountService
{
    public function register(array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $phone = phone_digits((string) ($input['phone'] ?? ''));
        $whatsapp = phone_digits((string) ($input['whatsapp'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $birth = parse_date((string) ($input['birth_date'] ?? '')) ?: null;
        $errors = [];
        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Informe o nome completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }
        if (strlen($phone) < 10) {
            $errors['phone'] = 'Informe o telefone com DDD.';
        }
        if (!password_is_strong($password) || $password !== (string) ($input['password_confirmation'] ?? '')) {
            $errors['password'] = 'A senha precisa ter 12 caracteres, com letras e números, e a confirmação igual.';
        }
        if (empty($input['terms'])) {
            $errors['terms'] = 'Aceite os termos para criar a conta.';
        }
        $taken = Connection::one('SELECT id FROM client_accounts WHERE email = ? AND deleted_at IS NULL', [$email]);
        if ($taken) {
            $errors['email'] = 'Já existe uma conta com este e-mail.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $client = Connection::one(
            'SELECT id FROM clients WHERE email = ? AND archived_at IS NULL AND anonymized_at IS NULL ORDER BY id LIMIT 1',
            [$email]
        );
        if ($client && Connection::one('SELECT id FROM client_accounts WHERE client_id = ? AND deleted_at IS NULL', [(int) $client['id']])) {
            throw new ValidationException(['email' => 'Este cadastro já possui uma conta. Entre com o e-mail.']);
        }
        $stamp = now();
        $origin = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'aplicativo'");
        Connection::transaction(function () use ($client, $name, $email, $phone, $whatsapp, $birth, $origin, $password, $input, $stamp): void {
            if ($client) {
                $clientId = (int) $client['id'];
                Connection::run(
                    'UPDATE clients SET full_name = ?, phone = ?, phone_digits = ?, whatsapp = ?, whatsapp_digits = ?, birth_date = COALESCE(?, birth_date), updated_at = ? WHERE id = ?',
                    [$name, $phone, $phone, $whatsapp ?: null, $whatsapp ?: null, $birth, $stamp, $clientId]
                );
            } else {
                $clientId = Connection::insert(
                    'INSERT INTO clients (full_name, phone, phone_digits, whatsapp, whatsapp_digits, email, birth_date, origin_id, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
                    [$name, $phone, $phone, $whatsapp ?: null, $whatsapp ?: null, $email, $birth, $origin ?: null, $stamp, $stamp]
                );
            }
            $accountId = Connection::insert(
                'INSERT INTO client_accounts (client_id, email, password_hash, marketing_opt_in, terms_accepted_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$clientId, $email, password_hash($password, PASSWORD_DEFAULT), empty($input['marketing']) ? 0 : 1, $stamp, $stamp, $stamp]
            );
            $this->attachReferral($clientId, (string) ($input['referral'] ?? ''));
            Connection::insert(
                'INSERT INTO client_audit (client_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
                [$clientId, 'conta_criada', 'Conta do aplicativo vinculada ao CRM.', $stamp]
            );
            $_SESSION['client_account_id'] = $accountId;
        });
    }

    public function login(string $email, string $password): void
    {
        $email = mb_strtolower(trim($email));
        $since = date('Y-m-d H:i:s', time() - 900);
        $attempts = (int) Connection::value(
            'SELECT COUNT(*) FROM client_login_attempts WHERE email = ? AND created_at >= ?',
            [$email, $since]
        );
        if ($attempts >= 8) {
            throw new ValidationException(['email' => 'Muitas tentativas. Aguarde alguns minutos.']);
        }
        $account = Connection::one('SELECT * FROM client_accounts WHERE email = ? AND deleted_at IS NULL', [$email]);
        if (!$account || !password_verify($password, (string) $account['password_hash'])) {
            Connection::insert(
                'INSERT INTO client_login_attempts (email, ip, created_at) VALUES (?, ?, ?)',
                [$email, request_ip(), now()]
            );
            throw new ValidationException(['email' => 'E-mail ou senha incorretos.']);
        }
        session_regenerate_id(true);
        $_SESSION['client_account_id'] = (int) $account['id'];
        Connection::insert(
            'INSERT INTO client_audit (client_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
            [(int) $account['client_id'], 'login', 'Acesso ao aplicativo.', now()]
        );
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function requestDeletion(int $clientId): void
    {
        $stamp = now();
        Connection::run('UPDATE client_accounts SET deletion_requested_at = ?, updated_at = ? WHERE client_id = ? AND deleted_at IS NULL', [$stamp, $stamp, $clientId]);
        Connection::insert(
            'INSERT INTO client_audit (client_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
            [$clientId, 'exclusao_solicitada', 'O cadastro central e as reservas já feitas permanecem até a equipe concluir a exclusão.', $stamp]
        );
    }

    private function attachReferral(int $clientId, string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }
        $referrer = Connection::one('SELECT referrer_client_id FROM referrals WHERE code = ?', [$code]);
        if (!$referrer || (int) $referrer['referrer_client_id'] === $clientId) {
            return;
        }
        $exists = Connection::value('SELECT id FROM referral_events WHERE referred_client_id = ?', [$clientId]);
        if ($exists) {
            return;
        }
        Connection::insert(
            'INSERT INTO referral_events (referrer_client_id, referred_client_id, status, created_at) VALUES (?, ?, ?, ?)',
            [(int) $referrer['referrer_client_id'], $clientId, 'pendente', now()]
        );
    }
}
