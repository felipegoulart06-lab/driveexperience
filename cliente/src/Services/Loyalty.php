<?php

declare(strict_types=1);

namespace Cliente\Services;

use App\Database\Connection;
use App\Support\ValidationException;

final class Loyalty
{
    public function balance(int $clientId): array
    {
        $earned = (int) Connection::value('SELECT COALESCE(SUM(delta), 0) FROM point_entries WHERE client_id = ? AND delta > 0', [$clientId]);
        $spent = (int) Connection::value('SELECT COALESCE(SUM(-delta), 0) FROM point_entries WHERE client_id = ? AND delta < 0', [$clientId]);
        $available = (int) Connection::value('SELECT COALESCE(SUM(delta), 0) FROM point_entries WHERE client_id = ?', [$clientId]);
        return ['available' => $available, 'earned' => $earned, 'spent' => $spent];
    }

    public function entries(int $clientId): array
    {
        return Connection::all('SELECT * FROM point_entries WHERE client_id = ? ORDER BY id DESC LIMIT 40', [$clientId]);
    }

    public function code(int $clientId, string $name): string
    {
        $row = Connection::one('SELECT code FROM referrals WHERE referrer_client_id = ?', [$clientId]);
        if ($row) {
            return (string) $row['code'];
        }
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
        $base = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $ascii));
        $base = $base !== '' ? $base : 'DRIVE';
        $base = substr($base, 0, 8);
        $code = 'DRIVE-' . $base . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        Connection::insert('INSERT INTO referrals (referrer_client_id, code, created_at) VALUES (?, ?, ?)', [$clientId, $code, now()]);
        return $code;
    }

    public function visitToken(int $clientId): array
    {
        $exp = time() + 120;
        $payload = $clientId . '.' . $exp;
        $sig = hash_hmac('sha256', $payload, (string) env('APP_KEY', 'cliente'));
        return ['token' => $payload . '.' . $sig, 'exp' => $exp];
    }

    public function creditVisit(string $token): string
    {
        $secret = (string) env('CLIENT_STAFF_TOKEN', '');
        $given = (string) ($_SERVER['HTTP_X_DX_STAFF'] ?? '');
        if ($secret === '' || !hash_equals($secret, $given)) {
            throw new ValidationException(['token' => 'A validação presencial não está configurada ou o token da equipe é inválido.']);
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ValidationException(['token' => 'Código inválido.']);
        }
        [$clientId, $exp, $sig] = $parts;
        $payload = $clientId . '.' . $exp;
        $known = hash_hmac('sha256', $payload, (string) env('APP_KEY', 'cliente'));
        if (!hash_equals($known, $sig) || (int) $exp < time()) {
            throw new ValidationException(['token' => 'Código expirado. Peça ao cliente para abrir de novo.']);
        }
        $client = Connection::one('SELECT id, full_name FROM clients WHERE id = ? AND anonymized_at IS NULL', [(int) $clientId]);
        if (!$client) {
            throw new ValidationException(['token' => 'Cliente não encontrado.']);
        }
        $hours = max(1, (int) Connection::value("SELECT setting_value FROM client_settings WHERE setting_key = 'visit_interval_hours'"));
        $points = max(0, (int) Connection::value("SELECT setting_value FROM client_settings WHERE setting_key = 'points_per_visit'"));
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $recent = Connection::value(
            "SELECT id FROM point_entries WHERE client_id = ? AND source = 'visita' AND created_at >= ?",
            [(int) $clientId, $since]
        );
        if ($recent) {
            throw new ValidationException(['token' => 'Esta visita já foi creditada dentro do intervalo configurado.']);
        }
        $sourceId = 'visita-' . $clientId . '-' . date('YmdHi');
        $balance = (int) Connection::value('SELECT COALESCE(SUM(delta), 0) FROM point_entries WHERE client_id = ?', [(int) $clientId]);
        Connection::insert(
            'INSERT INTO point_entries (client_id, delta, balance_after, reason, source, source_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $clientId, $points, $balance + $points, 'Visita presencial validada', 'visita', $sourceId, now()]
        );
        Connection::insert(
            'INSERT INTO client_notifications (client_id, title, body, channel, created_at) VALUES (?, ?, ?, ?, ?)',
            [(int) $clientId, 'Pontos creditados', $points . ' Drive Points pela visita.', 'operacional', now()]
        );
        return (string) $client['full_name'];
    }

    public function redeem(int $clientId, int $rewardId): void
    {
        $reward = Connection::one('SELECT * FROM rewards WHERE id = ? AND active = 1', [$rewardId]);
        if (!$reward || (int) $reward['stock'] < 1) {
            throw new ValidationException(['reward' => 'Recompensa indisponível.']);
        }
        $balance = $this->balance($clientId)['available'];
        if ($balance < (int) $reward['cost_points']) {
            throw new ValidationException(['reward' => 'Saldo insuficiente.']);
        }
        Connection::transaction(function () use ($clientId, $reward, $balance): void {
            Connection::run('UPDATE rewards SET stock = stock - 1 WHERE id = ? AND stock >= 1', [(int) $reward['id']]);
            $next = $balance - (int) $reward['cost_points'];
            Connection::insert(
                'INSERT INTO point_entries (client_id, delta, balance_after, reason, source, source_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$clientId, -1 * (int) $reward['cost_points'], $next, 'Resgate: ' . $reward['name'], 'resgate', 'reward-' . $reward['id'] . '-' . bin2hex(random_bytes(3)), now()]
            );
        });
    }
}
