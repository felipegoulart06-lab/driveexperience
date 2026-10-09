<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Domain\Statuses;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class ProductionService
{
    public function list(): array
    {
        return Connection::all('SELECT p.*, c.full_name, m.name AS package_name, r.code FROM productions p JOIN clients c ON c.id = p.client_id LEFT JOIN modalities m ON m.id = p.modality_id LEFT JOIN reservations r ON r.id = p.reservation_id ORDER BY p.id DESC');
    }

    public function find(int $id): ?array
    {
        return Connection::one('SELECT p.*, c.full_name, m.name AS package_name, r.code FROM productions p JOIN clients c ON c.id = p.client_id LEFT JOIN modalities m ON m.id = p.modality_id LEFT JOIN reservations r ON r.id = p.reservation_id WHERE p.id = ?', [$id]);
    }

    public function create(array $data, int $userId): int
    {
        $clientId = (int) ($data['client_id'] ?? 0);
        $modalityId = (int) ($data['modality_id'] ?? 0);
        $modality = Connection::one("SELECT * FROM modalities WHERE id = ? AND type = 'foto' AND active = 1", [$modalityId]);
        if (!$clientId || !$modality) {
            throw new ValidationException(['modality_id' => 'Selecione o cliente e um pacote de fotos.']);
        }
        $amount = parse_money($data['amount'] ?? '') ?: (int) $modality['base_price_cents'];
        $stamp = now();
        return Connection::insert(
            'INSERT INTO productions (public_id, client_id, reservation_id, modality_id, amount_cents, contracted_at, producer_user_id, status, due_date, private_link, notes, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [bin2hex(random_bytes(16)), $clientId, (int) ($data['reservation_id'] ?? 0) ?: null, $modalityId, $amount, $stamp, (int) ($data['producer_user_id'] ?? 0) ?: null, 'nao_iniciado', parse_date($data['due_date'] ?? ''), blank_to_null((string) ($data['private_link'] ?? '')), blank_to_null((string) ($data['notes'] ?? '')), $userId, $userId, $stamp, $stamp]
        );
    }

    public function update(int $id, array $data, int $userId): void
    {
        $current = $this->find($id);
        if (!$current) {
            throw new NotFoundException('Produção não encontrada.');
        }
        $status = (string) ($data['status'] ?? $current['status']);
        if (!array_key_exists($status, Statuses::PRODUCTION)) {
            throw new ValidationException(['status' => 'Situação de produção inválida.']);
        }
        Connection::run(
            'UPDATE productions SET status = ?, producer_user_id = ?, due_date = ?, delivered_at = ?, private_link = ?, notes = ?, updated_by = ?, updated_at = ? WHERE id = ?',
            [$status, (int) ($data['producer_user_id'] ?? 0) ?: null, parse_date($data['due_date'] ?? ''), $status === 'entregue' ? ($current['delivered_at'] ?: now()) : null, blank_to_null((string) ($data['private_link'] ?? '')), blank_to_null((string) ($data['notes'] ?? '')), $userId, now(), $id]
        );
    }
}
