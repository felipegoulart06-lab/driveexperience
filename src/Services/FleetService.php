<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Domain\Statuses;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class FleetService
{
    public function list(): array
    {
        $rows = Connection::all('SELECT * FROM vehicles WHERE deleted_at IS NULL ORDER BY brand, model');
        return array_map(fn (array $vehicle): array => $this->decorate($vehicle), $rows);
    }

    public function find(int $id): ?array
    {
        $vehicle = Connection::one('SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL', [$id]);
        return $vehicle ? $this->decorate($vehicle) : null;
    }

    public function images(?int $vehicleId = null): array
    {
        if ($vehicleId) {
            return Connection::all('SELECT id, vehicle_id, filename, sort_order, is_cover FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_cover DESC, sort_order, id', [$vehicleId]);
        }
        return Connection::all('SELECT id, vehicle_id, filename, sort_order, is_cover FROM vehicle_images ORDER BY is_cover DESC, sort_order, id');
    }

    public function prices(int $id): array
    {
        return Connection::all(
            'SELECT m.id, m.name, m.type, p.price_cents FROM modalities m LEFT JOIN vehicle_prices p ON p.modality_id = m.id AND p.vehicle_id = ? WHERE m.active = 1 ORDER BY m.sort_order',
            [$id]
        );
    }

    public function save(array $data, ?int $id, int $userId): int
    {
        $brand = trim((string) ($data['brand'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $errors = [];
        if ($brand === '' || $model === '') {
            $errors['brand'] = 'Informe marca e modelo.';
        }
        $capacity = (int) ($data['capacity'] ?? 0);
        if ($capacity < 1) {
            $errors['capacity'] = 'Informe a capacidade.';
        }
        $status = (string) ($data['base_status'] ?? 'disponivel');
        if (!in_array($status, ['disponivel', 'em_manutencao', 'indisponivel'], true)) {
            $errors['base_status'] = 'Situação cadastrada inválida.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $slug = str_replace(' ', '-', fold($brand . '-' . $model));
        $stamp = now();
        $values = [$brand, $model, (int) ($data['model_year'] ?? 0) ?: null, blank_to_null(strtoupper((string) ($data['plate'] ?? ''))), blank_to_null((string) ($data['description'] ?? '')), $capacity, $status, blank_to_null((string) ($data['notes'] ?? '')), !empty($data['active']) ? 1 : 0, $userId ? $stamp : $stamp];
        if ($id) {
            Connection::run('UPDATE vehicles SET brand = ?, model = ?, model_year = ?, plate = ?, description = ?, capacity = ?, base_status = ?, notes = ?, active = ?, updated_at = ? WHERE id = ?', [...array_slice($values, 0, 9), $stamp, $id]);
            $vehicleId = $id;
        } else {
            $vehicleId = Connection::insert('INSERT INTO vehicles (brand, model, slug, model_year, plate, description, capacity, base_status, notes, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)', [$brand, $model, $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4), $values[2], $values[3], $values[4], $capacity, $status, $values[7], $values[8], $stamp, $stamp]);
        }
        Connection::run('DELETE FROM vehicle_prices WHERE vehicle_id = ?', [$vehicleId]);
        foreach ((array) ($data['price'] ?? []) as $modalityId => $price) {
            $cents = parse_money((string) $price);
            if ($cents === null || $cents <= 0) {
                continue;
            }
            Connection::run('INSERT INTO vehicle_prices (vehicle_id, modality_id, price_cents) VALUES (?, ?, ?)', [$vehicleId, (int) $modalityId, $cents]);
        }
        audit($id ? 'update' : 'create', 'vehicle', $vehicleId, ['model' => $model]);
        return $vehicleId;
    }

    public function delete(int $id): void
    {
        $future = Connection::value("SELECT COUNT(*) FROM reservations WHERE vehicle_id = ? AND deleted_at IS NULL AND operational_status NOT IN ('cancelado', 'concluido') AND scheduled_date >= ?", [$id, today()]);
        if ((int) $future > 0) {
            throw new ValidationException(['vehicle' => 'Há reservas futuras neste veículo. Cancele ou conclua antes de desativar.']);
        }
        Connection::run('UPDATE vehicles SET deleted_at = ?, active = 0, updated_at = ? WHERE id = ?', [now(), now(), $id]);
        audit('delete', 'vehicle', $id);
    }

    public function moveImage(int $vehicleId, int $imageId, string $direction): void
    {
        if (!in_array($direction, ['esquerda', 'direita'], true)) {
            throw new ValidationException(['direction' => 'Informe para qual lado a foto deve ir.']);
        }
        $ids = array_map(static fn (array $image): int => (int) $image['id'], $this->images($vehicleId));
        $index = array_search($imageId, $ids, true);
        if ($index === false) {
            throw new NotFoundException();
        }
        $swap = $direction === 'esquerda' ? $index - 1 : $index + 1;
        if ($swap < 0 || $swap >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $this->persistImageOrder($vehicleId, $ids);
        audit('update', 'vehicle', $vehicleId, ['fotos' => 'ordem']);
    }

    public function deleteImage(int $vehicleId, int $imageId): void
    {
        $fileId = (int) Connection::value('SELECT file_id FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [$imageId, $vehicleId]);
        if ($fileId <= 0) {
            throw new NotFoundException();
        }
        $ids = [];
        foreach ($this->images($vehicleId) as $image) {
            if ((int) $image['id'] !== $imageId) {
                $ids[] = (int) $image['id'];
            }
        }
        Connection::transaction(function () use ($vehicleId, $imageId, $ids, $fileId): void {
            Connection::run('DELETE FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [$imageId, $vehicleId]);
            $this->persistImageOrder($vehicleId, $ids);
            $this->dropStoredFile($fileId);
        });
        audit('delete', 'vehicle_image', $imageId, ['vehicle' => $vehicleId]);
    }

    public function maintenance(int $id, array $data, int $userId): void
    {
        $description = trim((string) ($data['description'] ?? ''));
        $start = trim((string) ($data['starts_at'] ?? ''));
        $end = trim((string) ($data['ends_at'] ?? ''));
        if ($description === '' || !strtotime($start) || !strtotime($end) || $end <= $start) {
            throw new ValidationException(['description' => 'Informe a manutenção e um período válido.']);
        }
        $startAt = date('Y-m-d H:i:s', (int) strtotime($start));
        $endAt = date('Y-m-d H:i:s', (int) strtotime($end));
        Connection::transaction(function () use ($id, $description, $startAt, $endAt, $data, $userId): void {
            Connection::insert('INSERT INTO maintenance_logs (vehicle_id, description, starts_at, ends_at, cost_cents, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$id, $description, $startAt, $endAt, (int) (parse_money($data['cost'] ?? '0') ?? 0), $userId, now()]);
            Connection::insert('INSERT INTO schedule_blocks (vehicle_id, starts_at, ends_at, reason, block_type, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$id, $startAt, $endAt, $description, 'manutencao', $userId, now()]);
            if (!empty($data['mark_unavailable'])) {
                Connection::run('UPDATE vehicles SET base_status = ?, updated_at = ? WHERE id = ?', ['em_manutencao', now(), $id]);
            }
        });
    }

    public function modalities(): array
    {
        return Connection::all('SELECT * FROM modalities ORDER BY sort_order, name');
    }

    public function saveModality(array $data, ?int $id): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? 'outro');
        $price = parse_money($data['base_price'] ?? '0');
        if ($name === '' || $price === null || !in_array($type, ['experiencia', 'foto', 'outro'], true)) {
            throw new ValidationException(['name' => 'Informe nome, tipo e preço válidos.']);
        }
        $stamp = now();
        $duration = max(0, (int) ($data['duration_minutes'] ?? 0));
        if ($id) {
            Connection::run('UPDATE modalities SET name = ?, description = ?, type = ?, duration_minutes = ?, base_price_cents = ?, active = ?, updated_at = ? WHERE id = ?', [$name, blank_to_null((string) ($data['description'] ?? '')), $type, $duration, $price, !empty($data['active']) ? 1 : 0, $stamp, $id]);
            return $id;
        }
        return Connection::insert('INSERT INTO modalities (name, slug, description, type, duration_minutes, base_price_cents, active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)', [$name, str_replace(' ', '-', fold($name)) . '-' . random_int(10, 99), blank_to_null((string) ($data['description'] ?? '')), $type, $duration, $price, (int) Connection::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM modalities'), $stamp, $stamp]);
    }

    private function persistImageOrder(int $vehicleId, array $ids): void
    {
        Connection::transaction(function () use ($vehicleId, $ids): void {
            foreach (array_values($ids) as $order => $imageId) {
                Connection::run(
                    'UPDATE vehicle_images SET sort_order = ?, is_cover = ? WHERE id = ? AND vehicle_id = ?',
                    [$order, $order === 0 ? 1 : 0, $imageId, $vehicleId]
                );
            }
            $fileId = $ids === []
                ? null
                : Connection::value('SELECT file_id FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [(int) $ids[0], $vehicleId]);
            Connection::run('UPDATE vehicles SET image_file_id = ?, updated_at = ? WHERE id = ?', [$fileId, now(), $vehicleId]);
        });
    }

    private function dropStoredFile(int $fileId): void
    {
        $refs = (int) Connection::value('SELECT COUNT(*) FROM vehicle_images WHERE file_id = ?', [$fileId])
            + (int) Connection::value('SELECT COUNT(*) FROM vehicles WHERE image_file_id = ?', [$fileId])
            + (int) Connection::value('SELECT COUNT(*) FROM clients WHERE cnh_file_id = ? OR residence_file_id = ?', [$fileId, $fileId]);
        if ($refs === 0) {
            Connection::run('DELETE FROM stored_files WHERE id = ?', [$fileId]);
        }
    }

    private function decorate(array $vehicle): array
    {
        if (in_array((string) $vehicle['base_status'], ['em_manutencao', 'indisponivel'], true)) {
            $vehicle['display_status'] = $vehicle['base_status'];
            return $vehicle;
        }
        $now = now();
        $running = Connection::value("SELECT id FROM reservations WHERE vehicle_id = ? AND deleted_at IS NULL AND operational_status IN ('confirmado', 'em_andamento') AND starts_at <= ? AND ends_at > ?", [(int) $vehicle['id'], $now, $now]);
        if ($running) {
            $vehicle['display_status'] = 'em_utilizacao';
            return $vehicle;
        }
        $later = Connection::value("SELECT id FROM reservations WHERE vehicle_id = ? AND deleted_at IS NULL AND scheduled_date = ? AND operational_status NOT IN ('cancelado', 'concluido') AND starts_at >= ?", [(int) $vehicle['id'], today(), $now]);
        $vehicle['display_status'] = $later ? 'reservado' : 'disponivel';
        return $vehicle;
    }
}
