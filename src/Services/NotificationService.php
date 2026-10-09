<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class NotificationService
{
    public function queue(string $channel, string $recipient, string $template, array $payload, ?string $relatedType = null, ?int $relatedId = null): int
    {
        $status = $this->configured($channel) ? 'pendente' : 'nao_configurado';
        return Connection::insert(
            'INSERT INTO notifications (channel, recipient, template, payload_json, status, related_type, related_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$channel, $recipient, $template, json_encode($payload, JSON_UNESCAPED_UNICODE), $status, $relatedType, $relatedId, now()]
        );
    }

    public function configured(string $channel): bool
    {
        if ($channel === 'email') {
            return env('SMTP_HOST') !== null && env('SMTP_FROM') !== null;
        }
        if ($channel === 'whatsapp') {
            return $this->whatsappBase() !== '' && env('WHATSAPP_TOKEN') !== null;
        }
        return false;
    }

    public function flush(int $limit = 10): int
    {
        $rows = Connection::all("SELECT * FROM notifications WHERE status = 'pendente' ORDER BY id LIMIT " . max(1, $limit));
        $sent = 0;
        foreach ($rows as $row) {
            try {
                $payload = json_decode((string) $row['payload_json'], true) ?: [];
                if ($row['channel'] === 'email') {
                    $this->sendMail((string) $row['recipient'], (string) ($payload['subject'] ?? 'Drive Xperience'), (string) ($payload['body'] ?? ''));
                } elseif ($row['channel'] === 'whatsapp') {
                    $this->sendWhatsapp((string) $row['recipient'], $payload);
                } else {
                    throw new \RuntimeException('Canal desconhecido.');
                }
                Connection::run('UPDATE notifications SET status = ?, sent_at = ?, error = NULL WHERE id = ?', ['enviada', now(), (int) $row['id']]);
                $sent++;
            } catch (\Throwable $exception) {
                Connection::run('UPDATE notifications SET status = ?, error = ? WHERE id = ?', ['erro', mb_substr($exception->getMessage(), 0, 300), (int) $row['id']]);
            }
        }
        return $sent;
    }

    public function alertUpcoming(): int
    {
        $hours = max(1, (int) Settings::get('alert_hours', '24'));
        $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $rows = Connection::all(
            "SELECT r.id, r.code, r.starts_at, c.full_name, c.phone, c.email
             FROM reservations r JOIN clients c ON c.id = r.client_id
             WHERE r.deleted_at IS NULL AND r.operational_status IN ('confirmado', 'aguardando_confirmacao')
             AND r.starts_at IS NOT NULL AND r.starts_at BETWEEN ? AND ?",
            [now(), $until]
        );
        $created = 0;
        foreach ($rows as $row) {
            $exists = Connection::value(
                "SELECT id FROM notifications WHERE related_type = 'reservation' AND related_id = ? AND template = 'reserva_proxima' AND created_at >= ?",
                [(int) $row['id'], date('Y-m-d H:i:s', time() - 86400)]
            );
            if ($exists) {
                continue;
            }
            $body = 'Reserva ' . $row['code'] . ' de ' . $row['full_name'] . ' começa em ' . date_br((string) $row['starts_at']) . '.';
            $this->queue('email', (string) (env('SMTP_FROM') ?: 'equipe@local'), 'reserva_proxima', ['subject' => 'Reserva próxima ' . $row['code'], 'body' => $body], 'reservation', (int) $row['id']);
            if (!empty($row['phone'])) {
                $this->queue('whatsapp', (string) $row['phone'], 'reserva_proxima', ['body' => $body], 'reservation', (int) $row['id']);
            }
            activity('alerta', $body, 'reservation', (int) $row['id']);
            $created++;
        }
        $this->flush();
        return $created;
    }

    public function cleanup(): void
    {
        Connection::run('DELETE FROM sessions WHERE expires_at < ?', [now()]);
        Connection::run('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 90)]);
    }

    private function sendMail(string $to, string $subject, string $body): void
    {
        $host = (string) env('SMTP_HOST');
        $port = (int) env('SMTP_PORT', '587');
        $user = (string) env('SMTP_USER', '');
        $password = (string) env('SMTP_PASSWORD', '');
        $from = (string) env('SMTP_FROM');
        $encryption = (string) env('SMTP_ENCRYPTION', 'tls');
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $error, 10, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new \RuntimeException('Não foi possível conectar ao servidor de e-mail.');
        }
        stream_set_timeout($socket, 10);
        $this->smtpExpect($socket, [220]);
        $this->smtpCommand($socket, 'EHLO drivexperience.local', [250]);
        if ($encryption === 'tls') {
            $this->smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Falha ao iniciar TLS no e-mail.');
            }
            $this->smtpCommand($socket, 'EHLO drivexperience.local', [250]);
        }
        if ($user !== '') {
            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($user), [334]);
            $this->smtpCommand($socket, base64_encode($password), [235]);
        }
        $this->smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
        $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->smtpCommand($socket, 'DATA', [354]);
        $message = "From: {$from}\r\nTo: {$to}\r\nSubject: {$subject}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n.";
        $this->smtpCommand($socket, $message, [250]);
        $this->smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
    }

    public function sendReservation(int $id, array $data): void
    {
        $row = Connection::one(
            "SELECT r.*, c.full_name, c.phone, c.whatsapp, v.brand, v.model,
                    (SELECT description FROM reservation_items WHERE reservation_id = r.id ORDER BY id LIMIT 1) AS experience
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             LEFT JOIN vehicles v ON v.id = r.vehicle_id
             WHERE r.id = ? AND r.deleted_at IS NULL",
            [$id]
        );
        if (!$row) {
            throw new NotFoundException('Reserva não encontrada.');
        }
        $number = whatsapp_number($row['phone'] ?? null, $row['whatsapp'] ?? null);
        if ($number === null) {
            throw new ValidationException(['phone' => 'Este cliente não tem WhatsApp.']);
        }
        if (!$this->configured('whatsapp')) {
            throw new ValidationException(['message' => 'A conexão de WhatsApp não está configurada. Nenhuma mensagem é enviada.']);
        }
        $opening = trim((string) ($data['opening'] ?? ''));
        $closing = trim((string) ($data['closing'] ?? ''));
        $text = trim($opening . ($opening !== '' && $closing !== '' ? "\n\n" : '') . $closing);
        if ($text === '') {
            throw new ValidationException(['opening' => 'Escreva a mensagem.']);
        }
        $map = [];
        foreach (message_variables($row) as $variable) {
            $map[$variable['token']] = $variable['value'];
        }
        $text = strtr($text, $map);
        if (mb_strlen($text) > 4000) {
            throw new ValidationException(['opening' => 'A mensagem pode ter no máximo 4000 caracteres.']);
        }
        $buttons = json_decode((string) ($data['buttons'] ?? '[]'), true);
        $choices = $this->buttonChoices(is_array($buttons) ? $buttons : [], $map);
        $trace = ['track_source' => 'reserva', 'track_id' => (string) $row['code']];
        try {
            if ($choices === []) {
                $payload = ['number' => $number, 'text' => $text] + $trace;
                if (preg_match('#https?://#', $text)) {
                    $payload['linkPreview'] = true;
                }
                $this->postWhatsapp('/send/text', $payload);
            } else {
                $this->postWhatsapp('/send/menu', [
                    'number' => $number,
                    'type' => 'button',
                    'text' => $text,
                    'choices' => $choices,
                ] + $trace);
            }
        } catch (\RuntimeException $exception) {
            $this->store('whatsapp', $number, 'mensagem_reserva', ['body' => $text], 'erro', $exception->getMessage(), 'reservation', $id);
            throw new ValidationException(['message' => $exception->getMessage()]);
        }
        $this->store('whatsapp', $number, 'mensagem_reserva', ['body' => $text], 'enviada', null, 'reservation', $id);
        activity('mensagem', 'WhatsApp enviado para a reserva ' . $row['code'], 'reservation', $id);
    }

    private function buttonChoices(array $buttons, array $map): array
    {
        $choices = [];
        foreach (array_slice($buttons, 0, 3) as $button) {
            if (!is_array($button)) {
                continue;
            }
            $label = trim(strtr((string) ($button['label'] ?? ''), $map));
            $kind = (string) ($button['kind'] ?? 'resposta');
            $value = trim(strtr((string) ($button['value'] ?? ''), $map));
            if ($label === '') {
                continue;
            }
            if (str_contains($label, '|')) {
                throw new ValidationException(['buttons' => 'O texto do botão não pode conter o caractere |.']);
            }
            if (mb_strlen($label) > 20) {
                throw new ValidationException(['buttons' => 'Cada botão pode ter no máximo 20 caracteres.']);
            }
            if ($kind === 'link') {
                if (!preg_match('#^https://#', $value)) {
                    throw new ValidationException(['buttons' => 'O link do botão precisa começar com https://.']);
                }
                $choices[] = $label . '|url:' . $value;
                continue;
            }
            $id = $value !== '' ? $value : $label;
            if (preg_match('/[|\n]/', $id)) {
                throw new ValidationException(['buttons' => 'O identificador do botão não pode ter quebra de linha.']);
            }
            $choices[] = $label . '|' . $id;
        }
        return $choices;
    }

    private function store(string $channel, string $recipient, string $template, array $payload, string $status, ?string $error, ?string $relatedType, ?int $relatedId): void
    {
        Connection::insert(
            'INSERT INTO notifications (channel, recipient, template, payload_json, status, error, related_type, related_id, sent_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$channel, $recipient, $template, json_encode($payload, JSON_UNESCAPED_UNICODE), $status, $error, $relatedType, $relatedId, $status === 'enviada' ? now() : null, now()]
        );
    }

    private function whatsappBase(): string
    {
        $base = rtrim((string) (env('WHATSAPP_API_URL') ?: env('WHATSAPP_WEBHOOK_URL') ?: ''), '/');
        $base = (string) preg_replace('#/send/(text|menu)$#', '', $base);
        return $base;
    }

    private function postWhatsapp(string $path, array $body): void
    {
        $base = $this->whatsappBase();
        $token = (string) env('WHATSAPP_TOKEN', '');
        if ($base === '' || $token === '' || !function_exists('curl_init')) {
            throw new \RuntimeException('A conexão de WhatsApp não está configurada. Nenhuma mensagem é enviada.');
        }
        $channel = curl_init($base . $path);
        curl_setopt_array($channel, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'token: ' . $token],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($channel);
        $status = (int) curl_getinfo($channel, CURLINFO_RESPONSE_CODE);
        curl_close($channel);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException($this->whatsappError($status, is_string($response) ? $response : ''));
        }
    }

    private function whatsappError(int $status, string $response): string
    {
        $data = json_decode($response, true);
        $message = '';
        if (is_array($data)) {
            $message = trim((string) ($data['message_ptbr'] ?? $data['provider_message_ptbr'] ?? ''));
        }
        $message = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/uazapi/i', '', $message)));
        if ($message !== '') {
            return $message;
        }
        return match ($status) {
            401 => 'A conexão de WhatsApp recusou o token.',
            400 => 'A mensagem foi recusada. Revise o texto e os botões.',
            429 => 'A conexão de WhatsApp atingiu o limite de envios.',
            default => 'A conexão de WhatsApp recusou a mensagem.',
        };
    }

    private function sendWhatsapp(string $recipient, array $payload): void
    {
        $number = whatsapp_number($recipient, null);
        if ($number === null) {
            throw new \RuntimeException('O telefone do destinatário é inválido.');
        }
        $this->postWhatsapp('/send/text', ['number' => $number, 'text' => (string) ($payload['body'] ?? '')]);
    }

    private function smtpCommand($socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpExpect($socket, $expected);
    }

    private function smtpExpect($socket, array $expected): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('O servidor de e-mail retornou uma resposta inesperada.');
        }
    }
}
