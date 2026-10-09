<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Database\Connection;

$base = 'http://localhost:8080';
$cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dx-http-smoke.txt';
@unlink($cookie);

$fail = 0;
$check = static function (bool $ok, string $message) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$call = static function (string $method, string $path, array $fields = []) use ($base, $cookie): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($fields !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException(curl_error($ch));
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, substr($raw, 0, $headerSize), substr($raw, $headerSize)];
};

[$status, $headers, $html] = $call('GET', '/login');
$check($status === 200 && str_contains($html, 'DRIVE XPERIENCE'), 'tela de login');
preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
$check(isset($csrf[1]), 'token de segurança no login');

preg_match('/^Senha:\s*(\S+)/m', (string) file_get_contents(dirname(__DIR__) . '/storage/credenciais-locais.txt'), $secret);
[$status, $headers, $html] = $call('POST', '/login', [
    '_csrf' => $csrf[1] ?? '',
    'email' => 'admin@localhost',
    'password' => $secret[1] ?? '',
]);
$check($status === 302 && str_contains($headers, 'Location: /'), 'login redireciona ao painel');

[$status, , $html] = $call('GET', '/');
$check($status === 200 && str_contains($html, 'Cliente Demonstração Silva') && str_contains($html, 'Dados de demonstração'), 'painel com dados reais');

foreach (['/clientes', '/reservas', '/agenda', '/veiculos', '/produtos', '/fotos', '/financeiro', '/documentos', '/relatorios', '/orcamentos', '/integracoes', '/usuarios', '/configuracoes'] as $path) {
    [$status, , $body] = $call('GET', $path);
    $check($status === 200 && !str_contains($body, 'Fatal error'), $path . ' responde');
}

[$status, , $html] = $call('GET', '/reservas/nova');
preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
$suffix = (string) random_int(10000000, 99999999);
$phone = '479' . substr($suffix, 0, 8);
$date = date('Y-m-d', strtotime('+' . (30 + ((int) $suffix % 50)) . ' days'));
$payload = [
    '_csrf' => $csrf[1] ?? '',
    'client_mode' => 'new',
    'client_name' => 'Cliente Operação ' . $suffix,
    'client_phone' => $phone,
    'client_email' => 'operacao.' . $suffix . '@example.com',
    'client_city' => 'Balneário Camboriú',
    'client_state' => 'SC',
    'origin_id' => (string) Connection::value("SELECT id FROM client_origins WHERE slug = 'site'"),
    'modality_id' => [(string) Connection::value("SELECT id FROM modalities WHERE slug = 'super-drive'")],
    'vehicle_id' => (string) Connection::value("SELECT id FROM vehicles WHERE slug = 'porsche-911-cabriolet-turbo-s'"),
    'scheduled_date' => $date,
    'start_time' => '11:00',
    'participants' => '1',
    'operational_status' => 'aguardando_confirmacao',
    'discount' => '0,00',
    'additional_fees' => '0,00',
    'payment_amount' => '0,00',
    'payment_method_id' => (string) Connection::value('SELECT id FROM payment_methods ORDER BY sort_order, id LIMIT 1'),
];
[$status, , $html] = $call('POST', '/reservas', $payload);
$check($status === 200 && str_contains($html, 'Cliente Operação ' . $suffix) && str_contains($html, 'Confirmar reserva'), 'resumo da reserva');
preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
$payload['_csrf'] = $csrf[1] ?? '';
$payload['intent'] = 'save';
[$status, $headers] = $call('POST', '/reservas', $payload);
$check($status === 302 && str_contains($headers, 'Location: /reservas/'), 'reserva gravada');
preg_match('/Location: (\/reservas\/\d+)/', $headers, $location);
[$status, , $html] = $call('GET', $location[1] ?? '/reservas');
$check($status === 200 && str_contains($html, 'DX-'), 'ficha da reserva aberta');

$before = (int) Connection::value('SELECT COUNT(*) FROM reservations');
$conflict = $payload;
$conflict['client_name'] = 'Outro Cliente ' . $suffix;
$conflict['client_email'] = 'outro.' . $suffix . '@example.com';
$conflict['client_phone'] = '478' . substr($suffix, 0, 8);
[$status, , $html] = $call('GET', '/reservas/nova');
preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
$conflict['_csrf'] = $csrf[1] ?? '';
[$status, , $html] = $call('POST', '/reservas', $conflict);
preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
$conflict['_csrf'] = $csrf[1] ?? $conflict['_csrf'];
$conflict['intent'] = 'save';
[$status] = $call('POST', '/reservas', $conflict);
$after = (int) Connection::value('SELECT COUNT(*) FROM reservations');
$check($status === 302 && $after === $before, 'conflito de horário não cria outra reserva');

preg_match('/href="(\/documentos\/[a-f0-9]+)"/', $html, $doc);
if (!isset($doc[1])) {
    [$status, , $html] = $call('GET', '/documentos');
    preg_match('/href="(\/documentos\/[a-f0-9]+)"/', $html, $doc);
}
[$status, $headers, $pdf] = $call('GET', $doc[1] ?? '/documentos/inexistente');
$check($status === 200 && str_starts_with($pdf, '%PDF'), 'download autenticado de PDF');

[$status] = $call('GET', '/documentos/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$check($status === 404, 'documento inexistente não vaza arquivo');

echo $fail === 0 ? "HTTP OK\n" : "HTTP COM FALHAS\n";
exit($fail === 0 ? 0 : 1);
