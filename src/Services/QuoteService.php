<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class QuoteService
{
    public function list(): array
    {
        return Connection::all('SELECT q.*, c.full_name FROM quotes q JOIN clients c ON c.id = q.client_id ORDER BY q.id DESC');
    }

    public function find(int $id): ?array
    {
        $quote = Connection::one('SELECT q.*, c.full_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?', [$id]);
        if (!$quote) {
            return null;
        }
        $quote['items'] = Connection::all('SELECT * FROM quote_items WHERE quote_id = ?', [$id]);
        return $quote;
    }

    public function create(array $input, int $userId): int
    {
        $client = (new ClientService())->find((int) ($input['client_id'] ?? 0));
        $ids = array_map('intval', (array) ($input['modality_id'] ?? []));
        if (!$client || $ids === []) {
            throw new ValidationException(['client_id' => 'Selecione o cliente e ao menos um item.']);
        }
        $items = [];
        $total = 0;
        $vehicleId = (int) ($input['vehicle_id'] ?? 0) ?: null;
        foreach (Connection::all('SELECT * FROM modalities WHERE active = 1 AND id IN (' . implode(',', $ids) . ')') as $modality) {
            $price = $modality['type'] === 'experiencia' && $vehicleId
                ? Connection::value('SELECT price_cents FROM vehicle_prices WHERE vehicle_id = ? AND modality_id = ?', [$vehicleId, (int) $modality['id']])
                : $modality['base_price_cents'];
            $price = (int) ($price ?? $modality['base_price_cents']);
            $items[] = [$modality['id'], $modality['name'], $price];
            $total += $price;
        }
        $number = Sequence::next('quote-' . date('Y'));
        $code = sprintf('ORC-%s-%05d', date('Y'), $number);
        $stamp = now();
        $id = Connection::insert(
            'INSERT INTO quotes (public_id, code, client_id, valid_until, status, notes, conditions, total_cents, vehicle_id, scheduled_date, start_time, participants, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [bin2hex(random_bytes(16)), $code, (int) $client['id'], parse_date($input['valid_until'] ?? '') ?: date('Y-m-d', strtotime('+7 days')), 'aberto', blank_to_null((string) ($input['notes'] ?? '')), (string) Settings::get('quote_conditions', ''), $total, $vehicleId, parse_date($input['scheduled_date'] ?? ''), parse_time($input['start_time'] ?? ''), max(1, (int) ($input['participants'] ?? 1)), $userId, $stamp, $stamp]
        );
        foreach ($items as [$modalityId, $name, $price]) {
            Connection::insert('INSERT INTO quote_items (quote_id, modality_id, description, quantity, unit_price_cents, total_cents) VALUES (?, ?, ?, 1, ?, ?)', [$id, $modalityId, $name, $price, $price]);
        }
        activity('orcamento', 'Orçamento ' . $code . ' emitido', 'quote', $id);
        return $id;
    }

    public function convert(int $id, int $userId): int
    {
        $quote = $this->find($id);
        if (!$quote) {
            throw new NotFoundException('Orçamento não encontrado.');
        }
        $input = [
            'client_mode' => 'existing',
            'client_id' => (int) $quote['client_id'],
            'origin_id' => (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'venda-direta'"),
            'modality_id' => array_column($quote['items'], 'modality_id'),
            'vehicle_id' => $quote['vehicle_id'],
            'scheduled_date' => $quote['scheduled_date'],
            'start_time' => $quote['start_time'],
            'participants' => $quote['participants'],
            'notes' => 'Convertido do orçamento ' . $quote['code'],
            'operational_status' => 'aguardando_confirmacao',
        ];
        $result = (new ReservationService())->create($input, $userId);
        Connection::run('UPDATE quotes SET status = ?, updated_at = ? WHERE id = ?', ['convertido', now(), $id]);
        return (int) $result['id'];
    }
}
