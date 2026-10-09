<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class TrackingService
{
    public function snapshot(): array
    {
        $items = [];
        $trackers = Connection::all(
            'SELECT t.*, v.brand, v.model FROM trackers t LEFT JOIN vehicles v ON v.id = t.vehicle_id AND v.deleted_at IS NULL ORDER BY v.brand, v.model, t.name, t.id'
        );
        $linked = [];
        foreach ($trackers as $tracker) {
            $vehicleId = (int) ($tracker['vehicle_id'] ?? 0);
            if ($vehicleId > 0) {
                $linked[$vehicleId] = true;
            }
            $items[] = $this->item($tracker);
        }
        $vehicles = Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL AND active = 1 ORDER BY brand, model');
        foreach ($vehicles as $vehicle) {
            if (isset($linked[(int) $vehicle['id']])) {
                continue;
            }
            $items[] = [
                'id' => null,
                'vehicle_id' => (int) $vehicle['id'],
                'vehicle' => trim($vehicle['brand'] . ' ' . $vehicle['model']),
                'tag' => null,
                'name' => null,
                'lat' => null,
                'lng' => null,
                'speed' => null,
                'heading' => null,
                'seen' => null,
                'state' => 'sem_tag',
                'trail' => [],
            ];
        }
        usort($items, static fn (array $a, array $b): int => strnatcasecmp((string) $a['vehicle'], (string) $b['vehicle']));
        return $items;
    }

    public function save(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['external_id'] ?? ''));
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Informe um nome para a tag.';
        }
        if ($code === '' || strlen($code) > 80) {
            $errors['external_id'] = 'Informe o código da tag, com até 80 caracteres.';
        }
        if ($vehicleId > 0 && !Connection::one('SELECT id FROM vehicles WHERE id = ? AND deleted_at IS NULL', [$vehicleId])) {
            $errors['vehicle_id'] = 'Veículo não encontrado.';
        }
        $taken = $code === '' ? null : Connection::one('SELECT id FROM trackers WHERE external_id = ?', [$code]);
        if ($taken) {
            $errors['external_id'] = 'Já existe uma tag com este código.';
        }
        if ($vehicleId > 0 && Connection::one('SELECT id FROM trackers WHERE vehicle_id = ?', [$vehicleId])) {
            $errors['vehicle_id'] = 'Este veículo já tem uma tag.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $stamp = now();
        $id = Connection::insert(
            'INSERT INTO trackers (vehicle_id, name, external_id, active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)',
            [$vehicleId > 0 ? $vehicleId : null, $name, $code, $stamp, $stamp]
        );
        audit('create', 'tracker', $id, ['code' => $code]);
        return $id;
    }

    public function delete(int $id): void
    {
        $tracker = Connection::one('SELECT id FROM trackers WHERE id = ?', [$id]);
        if (!$tracker) {
            throw new NotFoundException();
        }
        Connection::run('DELETE FROM trackers WHERE id = ?', [$id]);
        audit('delete', 'tracker', $id);
    }

    public function ingest(array $payload): array
    {
        $rows = $this->rows($payload);
        if ($rows === []) {
            return ['accepted' => 0];
        }
        if (count($rows) > 100) {
            $rows = array_slice($rows, 0, 100);
        }
        $accepted = 0;
        Connection::transaction(function () use ($rows, &$accepted): void {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = $this->text($row, ['tag', 'device', 'device_id', 'imei', 'uniqueId', 'tracker', 'id']);
                $lat = $this->number($row, ['lat', 'latitude', 'y']);
                $lng = $this->number($row, ['lng', 'lon', 'long', 'longitude', 'x']);
                if ($code === null || $lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    continue;
                }
                $speed = $this->number($row, ['speed', 'speed_kmh', 'spd', 'velocity']);
                $heading = $this->number($row, ['heading', 'course', 'bearing', 'direction']);
                if ($heading !== null) {
                    $heading = fmod($heading, 360);
                    if ($heading < 0) {
                        $heading += 360;
                    }
                }
                $recorded = $this->moment($row);
                $trackerId = $this->trackerId($code);
                Connection::insert(
                    'INSERT INTO tracker_positions (tracker_id, latitude, longitude, speed_kmh, heading, recorded_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$trackerId, $lat, $lng, $speed, $heading, $recorded]
                );
                Connection::run(
                    'UPDATE trackers SET last_lat = ?, last_lng = ?, last_speed = ?, last_heading = ?, last_seen_at = ?, updated_at = ? WHERE id = ?',
                    [$lat, $lng, $speed, $heading, $recorded, now(), $trackerId]
                );
                $accepted++;
            }
            Connection::run('DELETE FROM tracker_positions WHERE recorded_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
        });
        return ['accepted' => $accepted];
    }

    private function item(array $tracker): array
    {
        $vehicle = trim((string) (($tracker['brand'] ?? '') . ' ' . ($tracker['model'] ?? '')));
        $seenAt = (string) ($tracker['last_seen_at'] ?? '');
        $lat = $tracker['last_lat'] !== null ? (float) $tracker['last_lat'] : null;
        $fresh = $seenAt !== '' && strtotime($seenAt) !== false && strtotime($seenAt) >= time() - 180;
        $speed = $tracker['last_speed'] !== null ? (float) $tracker['last_speed'] : null;
        $state = 'sem_sinal';
        if ($lat === null) {
            $state = 'sem_sinal';
        } elseif (!$fresh) {
            $state = 'sem_sinal';
        } elseif ($speed !== null && $speed >= 5) {
            $state = 'movendo';
        } else {
            $state = 'parado';
        }
        $trail = [];
        if ($lat !== null) {
            $points = Connection::all(
                'SELECT latitude, longitude FROM tracker_positions WHERE tracker_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 60',
                [(int) $tracker['id']]
            );
            foreach (array_reverse($points) as $point) {
                $trail[] = [(float) $point['latitude'], (float) $point['longitude']];
            }
        }
        return [
            'id' => (int) $tracker['id'],
            'vehicle_id' => (int) ($tracker['vehicle_id'] ?? 0) ?: null,
            'vehicle' => $vehicle !== '' ? $vehicle : (string) $tracker['name'],
            'tag' => (string) $tracker['external_id'],
            'name' => (string) $tracker['name'],
            'lat' => $lat,
            'lng' => $tracker['last_lng'] !== null ? (float) $tracker['last_lng'] : null,
            'speed' => $speed,
            'heading' => $tracker['last_heading'] !== null ? (float) $tracker['last_heading'] : null,
            'seen' => $seenAt !== '' ? date_br($seenAt) : null,
            'state' => $state,
            'trail' => $trail,
        ];
    }

    private function trackerId(string $code): int
    {
        $existing = Connection::value('SELECT id FROM trackers WHERE external_id = ?', [$code]);
        if ($existing) {
            return (int) $existing;
        }
        $stamp = now();
        $label = 'Tag ' . $code;
        if (strlen($label) > 120) {
            $label = substr($label, 0, 120);
        }
        return Connection::insert(
            'INSERT INTO trackers (vehicle_id, name, external_id, active, created_at, updated_at) VALUES (NULL, ?, ?, 1, ?, ?)',
            [$label, $code, $stamp, $stamp]
        );
    }

    private function rows(array $payload): array
    {
        if ($payload === []) {
            return [];
        }
        if (array_is_list($payload)) {
            return $payload;
        }
        foreach (['positions', 'points', 'data', 'locations'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $nested = $payload[$key];
                return array_is_list($nested) ? $nested : [$nested];
            }
        }
        return [$payload];
    }

    private function text(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row) || is_array($row[$key])) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return substr($value, 0, 80);
            }
        }
        return null;
    }

    private function number(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row) || is_array($row[$key]) || !is_numeric($row[$key])) {
                continue;
            }
            return (float) $row[$key];
        }
        return null;
    }

    private function moment(array $row): string
    {
        $raw = $this->text($row, ['at', 'time', 'timestamp', 'recorded_at', 'fixtime', 'deviceTime', 'gps_time']);
        if ($raw === null) {
            return now();
        }
        if (ctype_digit($raw)) {
            $unix = strlen($raw) > 10 ? (int) substr($raw, 0, 10) : (int) $raw;
            return date('Y-m-d H:i:s', $unix);
        }
        $unix = strtotime($raw);
        return $unix === false ? now() : date('Y-m-d H:i:s', $unix);
    }
}
