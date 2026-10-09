<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Http\Request;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class InboundWebhookService
{
    public function __construct(private ReservationService $reservations = new ReservationService())
    {
    }

    public function fields(): array
    {
        return [
            'Cliente' => [
                'nome' => 'Nome',
                'sobrenome' => 'Sobrenome',
                'cpf' => 'CPF',
                'telefone' => 'Telefone',
                'email' => 'E-mail',
                'cidade' => 'Cidade',
                'estado' => 'Estado',
            ],
            'Reserva' => [
                'veiculo' => 'Veículo',
                'experiencia' => 'Experiência',
                'data' => 'Data',
                'horario' => 'Horário',
                'participantes' => 'Participantes',
                'codigo_externo' => 'Código do pedido',
                'valor' => 'Valor',
                'valor_pago' => 'Valor pago',
                'forma_pagamento' => 'Forma de pagamento',
                'endereco' => 'Endereço',
                'numero' => 'Número',
                'complemento' => 'Complemento',
                'bairro' => 'Bairro',
                'cep' => 'CEP',
                'observacoes' => 'Observações',
            ],
        ];
    }

    public function list(): array
    {
        return Connection::all('SELECT * FROM inbound_webhooks ORDER BY id DESC');
    }

    public function find(int $id): array
    {
        $row = Connection::one('SELECT * FROM inbound_webhooks WHERE id = ?', [$id]);
        if (!$row) {
            throw new NotFoundException();
        }
        return $row;
    }

    public function create(string $name, string $domains): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Informe o nome do webhook.']);
        }
        $token = bin2hex(random_bytes(24));
        $stamp = now();
        $slug = 'site-' . substr($token, 0, 12);
        $platformId = Connection::insert(
            'INSERT INTO platforms (name, slug, status, connector, notes, active, trust_payment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?)',
            [$name, $slug, 'configurado', 'generico', 'Webhook de formulário do site.', $stamp, $stamp]
        );
        return Connection::insert(
            'INSERT INTO inbound_webhooks (platform_id, name, token, active, test_mode, allowed_domains, mapping_json, created_at, updated_at) VALUES (?, ?, ?, 0, 1, ?, ?, ?, ?)',
            [$platformId, $name, $token, $this->domains($domains), '{}', $stamp, $stamp]
        );
    }

    public function update(int $id, array $data): void
    {
        $hook = $this->find($id);
        $active = array_key_exists('active', $data) ? (!empty($data['active']) ? 1 : 0) : (int) $hook['active'];
        $test = array_key_exists('test_mode', $data) ? (!empty($data['test_mode']) ? 1 : 0) : (int) $hook['test_mode'];
        $domains = array_key_exists('allowed_domains', $data) ? $this->domains((string) $data['allowed_domains']) : (string) ($hook['allowed_domains'] ?? '');
        $mapping = (string) ($hook['mapping_json'] ?? '{}');
        if (isset($data['map']) && is_array($data['map'])) {
            $clean = [];
            $allowed = [];
            foreach ($this->fields() as $group) {
                foreach ($group as $key => $label) {
                    $allowed[$key] = true;
                }
            }
            foreach ($data['map'] as $key => $path) {
                $key = (string) $key;
                $path = trim((string) $path);
                if ($path !== '' && isset($allowed[$key]) && preg_match('/^[A-Za-z0-9_.-]{1,180}$/', $path)) {
                    $clean[$key] = $path;
                }
            }
            $mapping = json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '{}';
        }
        Connection::run(
            'UPDATE inbound_webhooks SET active = ?, test_mode = ?, allowed_domains = ?, mapping_json = ?, updated_at = ? WHERE id = ?',
            [$active, $test, $domains, $mapping, now(), $id]
        );
        Connection::run('UPDATE platforms SET active = ?, updated_at = ? WHERE id = ?', [$active, now(), (int) $hook['platform_id']]);
    }

    public function delete(int $id): void
    {
        $hook = $this->find($id);
        Connection::run('DELETE FROM inbound_webhooks WHERE id = ?', [$id]);
        $used = (int) Connection::value('SELECT COUNT(*) FROM reservations WHERE platform_id = ?', [(int) $hook['platform_id']]);
        if ($used === 0) {
            Connection::run('DELETE FROM sync_logs WHERE platform_id = ?', [(int) $hook['platform_id']]);
            Connection::run('DELETE FROM platforms WHERE id = ?', [(int) $hook['platform_id']]);
        } else {
            Connection::run('UPDATE platforms SET active = 0, updated_at = ? WHERE id = ?', [now(), (int) $hook['platform_id']]);
        }
    }

    public function samples(int $id): array
    {
        $hook = $this->find($id);
        $rows = Connection::all('SELECT id, payload_json, source_host, created_at FROM webhook_captures WHERE webhook_id = ? ORDER BY id DESC LIMIT 15', [$id]);
        $out = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) $row['payload_json'], true);
            $payload = is_array($payload) ? $payload : [];
            $fields = $this->flatten($payload);
            $preview = [];
            foreach ($fields as $path => $value) {
                $preview[$path] = mb_substr($value, 0, 160);
                if (count($preview) >= 250) {
                    break;
                }
            }
            $out[] = [
                'id' => (int) $row['id'],
                'at' => date_br((string) $row['created_at']),
                'host' => (string) ($row['source_host'] ?? ''),
                'fields' => $preview,
                'preview' => $this->explain($hook, $payload),
            ];
        }
        return $out;
    }

    public function receive(string $token, Request $request): array
    {
        $hook = Connection::one('SELECT * FROM inbound_webhooks WHERE token = ?', [$token]);
        if (!$hook) {
            return ['status' => 404, 'body' => ['error' => 'Webhook não encontrado.']];
        }
        if (!(int) $hook['active'] && !(int) $hook['test_mode']) {
            return ['status' => 404, 'body' => ['error' => 'Webhook desativado.']];
        }
        $host = $this->sourceHost($request);
        if (!$this->hostAllowed((string) ($hook['allowed_domains'] ?? ''), $host)) {
            return ['status' => 403, 'body' => ['error' => 'Domínio não autorizado.']];
        }
        $payload = $this->payload($request);
        if ($payload === null) {
            return ['status' => 422, 'body' => ['error' => 'Envie JSON ou formulário.']];
        }
        if ((int) $hook['test_mode'] === 1) {
            $this->storeCapture((int) $hook['id'], $payload, $host);
            return ['status' => 200, 'body' => ['status' => 'teste', 'message' => 'Recebido em modo teste. Nenhuma reserva foi criada.']];
        }
        return $this->commit($hook, $payload, 0);
    }

    public function importCapture(int $id, int $captureId, int $userId): array
    {
        $hook = $this->find($id);
        $row = Connection::one('SELECT payload_json FROM webhook_captures WHERE id = ? AND webhook_id = ?', [$captureId, $id]);
        if (!$row) {
            return ['ok' => false, 'message' => 'Escolha um teste recebido.'];
        }
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            return ['ok' => false, 'message' => 'Escolha um teste recebido.'];
        }
        $result = $this->commit($hook, $payload, $userId);
        $body = $result['body'];
        if (($body['status'] ?? '') === 'created') {
            return ['ok' => true, 'message' => 'Reserva ' . ($body['code'] ?? '') . ' criada a partir do teste.'];
        }
        if (($body['status'] ?? '') === 'duplicate') {
            return ['ok' => true, 'message' => 'Este pedido já está na reserva ' . ($body['code'] ?? '') . '.'];
        }
        return ['ok' => false, 'message' => (string) ($body['error'] ?? 'Não foi possível criar a reserva.')];
    }

    public function explain(array $hook, array $payload): array
    {
        $draft = $this->draft($hook, $this->apply((string) ($hook['mapping_json'] ?? '{}'), $payload));
        $result = $this->reservations->recordExternal($draft, 0, true);
        $when = ($draft['scheduled_date'] ?? '') !== '' && ($draft['start_time'] ?? '') !== ''
            ? date_br((string) $draft['scheduled_date']) . ' ' . $draft['start_time']
            : '';
        $summary = implode(', ', array_filter([
            (string) ($draft['customer']['name'] ?? ''),
            (string) ($draft['product'] ?? ''),
            (string) ($draft['vehicle'] ?? ''),
            $when,
            money((int) ($draft['gross_cents'] ?? 0)),
        ]));
        $status = (string) ($result['status'] ?? 'error');
        if ($status === 'ready') {
            return ['status' => 'ready', 'text' => 'Pronto para criar: ' . $summary . '.'];
        }
        if ($status === 'duplicate') {
            return ['status' => 'duplicate', 'text' => 'Este pedido já entrou como ' . ($result['code'] ?? 'reserva existente') . '.'];
        }
        return ['status' => 'error', 'text' => (string) ($result['message'] ?? 'Não foi possível ler este teste.')];
    }

    public function url(array $hook): string
    {
        $https = is_https();
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host . '/api/webhooks/entrada/' . $hook['token'];
    }

    public function mapping(array $hook): array
    {
        $decoded = json_decode((string) ($hook['mapping_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function commit(array $hook, array $payload, int $userId): array
    {
        $draft = $this->draft($hook, $this->apply((string) ($hook['mapping_json'] ?? '{}'), $payload));
        $logId = Connection::insert(
            'INSERT INTO sync_logs (platform_id, direction, external_id, payload_json, status, attempts, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [(int) $hook['platform_id'], 'entrada', $draft['external_id'], json_encode($payload, JSON_UNESCAPED_UNICODE), 'recebido', now()]
        );
        $result = $this->reservations->recordExternal($draft, $userId, false);
        $status = (string) ($result['status'] ?? 'error');
        Connection::run(
            'UPDATE sync_logs SET status = ?, message = ?, http_status = ?, reservation_id = ?, processed_at = ? WHERE id = ?',
            [
                $status === 'created' ? 'processado' : ($status === 'duplicate' ? 'duplicado' : 'erro'),
                (string) ($result['message'] ?? $result['code'] ?? $status),
                $status === 'created' ? 201 : ($status === 'duplicate' ? 200 : 422),
                $result['id'] ?? null,
                now(),
                $logId,
            ]
        );
        if ($status === 'created') {
            Connection::run('UPDATE platforms SET last_sync_at = ? WHERE id = ?', [now(), (int) $hook['platform_id']]);
            return ['status' => 201, 'body' => ['status' => 'created', 'code' => $result['code'] ?? null]];
        }
        if ($status === 'duplicate') {
            return ['status' => 200, 'body' => ['status' => 'duplicate', 'code' => $result['code'] ?? null]];
        }
        return ['status' => 422, 'body' => ['error' => (string) ($result['message'] ?? 'Não foi possível registrar a reserva.')]];
    }

    private function draft(array $hook, array $mapped): array
    {
        $name = trim(trim((string) ($mapped['nome'] ?? '')) . ' ' . trim((string) ($mapped['sobrenome'] ?? '')));
        $notes = array_filter([
            trim((string) ($mapped['observacoes'] ?? '')),
            $this->line('Endereço', trim(implode(', ', array_filter([
                trim((string) ($mapped['endereco'] ?? '')),
                trim((string) ($mapped['numero'] ?? '')),
                trim((string) ($mapped['complemento'] ?? '')),
                trim((string) ($mapped['bairro'] ?? '')),
                trim((string) ($mapped['cep'] ?? '')),
            ])))),
            $this->line('Pagamento', trim((string) ($mapped['forma_pagamento'] ?? ''))),
        ]);
        $paid = $this->cents((string) ($mapped['valor_pago'] ?? ''));
        $gross = $this->cents((string) ($mapped['valor'] ?? ''));
        return [
            'platform_id' => (int) $hook['platform_id'],
            'external_id' => trim((string) ($mapped['codigo_externo'] ?? '')),
            'external_reservation_code' => trim((string) ($mapped['codigo_externo'] ?? '')),
            'customer' => [
                'name' => $name,
                'email' => trim((string) ($mapped['email'] ?? '')),
                'phone' => trim((string) ($mapped['telefone'] ?? '')),
                'cpf' => trim((string) ($mapped['cpf'] ?? '')),
                'city' => trim((string) ($mapped['cidade'] ?? '')),
                'state' => trim((string) ($mapped['estado'] ?? '')),
            ],
            'product' => trim((string) ($mapped['experiencia'] ?? '')),
            'vehicle' => trim((string) ($mapped['veiculo'] ?? '')),
            'scheduled_date' => $this->date((string) ($mapped['data'] ?? '')),
            'start_time' => (string) (parse_time($mapped['horario'] ?? '') ?? ''),
            'participants' => max(1, (int) ($mapped['participantes'] ?? 1)),
            'gross_cents' => $gross ?? 0,
            'fee_cents' => 0,
            'net_cents' => $paid ?? 0,
            'reported_paid_cents' => $paid ?? 0,
            'notes' => implode("\n", $notes),
        ];
    }

    private function apply(string $json, array $payload): array
    {
        $map = json_decode($json, true);
        if (!is_array($map)) {
            return [];
        }
        $flat = $this->flatten($payload);
        $out = [];
        foreach ($map as $key => $path) {
            $path = (string) $path;
            $out[(string) $key] = $flat[$path] ?? '';
        }
        return $out;
    }

    private function payload(Request $request): ?array
    {
        $json = $request->json();
        if ($json !== []) {
            return $json;
        }
        $post = $request->all();
        unset($post['_csrf']);
        return $post === [] ? null : $post;
    }

    private function storeCapture(int $id, array $payload, string $host): void
    {
        Connection::insert(
            'INSERT INTO webhook_captures (webhook_id, payload_json, source_host, created_at) VALUES (?, ?, ?, ?)',
            [$id, json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}', $host !== '' ? $host : null, now()]
        );
        $keep = array_column(Connection::all('SELECT id FROM webhook_captures WHERE webhook_id = ? ORDER BY id DESC LIMIT 15', [$id]), 'id');
        if ($keep !== []) {
            $marks = implode(',', array_fill(0, count($keep), '?'));
            Connection::run('DELETE FROM webhook_captures WHERE webhook_id = ? AND id NOT IN (' . $marks . ')', [$id, ...$keep]);
        }
    }

    private function flatten(array $data, string $prefix = '', int $depth = 0): array
    {
        $out = [];
        if ($depth > 6) {
            return $out;
        }
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (!preg_match('/^[A-Za-z0-9_.-]{1,180}$/', $path)) {
                continue;
            }
            if (is_array($value)) {
                $out += $this->flatten($value, $path, $depth + 1);
                continue;
            }
            if (is_bool($value)) {
                $out[$path] = $value ? '1' : '0';
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$path] = trim((string) $value);
            }
        }
        return $out;
    }

    private function domains(string $raw): string
    {
        $hosts = [];
        foreach (preg_split('/[\s,;]+/', strtolower($raw)) ?: [] as $piece) {
            $host = $this->host($piece);
            if ($host !== '') {
                $hosts[$host] = $host;
            }
        }
        return implode("\n", $hosts);
    }

    private function hostAllowed(string $stored, string $source): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return true;
        }
        if ($source === '') {
            return true;
        }
        $allowed = array_filter(array_map(fn (string $item): string => $this->host($item), explode("\n", $stored)));
        return in_array($source, $allowed, true);
    }

    private function sourceHost(Request $request): string
    {
        foreach (['Origin', 'Referer', 'X-WC-Webhook-Source'] as $header) {
            $host = $this->host($request->header($header));
            if ($host !== '') {
                return $host;
            }
        }
        return '';
    }

    private function host(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (!str_contains($value, '://')) {
            $value = 'https://' . $value;
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : '';
    }

    private function cents(string $value): ?int
    {
        $value = trim(str_ireplace(['r$', ' '], '', $value));
        if ($value === '') {
            return null;
        }
        return parse_money($value);
    }

    private function date(string $value): string
    {
        $parsed = parse_date($value);
        if ($parsed) {
            return $parsed;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s]/', $value, $matches)) {
            return parse_date($matches[1]) ?? '';
        }
        $months = [
            'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'março' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
            'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];
        $text = mb_strtolower(trim($value));
        if (preg_match('/([a-zç]+)\s+(\d{1,2}),?\s+(\d{4})/u', $text, $matches) && isset($months[$matches[1]])) {
            $month = $months[$matches[1]];
            $day = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        if (preg_match('/(\d{1,2})\s+de\s+([a-zç]+)\s+de\s+(\d{4})/u', $text, $matches) && isset($months[$matches[2]])) {
            $month = $months[$matches[2]];
            $day = (int) $matches[1];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        return '';
    }

    private function line(string $label, string $value): string
    {
        return $value === '' ? '' : $label . ': ' . $value;
    }
}
