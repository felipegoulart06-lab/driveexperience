<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Security\Crypto;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class IntegrationService
{
    public function __construct(private ReservationService $reservations = new ReservationService())
    {
    }

    public function receive(string $slug, string $rawBody, string $signature): array
    {
        $platform = Connection::one('SELECT * FROM platforms WHERE slug = ? AND active = 1', [$slug]);
        if (!$platform) {
            return ['status' => 404, 'body' => ['error' => 'Plataforma não encontrada.']];
        }
        $logId = Connection::insert(
            'INSERT INTO sync_logs (platform_id, direction, status, attempts, created_at) VALUES (?, ?, ?, 1, ?)',
            [(int) $platform['id'], 'entrada', 'recebido', now()]
        );
        if (($platform['connector'] ?? '') !== 'generico' || ($platform['status'] ?? '') !== 'configurado' || empty($platform['secret_encrypted'])) {
            $this->finish($logId, 'erro', 'Conector pendente de configuração. Nenhuma venda foi registrada.', 503, null, null);
            return ['status' => 503, 'body' => ['error' => 'Conector pendente de configuração.']];
        }
        try {
            $secret = Crypto::decrypt((string) $platform['secret_encrypted']);
        } catch (\Throwable) {
            $this->finish($logId, 'erro', 'Não foi possível ler o segredo da plataforma.', 503, null, null);
            return ['status' => 503, 'body' => ['error' => 'Segredo da integração indisponível.']];
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $given = $signature;
        if (str_starts_with($given, 'sha256=')) {
            $given = substr($given, 7);
        }
        if ($given === '' || !hash_equals($expected, $given)) {
            $this->finish($logId, 'erro', 'Assinatura inválida.', 401, null, null);
            return ['status' => 401, 'body' => ['error' => 'Assinatura inválida.']];
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->finish($logId, 'erro', 'JSON inválido.', 422, null, null);
            return ['status' => 422, 'body' => ['error' => 'JSON inválido.']];
        }
        $draft = $this->normalize($platform, $payload);
        Connection::run('UPDATE sync_logs SET external_id = ?, payload_json = ? WHERE id = ?', [$draft['external_id'], json_encode($payload, JSON_UNESCAPED_UNICODE), $logId]);
        $result = $this->reservations->recordExternal($draft, 0, false);
        if ($result['status'] === 'created') {
            Connection::run('UPDATE platforms SET last_sync_at = ? WHERE id = ?', [now(), (int) $platform['id']]);
            $this->finish($logId, 'processado', $result['message'] ?? 'Venda registrada.', 201, $result['id'] ?? null, $draft['external_id']);
            return ['status' => 201, 'body' => ['status' => 'created', 'code' => $result['code'] ?? null]];
        }
        if ($result['status'] === 'duplicate') {
            $this->finish($logId, 'duplicado', 'Venda já existente.', 200, $result['id'] ?? null, $draft['external_id']);
            return ['status' => 200, 'body' => ['status' => 'duplicate', 'code' => $result['code'] ?? null]];
        }
        $this->finish($logId, 'erro', (string) ($result['message'] ?? 'Falha ao registrar.'), 422, null, $draft['external_id']);
        return ['status' => 422, 'body' => ['error' => $result['message'] ?? 'Não foi possível registrar a venda.']];
    }

    public function reprocess(int $logId, int $userId): array
    {
        $log = Connection::one('SELECT * FROM sync_logs WHERE id = ?', [$logId]);
        if (!$log) {
            throw new NotFoundException('Sincronização não encontrada.');
        }
        $payload = json_decode((string) $log['payload_json'], true);
        if (!is_array($payload)) {
            throw new ValidationException(['sync' => 'Não há payload armazenado para reprocessar.']);
        }
        $platform = Connection::one('SELECT * FROM platforms WHERE id = ?', [(int) $log['platform_id']]);
        $result = $this->reservations->recordExternal($this->normalize($platform, $payload), $userId, false);
        Connection::run('UPDATE sync_logs SET attempts = attempts + 1, status = ?, message = ?, processed_at = ?, reservation_id = ? WHERE id = ?', [
            $result['status'] === 'created' ? 'processado' : ($result['status'] === 'duplicate' ? 'duplicado' : 'erro'),
            $result['message'] ?? ($result['code'] ?? ''),
            now(),
            $result['id'] ?? null,
            $logId,
        ]);
        return $result;
    }

    public function savePlatform(array $data, ?int $id, int $userId): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = fold((string) ($data['slug'] ?? $name));
        $slug = str_replace(' ', '-', $slug);
        if ($name === '' || $slug === '') {
            throw new ValidationException(['name' => 'Informe o nome da plataforma.']);
        }
        $connector = (string) ($data['connector'] ?? 'pendente');
        if (!in_array($connector, ['generico', 'pendente'], true)) {
            throw new ValidationException(['connector' => 'Conector ainda não implementado. Não há documentação suficiente para ativá-lo.']);
        }
        $secret = trim((string) ($data['webhook_secret'] ?? ''));
        $status = $connector === 'generico' && $secret !== '' ? 'configurado' : 'pendente';
        $encrypted = null;
        if ($secret !== '') {
            $encrypted = Crypto::encrypt($secret);
        }
        $stamp = now();
        if ($id) {
            $current = Connection::one('SELECT * FROM platforms WHERE id = ?', [$id]);
            if (!$current) {
                throw new NotFoundException('Plataforma não encontrada.');
            }
            Connection::run(
                'UPDATE platforms SET name = ?, slug = ?, connector = ?, status = ?, secret_encrypted = COALESCE(?, secret_encrypted), trust_payment = ?, notes = ?, active = ?, updated_at = ? WHERE id = ?',
                [$name, $slug, $connector, $secret !== '' ? $status : ($current['status'] ?: 'pendente'), $encrypted, !empty($data['trust_payment']) ? 1 : 0, blank_to_null((string) ($data['notes'] ?? '')), !empty($data['active']) ? 1 : 0, $stamp, $id]
            );
            audit('update', 'platform', $id, ['slug' => $slug]);
            return $id;
        }
        $newId = Connection::insert(
            'INSERT INTO platforms (name, slug, status, connector, secret_encrypted, notes, active, trust_payment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$name, $slug, $status, $connector, $encrypted, blank_to_null((string) ($data['notes'] ?? '')), 1, !empty($data['trust_payment']) ? 1 : 0, $stamp, $stamp]
        );
        audit('create', 'platform', $newId, ['slug' => $slug]);
        return $newId;
    }

    private function normalize(array $platform, array $payload): array
    {
        $customer = (array) ($payload['customer'] ?? []);
        return [
            'platform_id' => (int) $platform['id'],
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'external_reservation_code' => (string) ($payload['external_reservation_code'] ?? ''),
            'customer' => [
                'name' => (string) ($customer['name'] ?? ''),
                'email' => (string) ($customer['email'] ?? ''),
                'phone' => (string) ($customer['phone'] ?? ''),
                'cpf' => (string) ($customer['document'] ?? ''),
                'city' => (string) ($customer['city'] ?? ''),
                'state' => (string) ($customer['state'] ?? ''),
            ],
            'product' => (string) ($payload['product_code'] ?? $payload['product_name'] ?? ''),
            'vehicle' => (string) ($payload['vehicle_code'] ?? $payload['vehicle_name'] ?? ''),
            'scheduled_date' => (string) ($payload['scheduled_date'] ?? ''),
            'start_time' => (string) ($payload['start_time'] ?? ''),
            'participants' => (int) ($payload['participants'] ?? 1),
            'gross_cents' => (int) round(((float) ($payload['gross_amount'] ?? 0)) * 100),
            'fee_cents' => (int) round(((float) ($payload['fee_amount'] ?? 0)) * 100),
            'net_cents' => (int) round(((float) ($payload['net_amount'] ?? 0)) * 100),
            'reported_paid_cents' => (int) round(((float) ($payload['paid_amount'] ?? 0)) * 100),
            'notes' => (string) ($payload['notes'] ?? ''),
        ];
    }

    private function finish(int $id, string $status, string $message, int $http, ?int $reservationId, ?string $externalId): void
    {
        Connection::run(
            'UPDATE sync_logs SET status = ?, message = ?, http_status = ?, reservation_id = ?, external_id = COALESCE(?, external_id), processed_at = ? WHERE id = ?',
            [$status, $message, $http, $reservationId, $externalId, now(), $id]
        );
    }
}
