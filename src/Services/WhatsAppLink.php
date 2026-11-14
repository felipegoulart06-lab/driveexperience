<?php

declare(strict_types=1);

namespace App\Services;

use App\Security\Crypto;
use RuntimeException;

final class WhatsAppLink
{
    public function ready(): bool
    {
        return $this->instanceBase() !== '' && $this->instanceToken() !== '' && $this->storedStatus() === 'conectado';
    }

    public function credentials(): array
    {
        return ['base' => $this->instanceBase(), 'token' => $this->instanceToken()];
    }

    public function deliveryNumber(string $digits): string
    {
        [$ok, , $message, $json] = $this->request('POST', '/chat/check', ['numbers' => [$digits]], 20);
        $row = $ok && is_array($json) && isset($json[0]) && is_array($json[0]) ? $json[0] : null;
        if (!$row || empty($row['isInWhatsapp'])) {
            throw new RuntimeException($message !== '' ? $message : 'Este número não está no WhatsApp.');
        }
        $jid = preg_replace('/\D/', '', explode('@', (string) ($row['jid'] ?? ''))[0] ?? '');
        if ($jid === '') {
            throw new RuntimeException('Este número não está no WhatsApp.');
        }
        $owner = $this->ownerNumber();
        if ($owner !== '' && $owner === $jid) {
            throw new RuntimeException('O WhatsApp do cliente é o mesmo aparelho conectado em Configurações. O WhatsApp não entrega uma mensagem para o próprio número.');
        }
        return $jid;
    }

    private function ownerNumber(): string
    {
        [$ok, , , $json] = $this->request('GET', '/instance/status', null, 15);
        if (!$ok || !is_array($json)) {
            return '';
        }
        $instance = $json['instance'] ?? null;
        $owner = is_array($instance) ? (string) ($instance['owner'] ?? '') : '';
        return preg_replace('/\D/', '', $owner) ?? '';
    }

    public function view(): array
    {
        $status = $this->storedStatus();
        $qr = $this->visibleQr($status, (string) Settings::get('wa_qr', ''));
        return $this->present($status, (string) Settings::get('wa_profile', ''), $qr, '');
    }

    public function refresh(): array
    {
        if ($this->instanceBase() === '' || $this->instanceToken() === '') {
            return $this->present('desconectado', '', '', '');
        }
        [$ok, $code, $message, $json] = $this->request('GET', '/instance/status', null, 12);
        if (!$ok) {
            if ($code === 401 || $code === 404) {
                $this->forgetInstance();
                return $this->present('desconectado', '', '', 'A instância não está mais disponível. Gere o QR Code de novo.');
            }
            return $this->present($this->storedStatus(), (string) Settings::get('wa_profile', ''), $this->visibleQr($this->storedStatus(), (string) Settings::get('wa_qr', '')), $message);
        }
        return $this->saveState($this->extract($json), '');
    }

    public function beginQr(string $otp): array
    {
        TwoFactor::assertCode($otp);
        $current = $this->refresh();
        if (($current['status'] ?? '') === 'conectado') {
            $current['qr'] = '';
            $current['message'] = 'O WhatsApp já está conectado. O QR Code só aparece depois de desconectar.';
            return $current;
        }
        if (($current['status'] ?? '') === 'aguardando' && ($current['qr'] ?? '') !== '') {
            return $current;
        }
        if ($this->instanceBase() === '' || $this->instanceToken() === '') {
            $this->provision();
        }
        [$ok, $code, $message, $json] = $this->request('POST', '/instance/connect', $this->connectBody(), 25);
        if ($code === 409) {
            return $this->refresh();
        }
        if ($code === 401 || $code === 404) {
            $this->forgetInstance();
            $this->provision();
            [$ok, $code, $message, $json] = $this->request('POST', '/instance/connect', $this->connectBody(), 25);
        }
        if (!$ok) {
            throw new RuntimeException($message !== '' ? $message : 'Não foi possível abrir o QR Code.');
        }
        $state = $this->extract($json);
        if ($state['qrcode'] === '' && !$state['connected']) {
            $follow = $this->refresh();
            if (($follow['qr'] ?? '') !== '' || ($follow['status'] ?? '') === 'conectado') {
                return $follow;
            }
        }
        return $this->saveState($state, $state['qrcode'] !== '' ? 'Escaneie o QR Code no WhatsApp: Aparelhos conectados, Conectar aparelho.' : '');
    }

    public function disconnect(string $otp): array
    {
        TwoFactor::assertCode($otp);
        if ($this->instanceBase() === '' || $this->instanceToken() === '') {
            $this->forgetInstance();
            return $this->present('desconectado', '', '', 'WhatsApp desconectado.');
        }
        [$ok, $code, $message] = $this->request('POST', '/instance/disconnect', [], 20);
        if (!$ok && !in_array($code, [404, 409], true)) {
            throw new RuntimeException($message !== '' ? $message : 'Não foi possível desconectar o aparelho.');
        }
        [$deleted, $deleteCode, $deleteMessage] = $this->request('DELETE', '/instance', null, 20);
        if (!$deleted && $deleteCode !== 404) {
            throw new RuntimeException($deleteMessage !== '' ? $deleteMessage : 'O aparelho saiu, mas a instância continuou na API.');
        }
        $this->forgetInstance();
        return $this->present('desconectado', '', '', 'Instância excluída no WhatsApp e aqui.');
    }

    private function connectBody(): array
    {
        return ['browser' => 'auto', 'systemName' => 'DRIVEXPERIENCE TESTE'];
    }

    private function provision(): void
    {
        $admin = trim((string) env('WHATSAPP_ADMIN_TOKEN', ''));
        $endpoint = trim((string) env('WHATSAPP_CREATE_URL', 'https://grlwciflaotripbumhve.supabase.co/functions/v1/create-instance-url'));
        if ($admin === '' || !preg_match('#^https://#i', $endpoint)) {
            throw new RuntimeException('O token permanente da API não está configurado no servidor.');
        }
        $slug = 'drivexperience-' . gmdate('Ymd-His');
        [$ok, , $message, $json] = $this->http($endpoint, 'POST', ['Content-Type: application/json', 'Accept: application/json'], [
            'token' => $admin,
            'name' => $slug,
            'deviceName' => 'DRIVEXPERIENCE TESTE',
            'systemName' => 'DRIVEXPERIENCE TESTE',
        ], 30);
        if (!$ok || !is_array($json)) {
            throw new RuntimeException($message !== '' ? $message : 'A API não criou a instância.');
        }
        $parsed = $this->parseProvision($json);
        if ($parsed['url'] === '' || $parsed['token'] === '') {
            throw new RuntimeException('A API não devolveu o endereço e o token da instância.');
        }
        Settings::set('wa_server_url', $parsed['url']);
        Settings::set('wa_instance_token', Crypto::encrypt($parsed['token']));
        Settings::set('wa_instance_name', $parsed['name'] !== '' ? $parsed['name'] : $slug);
        Settings::set('wa_status', 'desconectado');
        Settings::set('wa_qr', '');
    }

    private function parseProvision(array $json): array
    {
        $out = ['url' => '', 'token' => '', 'name' => ''];
        foreach (['server_url', 'serverUrl', 'base_url', 'baseUrl'] as $key) {
            $value = trim((string) ($json[$key] ?? ''));
            if (preg_match('#^https://#i', $value)) {
                $out['url'] = rtrim($value, '/');
                break;
            }
        }
        foreach (['instance_token', 'instanceToken', 'Instance Token', 'Instance token'] as $key) {
            $value = trim((string) ($json[$key] ?? ''));
            if (strlen($value) > 20) {
                $out['token'] = $value;
                break;
            }
        }
        $flat = trim((string) ($json['token'] ?? ''));
        if ($out['token'] === '' && strlen($flat) > 40) {
            $out['token'] = $flat;
        }
        $instance = $json['instance'] ?? null;
        if (is_array($instance)) {
            $nested = trim((string) ($instance['token'] ?? ''));
            if ($out['token'] === '' && strlen($nested) > 20) {
                $out['token'] = $nested;
            }
            $out['name'] = trim((string) ($instance['name'] ?? ''));
        }
        return $out;
    }

    private function saveState(array $state, string $message): array
    {
        $status = $this->mapStatus($state);
        $qr = $this->visibleQr($status, $state['qrcode']);
        Settings::set('wa_status', $status);
        Settings::set('wa_profile', $state['profile_name']);
        Settings::set('wa_qr', $qr);
        return $this->present($status, $state['profile_name'], $qr, $message);
    }

    private function present(string $status, string $profile, string $qr, string $message): array
    {
        $label = match ($status) {
            'conectado' => 'Conectado',
            'aguardando' => 'Aguardando leitura do QR Code',
            'hibernado' => 'Sessão pausada',
            default => 'Desconectado',
        };
        return [
            'status' => $status,
            'label' => $label,
            'profile' => $profile,
            'qr' => $qr,
            'message' => $message,
            'connected' => $status === 'conectado',
            'two_factor' => TwoFactor::enabled(),
        ];
    }

    private function visibleQr(string $status, string $qr): string
    {
        if ($status === 'conectado' || !TwoFactor::enabled() || trim($qr) === '') {
            return '';
        }
        return $qr;
    }

    private function mapStatus(array $state): string
    {
        if ($state['connected'] || $state['instance_status'] === 'connected') {
            return 'conectado';
        }
        if ($state['instance_status'] === 'hibernated') {
            return 'hibernado';
        }
        if ($state['instance_status'] === 'connecting' || $state['qrcode'] !== '') {
            return 'aguardando';
        }
        return 'desconectado';
    }

    private function extract(?array $json): array
    {
        $state = [
            'connected' => false,
            'instance_status' => 'disconnected',
            'qrcode' => '',
            'profile_name' => '',
        ];
        if ($json === null) {
            return $state;
        }
        if (array_key_exists('connected', $json)) {
            $state['connected'] = !empty($json['connected']);
        }
        if (array_key_exists('loggedIn', $json)) {
            $state['connected'] = $state['connected'] || !empty($json['loggedIn']);
        }
        $status = $json['status'] ?? null;
        if (is_array($status)) {
            if (array_key_exists('connected', $status)) {
                $state['connected'] = !empty($status['connected']);
            }
            if (array_key_exists('loggedIn', $status)) {
                $state['connected'] = $state['connected'] || !empty($status['loggedIn']);
            }
        } elseif (is_string($status) && $status !== '') {
            $state['instance_status'] = strtolower($status);
        }
        $instance = $json['instance'] ?? null;
        if (is_array($instance)) {
            $name = strtolower(trim((string) ($instance['status'] ?? '')));
            if ($name !== '') {
                $state['instance_status'] = $name;
            }
            if ($name === 'connected') {
                $state['connected'] = true;
            }
            $state['profile_name'] = trim((string) ($instance['profileName'] ?? $instance['profile_name'] ?? ''));
            $qr = $this->normalizeQr((string) ($instance['qrcode'] ?? ''));
            if ($qr !== '') {
                $state['qrcode'] = $qr;
            }
        }
        if ($state['qrcode'] === '') {
            $state['qrcode'] = $this->findQr($json);
        }
        if ($state['connected']) {
            $state['instance_status'] = 'connected';
            $state['qrcode'] = '';
        }
        return $state;
    }

    private function findQr(mixed $data, string $key = ''): string
    {
        if (is_string($data)) {
            if (str_starts_with($data, 'data:image')) {
                return $data;
            }
            if (preg_match('/qr/i', $key) && strlen($data) > 40) {
                return $this->normalizeQr($data);
            }
            return '';
        }
        if (!is_array($data)) {
            return '';
        }
        foreach ($data as $childKey => $value) {
            $found = $this->findQr($value, is_string($childKey) ? $childKey : $key);
            if ($found !== '') {
                return $found;
            }
        }
        return '';
    }

    private function normalizeQr(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, 'data:image') || preg_match('#^https://#i', $raw)) {
            return $raw;
        }
        return 'data:image/png;base64,' . preg_replace('/\s+/', '', $raw);
    }

    private function forgetInstance(): void
    {
        Settings::set('wa_server_url', '');
        Settings::set('wa_instance_token', '');
        Settings::set('wa_instance_name', '');
        Settings::set('wa_status', 'desconectado');
        Settings::set('wa_qr', '');
        Settings::set('wa_profile', '');
    }

    private function storedStatus(): string
    {
        $status = (string) Settings::get('wa_status', 'desconectado');
        return in_array($status, ['conectado', 'aguardando', 'hibernado', 'desconectado'], true) ? $status : 'desconectado';
    }

    private function instanceBase(): string
    {
        return rtrim(trim((string) Settings::get('wa_server_url', '')), '/');
    }

    private function instanceToken(): string
    {
        $stored = trim((string) Settings::get('wa_instance_token', ''));
        if ($stored === '') {
            return '';
        }
        try {
            return Crypto::decrypt($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    private function request(string $method, string $path, ?array $body, int $timeout): array
    {
        $base = $this->instanceBase();
        $token = $this->instanceToken();
        if (!preg_match('#^https://#i', $base) || $token === '') {
            return [false, 0, 'A instância ainda não foi criada.', null];
        }
        return $this->http($base . $path, $method, ['Accept: application/json', 'token: ' . $token], $body, $timeout);
    }

    private function http(string $url, string $method, array $headers, ?array $body, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return [false, 0, 'O PHP deste computador não tem cURL.', null];
        }
        $channel = curl_init($url);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($channel, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_apply_ca($channel);
        $raw = curl_exec($channel);
        $code = (int) curl_getinfo($channel, CURLINFO_RESPONSE_CODE);
        $error = curl_error($channel);
        curl_close($channel);
        if ($raw === false) {
            return [false, $code, $error !== '' ? 'Sem resposta da API de WhatsApp.' : 'Sem resposta da API de WhatsApp.', null];
        }
        $json = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300) {
            return [true, $code, '', is_array($json) ? $json : []];
        }
        $text = '';
        if (is_array($json)) {
            foreach (['message_ptbr', 'error', 'message', 'response'] as $key) {
                $value = $json[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $text = trim($value);
                    break;
                }
            }
        }
        if ($text === '') {
            $text = match ($code) {
                401 => 'O token da instância foi recusado.',
                404 => 'Instância não encontrada.',
                409 => 'Já existe uma conexão em andamento.',
                429 => 'A API atingiu o limite de chamadas. Aguarde e tente de novo.',
                default => 'A API de WhatsApp recusou a operação.',
            };
        }
        return [false, $code, $text, is_array($json) ? $json : null];
    }
}
