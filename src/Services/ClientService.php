<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class ClientService
{
    public function find(int $id): ?array
    {
        return Connection::one(
            'SELECT c.*, o.name AS origin_name, o.requires_platform, p.name AS platform_name
             FROM clients c
             LEFT JOIN client_origins o ON o.id = c.origin_id
             LEFT JOIN platforms p ON p.id = c.platform_id
             WHERE c.id = ?',
            [$id]
        );
    }

    public function list(array $filters, int $page = 1): array
    {
        $page = max(1, $page);
        $where = ['1 = 1'];
        $params = [];
        if (empty($filters['archived'])) {
            $where[] = 'c.archived_at IS NULL';
        }
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = like_term($term);
            $digits = phone_digits($term);
            $where[] = '(c.full_name LIKE ? ESCAPE \'\\\' OR c.email LIKE ? ESCAPE \'\\\' OR c.cpf LIKE ? ESCAPE \'\\\' OR c.phone_digits LIKE ? ESCAPE \'\\\')';
            array_push($params, $like, $like, like_term(only_digits($term)), $digits !== '' ? like_term($digits) : $like);
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Connection::value("SELECT COUNT(*) FROM clients c WHERE {$sqlWhere}", $params);
        $pages = max(1, (int) ceil($total / 15));
        $page = min($page, $pages);
        $rows = Connection::all(
            "SELECT c.*, o.name AS origin_name FROM clients c
             LEFT JOIN client_origins o ON o.id = c.origin_id
             WHERE {$sqlWhere} ORDER BY c.full_name LIMIT 15 OFFSET " . (($page - 1) * 15),
            $params
        );
        return compact('rows', 'total', 'page', 'pages');
    }

    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        return $this->list(['q' => $term], 1)['rows'];
    }

    public function duplicates(array $data, ?int $excludeId = null): array
    {
        $clauses = [];
        $params = [];
        $cpf = only_digits((string) ($data['cpf'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = phone_digits((string) ($data['phone'] ?? ''));
        $whatsapp = phone_digits((string) ($data['whatsapp'] ?? ''));
        if (strlen($cpf) === 11) {
            $clauses[] = 'cpf = ?';
            $params[] = $cpf;
        }
        if ($email !== '') {
            $clauses[] = 'email = ?';
            $params[] = $email;
        }
        foreach (array_unique(array_filter([$phone, $whatsapp])) as $number) {
            $clauses[] = '(phone_digits = ? OR whatsapp_digits = ?)';
            array_push($params, $number, $number);
        }
        if ($clauses === []) {
            return [];
        }
        $sql = 'SELECT id, full_name, cpf, phone, email, city, state FROM clients WHERE archived_at IS NULL AND anonymized_at IS NULL AND (' . implode(' OR ', $clauses) . ')';
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        return Connection::all($sql . ' ORDER BY id', $params);
    }

    public function resolve(array $data, string $confirm, ?int $linkId, int $userId): int
    {
        if (($data['mode'] ?? 'new') === 'existing') {
            $client = $this->find((int) ($data['client_id'] ?? 0));
            if (!$client || $client['archived_at'] || $client['anonymized_at']) {
                throw new ValidationException(['client_id' => 'Selecione um cliente ativo.']);
            }
            return (int) $client['id'];
        }
        $duplicates = $this->duplicates($data);
        if ($duplicates !== [] && $confirm === '') {
            throw new ValidationException([
                'client_name' => 'Já existe um cadastro compatível. Vincule o cliente ou confirme a criação de um novo.',
                '_duplicates' => json_encode(array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'full_name' => $row['full_name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'cpf' => mask_cpf($row['cpf'], false),
                ], $duplicates), JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ($confirm === 'link') {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $duplicates);
            if (!$linkId || !in_array($linkId, $ids, true)) {
                throw new ValidationException(['link_client_id' => 'Escolha qual cadastro existente deve ser vinculado.']);
            }
            $this->fillBlanks($linkId, $data, $userId);
            return $linkId;
        }
        return $this->create($data, $userId);
    }

    public function matchExternal(array $data): array
    {
        $duplicates = $this->duplicates($data);
        if ($duplicates === []) {
            return ['status' => 'none'];
        }
        $cpf = only_digits((string) ($data['cpf'] ?? ''));
        $ids = [];
        foreach ($duplicates as $row) {
            if ($cpf !== '' && only_digits((string) $row['cpf']) !== '' && only_digits((string) $row['cpf']) !== $cpf) {
                return ['status' => 'conflict', 'message' => 'O documento informado diverge de um cadastro já encontrado. Resolva o cliente manualmente.'];
            }
            $ids[(int) $row['id']] = true;
        }
        if (count($ids) > 1) {
            return ['status' => 'ambiguous', 'message' => 'Há mais de um cliente compatível. A venda não foi criada para evitar vínculo incorreto.'];
        }
        return ['status' => 'one', 'id' => (int) array_key_first($ids)];
    }

    public function create(array $data, int $userId): int
    {
        $clean = $this->check($data);
        $stamp = now();
        $id = Connection::insert(
            'INSERT INTO clients (full_name, cpf, phone, phone_digits, whatsapp, whatsapp_digits, email, city, state, birth_date, origin_id, platform_id, external_id, notes, is_demo, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $clean['full_name'], $clean['cpf'], $clean['phone'], phone_digits($clean['phone']),
                $clean['whatsapp'], phone_digits($clean['whatsapp']), $clean['email'], $clean['city'], $clean['state'],
                $clean['birth_date'], $clean['origin_id'], $clean['platform_id'], $clean['external_id'], $clean['notes'],
                !empty($data['is_demo']) ? 1 : 0, $userId, $userId, $stamp, $stamp,
            ]
        );
        activity('cliente', 'Cliente cadastrado: ' . $clean['full_name'], 'client', $id);
        audit('create', 'client', $id, ['full_name' => $clean['full_name']]);
        return $id;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $current = $this->find($id);
        if (!$current || $current['anonymized_at']) {
            throw new NotFoundException('Cliente não encontrado.');
        }
        if (!array_key_exists('cpf', $data)) {
            $data['cpf'] = $current['cpf'];
        }
        $clean = $this->check($data, $id);
        if ($clean['cpf']) {
            $other = Connection::one(
                'SELECT id FROM clients WHERE cpf = ? AND id <> ? AND archived_at IS NULL AND anonymized_at IS NULL',
                [$clean['cpf'], $id]
            );
            if ($other) {
                throw new ValidationException(['cpf' => 'Já existe outro cliente ativo com este CPF.']);
            }
        }
        Connection::run(
            'UPDATE clients SET full_name = ?, cpf = ?, phone = ?, phone_digits = ?, whatsapp = ?, whatsapp_digits = ?, email = ?, city = ?, state = ?, birth_date = ?, origin_id = ?, platform_id = ?, external_id = ?, notes = ?, updated_by = ?, updated_at = ? WHERE id = ?',
            [
                $clean['full_name'], $clean['cpf'], $clean['phone'], phone_digits($clean['phone']),
                $clean['whatsapp'], phone_digits($clean['whatsapp']), $clean['email'], $clean['city'], $clean['state'],
                $clean['birth_date'], $clean['origin_id'], $clean['platform_id'], $clean['external_id'], $clean['notes'],
                $userId, now(), $id,
            ]
        );
        activity('cliente', 'Cliente atualizado: ' . $clean['full_name'], 'client', $id);
        audit('update', 'client', $id, ['full_name' => $clean['full_name']]);
    }

    public function archive(int $id, int $userId): void
    {
        $client = $this->find($id);
        if (!$client) {
            throw new NotFoundException('Cliente não encontrado.');
        }
        Connection::run('UPDATE clients SET archived_at = ?, updated_by = ?, updated_at = ? WHERE id = ?', [now(), $userId, now(), $id]);
        activity('cliente', 'Cliente arquivado: ' . $client['full_name'], 'client', $id);
        audit('archive', 'client', $id);
    }

    public function addContact(int $id, array $data, int $userId): void
    {
        if (!$this->find($id)) {
            throw new NotFoundException('Cliente não encontrado.');
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        if ($notes === '') {
            throw new ValidationException(['notes' => 'Descreva o contato realizado.']);
        }
        $channel = trim((string) ($data['channel'] ?? 'interno'));
        Connection::insert(
            'INSERT INTO client_contacts (client_id, user_id, channel, notes, contacted_at, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $userId, $channel !== '' ? $channel : 'interno', $notes, now(), now()]
        );
        activity('contato', 'Contato registrado no cliente', 'client', $id);
    }

    public function history(int $id): array
    {
        return [
            'reservations' => Connection::all(
                'SELECT r.*, v.brand, v.model FROM reservations r LEFT JOIN vehicles v ON v.id = r.vehicle_id WHERE r.client_id = ? AND r.deleted_at IS NULL ORDER BY r.scheduled_date IS NULL, r.scheduled_date DESC, r.id DESC',
                [$id]
            ),
            'payments' => Connection::all(
                'SELECT p.*, m.name AS method_name FROM payments p LEFT JOIN payment_methods m ON m.id = p.method_id WHERE p.client_id = ? ORDER BY p.paid_at DESC, p.id DESC',
                [$id]
            ),
            'documents' => Connection::all(
                'SELECT id, public_id, type, title, version, issued_at, redacted_at FROM documents WHERE client_id = ? ORDER BY issued_at DESC',
                [$id]
            ),
            'contacts' => Connection::all(
                'SELECT c.*, u.name AS user_name FROM client_contacts c LEFT JOIN users u ON u.id = c.user_id WHERE c.client_id = ? ORDER BY c.contacted_at DESC',
                [$id]
            ),
            'productions' => Connection::all('SELECT * FROM productions WHERE client_id = ? ORDER BY id DESC', [$id]),
        ];
    }

    public function export(int $id): array
    {
        $client = $this->find($id);
        if (!$client) {
            throw new NotFoundException('Cliente não encontrado.');
        }
        audit('export', 'client', $id);
        $history = $this->history($id);
        unset($client['notes']);
        return ['client' => $client, 'history' => $history];
    }

    public function anonymize(int $id, int $userId): void
    {
        $client = $this->find($id);
        if (!$client) {
            throw new NotFoundException('Cliente não encontrado.');
        }
        Connection::transaction(function () use ($id, $userId): void {
            Connection::run(
                'UPDATE clients SET full_name = ?, cpf = NULL, phone = NULL, phone_digits = NULL, whatsapp = NULL, whatsapp_digits = NULL, email = NULL, city = NULL, state = NULL, birth_date = NULL, external_id = NULL, notes = NULL, instagram = NULL, cnh_file_id = NULL, residence_file_id = NULL, anonymized_at = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                ['Titular anonimizado #' . $id, now(), $userId, now(), $id]
            );
            Connection::run('DELETE FROM client_contacts WHERE client_id = ?', [$id]);
            Connection::run('UPDATE documents SET content = NULL, redacted_at = ?, byte_size = 0 WHERE client_id = ? AND redacted_at IS NULL', [now(), $id]);
        });
        activity('privacidade', 'Dados pessoais removidos mediante solicitação', 'client', $id);
        audit('anonymize', 'client', $id);
    }

    private function fillBlanks(int $id, array $data, int $userId): void
    {
        $current = $this->find($id);
        if (!$current) {
            throw new ValidationException(['link_client_id' => 'Cliente não encontrado.']);
        }
        $clean = $this->check(array_merge([
            'full_name' => $current['full_name'],
            'cpf' => $current['cpf'],
            'phone' => $current['phone'],
            'whatsapp' => $current['whatsapp'],
            'email' => $current['email'],
            'city' => $current['city'],
            'state' => $current['state'],
            'birth_date' => $current['birth_date'],
            'origin_id' => $current['origin_id'],
            'platform_id' => $current['platform_id'],
            'external_id' => $current['external_id'],
            'notes' => $current['notes'],
        ], array_filter($data, static fn ($value): bool => $value !== null && $value !== '')), $id);
        foreach (['cpf', 'phone', 'whatsapp', 'email', 'city', 'state', 'birth_date', 'external_id'] as $field) {
            if (!empty($current[$field])) {
                $clean[$field] = $current[$field];
            }
        }
        $clean['full_name'] = $current['full_name'];
        $clean['notes'] = $current['notes'];
        $this->update($id, $clean, $userId);
    }

    public function check(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $name = person_name((string) ($data['full_name'] ?? ''));
        if (mb_strlen($name) < 3) {
            $errors['full_name'] = 'Informe o nome completo.';
        }
        $cpf = only_digits((string) ($data['cpf'] ?? ''));
        if ($cpf !== '' && !valid_cpf($cpf)) {
            $errors['cpf'] = 'CPF inválido.';
        }
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }
        foreach (['phone' => 'Telefone', 'whatsapp' => 'WhatsApp'] as $field => $label) {
            $typed = trim((string) ($data[$field] ?? ''));
            $raw = only_digits($typed);
            if ($raw === '') {
                continue;
            }
            if (str_starts_with($typed, '+') && !str_starts_with($raw, '55')) {
                continue;
            }
            $national = phone_digits($typed);
            $brazilian = in_array(strlen($national), [10, 11], true) && ($national === $raw || str_starts_with($raw, '55'));
            if (!$brazilian && (strlen($raw) < 8 || strlen($raw) > 15)) {
                $errors[$field] = $label . ' deve ter DDD e número.';
            }
        }
        $instagram = ltrim(trim((string) ($data['instagram'] ?? '')), '@');
        if ($instagram !== '' && !preg_match('/^[A-Za-z0-9._]{2,30}$/', $instagram)) {
            $errors['instagram'] = 'Informe só o usuário do Instagram, sem espaços.';
        }
        $birth = blank_to_null((string) ($data['birth_date'] ?? ''));
        if ($birth !== null) {
            $birth = parse_date($birth);
            if ($birth === null || $birth > today()) {
                $errors['birth_date'] = 'Data de nascimento inválida.';
            }
        }
        $state = strtoupper(trim((string) ($data['state'] ?? '')));
        if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) {
            $errors['state'] = 'Use a sigla do estado.';
        }
        $originId = (int) ($data['origin_id'] ?? 0);
        $origin = $originId ? Connection::one('SELECT * FROM client_origins WHERE id = ? AND active = 1', [$originId]) : null;
        $platformId = (int) ($data['platform_id'] ?? 0) ?: null;
        if ($origin && (int) $origin['requires_platform'] && !$platformId) {
            $errors['platform_id'] = 'Informe a plataforma de origem.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [
            'full_name' => $name,
            'cpf' => $cpf !== '' ? $cpf : null,
            'phone' => blank_to_null((string) ($data['phone'] ?? '')),
            'whatsapp' => blank_to_null((string) ($data['whatsapp'] ?? '')),
            'email' => $email !== '' ? $email : null,
            'city' => blank_to_null((string) ($data['city'] ?? '')),
            'state' => $state !== '' ? $state : null,
            'birth_date' => $birth,
            'origin_id' => $origin ? (int) $origin['id'] : null,
            'platform_id' => $platformId,
            'external_id' => blank_to_null((string) ($data['external_id'] ?? '')),
            'notes' => blank_to_null((string) ($data['notes'] ?? '')),
            'instagram' => $instagram !== '' ? $instagram : null,
        ];
    }
}
