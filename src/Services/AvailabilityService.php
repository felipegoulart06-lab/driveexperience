<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Domain\Statuses;
use App\Support\ValidationException;

final class AvailabilityService
{
    public function assertBookable(int $vehicleId, string $startsAt, string $endsAt, ?int $ignoreId = null, bool $allowOutsideHours = false): void
    {
        $vehicle = Connection::one('SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL', [$vehicleId]);
        if (!$vehicle || !(int) $vehicle['active']) {
            throw new ValidationException(['vehicle_id' => 'O veículo não está disponível para reserva.']);
        }
        if (in_array(Connection::driver(), ['mysql', 'pgsql'], true)) {
            Connection::one('SELECT id FROM vehicles WHERE id = ? FOR UPDATE', [$vehicleId]);
        }
        $errors = [];
        $date = substr($startsAt, 0, 10);
        $past = $date < today();
        if (!$past && in_array((string) $vehicle['base_status'], ['em_manutencao', 'indisponivel'], true)) {
            $errors['vehicle_id'] = 'O veículo está ' . mb_strtolower(Statuses::label('vehicle', (string) $vehicle['base_status'])) . ' e não pode ser reservado.';
        }
        if (!$past && !$allowOutsideHours) {
            $weekday = (int) date('w', (int) strtotime($date));
            $hours = Connection::one('SELECT * FROM operating_hours WHERE weekday = ?', [$weekday]);
            $opens = $date . ' ' . ($hours['opens_at'] ?? '00:00') . ':00';
            $closes = $date . ' ' . ($hours['closes_at'] ?? '00:00') . ':00';
            if (!$hours || !(int) $hours['active'] || $startsAt < $opens || $endsAt > $closes) {
                $errors['scheduled_date'] = 'O horário está fora do expediente. Ajuste o horário ou marque o registro fora do expediente.';
            }
        }
        $buffer = max(0, (int) Settings::get('buffer_minutes', '20'));
        $blockedEnd = add_minutes($endsAt, $buffer);
        $rows = Connection::all(
            "SELECT id, code, starts_at, ends_at FROM reservations
             WHERE vehicle_id = ? AND deleted_at IS NULL AND operational_status <> 'cancelado'
             AND scheduled_date BETWEEN ? AND ? AND starts_at IS NOT NULL AND ends_at IS NOT NULL",
            [$vehicleId, date('Y-m-d', strtotime($date . ' -1 day')), date('Y-m-d', strtotime($date . ' +1 day'))]
        );
        foreach ($rows as $row) {
            if ($ignoreId && (int) $row['id'] === $ignoreId) {
                continue;
            }
            $otherEnd = add_minutes((string) $row['ends_at'], $buffer);
            if ($startsAt < $otherEnd && (string) $row['starts_at'] < $blockedEnd) {
                $errors['scheduled_date'] = 'Horário indisponível: conflita com a reserva ' . $row['code'] . ', já considerado o intervalo entre experiências.';
                break;
            }
        }
        $blocks = Connection::all(
            'SELECT reason FROM schedule_blocks WHERE (vehicle_id IS NULL OR vehicle_id = ?) AND starts_at < ? AND ends_at > ? ORDER BY starts_at',
            [$vehicleId, $blockedEnd, $startsAt]
        );
        if ($blocks !== []) {
            $errors['scheduled_date'] = 'Horário bloqueado: ' . $blocks[0]['reason'] . '.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
