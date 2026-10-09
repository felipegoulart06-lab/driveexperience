<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Database\Connection;
use App\Database\Installer;
use App\Security\Crypto;
use App\Services\ClientService;
use App\Services\DocumentService;
use App\Services\FinanceService;
use App\Services\ImportService;
use App\Services\IntegrationService;
use App\Services\ReservationService;
use App\Services\UserService;
use App\Support\ValidationException;

$database = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dx-acceptance.sqlite';
if (is_file($database)) {
    unlink($database);
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=1');
putenv('APP_KEY=acceptance-key-with-more-than-32-characters');
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . $database);
putenv('ADMIN_EMAIL=admin@example.com');
putenv('ADMIN_PASSWORD=Teste-Seguro-123');
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_KEY'] = 'acceptance-key-with-more-than-32-characters';
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = $database;

require dirname(__DIR__) . '/bootstrap/app.php';
Connection::reset();

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'OK  ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

Installer::run([
    'admin_name' => 'Administrador',
    'admin_email' => 'admin@example.com',
    'admin_password' => 'Teste-Seguro-123',
    'demo' => false,
]);
$adminId = (int) Connection::value('SELECT id FROM users LIMIT 1');
Auth::login($adminId);
$check($adminId > 0, 'instalação cria administrador');

$clients = new ClientService();
$clientId = $clients->create([
    'full_name' => 'Ana Paula Ferreira',
    'cpf' => '39053344705',
    'phone' => '(47) 99999-0001',
    'email' => 'ana.teste@example.com',
    'city' => 'Balneário Camboriú',
    'state' => 'SC',
    'origin_id' => (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'whatsapp'"),
], $adminId);
$check($clientId > 0, 'cadastrar cliente');
$duplicateBlocked = false;
try {
    $clients->resolve([
        'mode' => 'new',
        'full_name' => 'Ana Paula Ferreira',
        'phone' => '47999990001',
        'email' => 'ana.teste@example.com',
    ], '', null, $adminId);
} catch (ValidationException $exception) {
    $duplicateBlocked = isset($exception->errors['_duplicates']);
}
$check($duplicateBlocked, 'cliente compatível exige confirmação');

$origin = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'venda-direta'");
$modality = (int) Connection::value("SELECT id FROM modalities WHERE slug = 'drive-xperience'");
$vehicle = (int) Connection::value("SELECT id FROM vehicles WHERE slug = 'porsche-911-cabriolet-turbo-s'");
$reservations = new ReservationService();
$date = date('Y-m-d', strtotime('next monday'));
$created = $reservations->create([
    'client_mode' => 'existing',
    'client_id' => $clientId,
    'origin_id' => $origin,
    'modality_id' => [$modality],
    'vehicle_id' => $vehicle,
    'scheduled_date' => $date,
    'start_time' => '10:00',
    'participants' => 1,
    'operational_status' => 'confirmado',
], $adminId);
$check(($created['code'] ?? '') !== '', 'criar reserva ' . ($created['code'] ?? ''));
$pdf = Connection::value('SELECT content FROM documents WHERE reservation_id = ? AND type = ?', [$created['id'], 'ficha_reserva']);
$check(is_string($pdf) && str_starts_with($pdf, '%PDF'), 'gerar ficha de reserva em PDF');

$conflict = false;
try {
    $reservations->create([
        'client_mode' => 'existing',
        'client_id' => $clientId,
        'origin_id' => $origin,
        'modality_id' => [$modality],
        'vehicle_id' => $vehicle,
        'scheduled_date' => $date,
        'start_time' => '10:20',
        'participants' => 1,
    ], $adminId);
} catch (ValidationException) {
    $conflict = true;
}
$check($conflict, 'impedir conflito de horário');

$finance = new FinanceService();
$finance->addPayment([
    'client_id' => $clientId,
    'reservation_id' => $created['id'],
    'amount_cents' => 50000,
    'status' => 'confirmado',
    'origin' => 'manual',
], $adminId);
$reservation = $reservations->find((int) $created['id']);
$check((int) $reservation['pending_cents'] === (int) $reservation['final_cents'] - 50000, 'calcular saldo do pagamento parcial');
$check($reservation['financial_status'] === 'parcial', 'status financeiro parcial');
$receipt = (new DocumentService())->issuePayment((int) Connection::value('SELECT id FROM payments ORDER BY id DESC LIMIT 1'), $adminId);
$receiptBinary = Connection::value('SELECT content FROM documents WHERE public_id = ?', [$receipt['public_id']]);
$check(is_string($receiptBinary) && str_starts_with($receiptBinary, '%PDF'), 'gerar recibo');

$reservations->update((int) $created['id'], [
    'scheduled_date' => $date,
    'start_time' => '11:00',
    'vehicle_id' => $vehicle,
    'duration_minutes' => 25,
    'participants' => 1,
    'discount' => '0',
    'additional_fees' => '0',
], $adminId);
$reservations->cancel((int) $created['id'], 'Cliente solicitou cancelamento', $adminId);
$check($reservations->find((int) $created['id'])['operational_status'] === 'cancelado', 'cancelar reserva preservando histórico');

$platformId = (int) Connection::value("SELECT id FROM platforms WHERE slug = 'generico'");
$external = $reservations->recordExternal([
    'platform_id' => $platformId,
    'external_id' => 'PLAT-1001',
    'customer' => ['name' => 'Ricardo Mendes', 'email' => 'ricardo.teste@example.com', 'phone' => '47988880002', 'cpf' => '52998224725'],
    'product' => 'Drive Xperience',
    'vehicle' => 'Porsche 911',
    'scheduled_date' => $date,
    'start_time' => '14:00',
    'participants' => 1,
    'gross_cents' => 129000,
    'fee_cents' => 12900,
    'net_cents' => 116100,
    'reported_paid_cents' => 129000,
], $adminId);
$check(($external['status'] ?? '') === 'created', 'registrar venda externa');
$externalRow = $reservations->find((int) $external['id']);
$check((int) $externalRow['paid_cents'] === 0 && $externalRow['financial_status'] === 'aguardando_pagamento', 'venda externa não entra como paga');
$again = $reservations->recordExternal([
    'platform_id' => $platformId,
    'external_id' => 'PLAT-1001',
    'customer' => ['name' => 'Ricardo Mendes', 'phone' => '47988880002'],
    'product' => 'Drive Xperience',
    'vehicle' => 'Porsche 911',
    'scheduled_date' => $date,
    'start_time' => '14:00',
    'gross_cents' => 129000,
], $adminId);
$check(($again['status'] ?? '') === 'duplicate', 'identificar venda duplicada');

$csv = "codigo,nome,telefone,email,produto,veiculo,data,hora,valor\nPLAT-2002,Juliana Costa,47977770003,juliana.teste@example.com,Carona,Maserati GranCabrio,{$date},15:00,\"390,00\"\n";
$imports = new ImportService();
$batch = $imports->upload('vendas.csv', $csv, $platformId, $adminId);
$imports->preview($batch, [
    'external_id' => 'codigo',
    'customer_name' => 'nome',
    'customer_phone' => 'telefone',
    'customer_email' => 'email',
    'product_name' => 'produto',
    'vehicle_name' => 'veiculo',
    'scheduled_date' => 'data',
    'start_time' => 'hora',
    'gross_amount' => 'valor',
], $adminId);
$first = $imports->confirm($batch, $adminId);
$secondBatch = $imports->upload('vendas.csv', $csv, $platformId, $adminId);
$imports->preview($secondBatch, [
    'external_id' => 'codigo', 'customer_name' => 'nome', 'customer_phone' => 'telefone', 'customer_email' => 'email',
    'product_name' => 'produto', 'vehicle_name' => 'veiculo', 'scheduled_date' => 'data', 'start_time' => 'hora', 'gross_amount' => 'valor',
], $adminId);
$second = $imports->confirm($secondBatch, $adminId);
$check($first['imported'] === 1 && $second['skipped'] === 1, 'importar CSV e ignorar duplicidade');

$secret = 'segredo-de-teste-webhook';
Connection::run('UPDATE platforms SET status = ?, secret_encrypted = ?, connector = ? WHERE id = ?', ['configurado', Crypto::encrypt($secret), 'generico', $platformId]);
$body = json_encode([
    'external_id' => 'WH-3003',
    'customer' => ['name' => 'Camila Souza', 'phone' => '47966660004'],
    'product_code' => 'carona',
    'vehicle_name' => 'Lamborghini Gallardo',
    'scheduled_date' => $date,
    'start_time' => '16:00',
    'gross_amount' => 650,
    'paid_amount' => 650,
], JSON_UNESCAPED_UNICODE);
$signature = hash_hmac('sha256', $body, $secret);
$response = (new IntegrationService())->receive('generico', $body, 'sha256=' . $signature);
$check(($response['body']['status'] ?? '') === 'created', 'webhook assinado registra venda');
$webhookReservation = Connection::one('SELECT paid_cents, financial_status FROM reservations WHERE external_transaction_id = ?', ['WH-3003']);
$check((int) $webhookReservation['paid_cents'] === 0, 'webhook não presume pagamento');

$roleId = (int) Connection::value("SELECT id FROM roles WHERE slug = 'operacional'");
(new UserService())->save([
    'name' => 'Equipe Pista',
    'email' => 'pista@example.com',
    'role_id' => $roleId,
    'password' => 'Operacao-12345',
    'password_confirmation' => 'Operacao-12345',
    'active' => '1',
], null, $adminId);
Auth::login((int) Connection::value('SELECT id FROM users WHERE email = ?', ['pista@example.com']));
$check(Auth::can('agenda.view') && !Auth::can('finance.view'), 'permissões diferentes por perfil');
$document = Connection::one("SELECT * FROM documents WHERE type = 'recibo_pagamento' ORDER BY id DESC LIMIT 1");
$check(!(new DocumentService())->canView($document), 'recibo financeiro não fica disponível ao perfil operacional');

Auth::login($adminId);
$clients->anonymize($clientId, $adminId);
$anon = $clients->find($clientId);
$check(str_starts_with((string) $anon['full_name'], 'Titular anonimizado') && $anon['cpf'] === null, 'anonimizar dados pessoais');
$redacted = Connection::value('SELECT redacted_at FROM documents WHERE client_id = ? AND type = ?', [$clientId, 'ficha_reserva']);
$check($redacted !== null, 'documentos do titular deixam de guardar o PDF');

$finance->addExpense(['amount_cents' => 15000, 'description' => 'Combustível de teste', 'expense_date' => $date, 'category_id' => 1], $adminId);
$summary = $finance->summary(today(), $date);
$check($summary['expenses'] === 15000 && $summary['received'] >= 50000, 'relatório financeiro separa recebido e despesa');

echo $failed === 0 ? "ACEITE OK\n" : "FALHAS {$failed}\n";
exit($failed === 0 ? 0 : 1);
