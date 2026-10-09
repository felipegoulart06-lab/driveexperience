<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\ClientService;
use App\Services\FinanceService;
use App\Services\ReservationService;

final class DemoSeeder
{
    public function run(): void
    {
        $userId = (int) Connection::value('SELECT id FROM users ORDER BY id LIMIT 1');
        $clients = new ClientService();
        $site = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'site'");
        $whatsapp = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'whatsapp'");
        $first = $clients->create([
            'full_name' => 'Cliente Demonstração Silva',
            'cpf' => '39053344705',
            'phone' => '47988887766',
            'whatsapp' => '47988887766',
            'email' => 'demonstracao.silva@example.com',
            'city' => 'Balneário Camboriú',
            'state' => 'SC',
            'origin_id' => $site,
            'notes' => 'Registro fictício de demonstração. Não representa um cliente real.',
            'is_demo' => 1,
        ], $userId);
        $clients->create([
            'full_name' => 'Cliente Demonstração Costa',
            'phone' => '47977776655',
            'whatsapp' => '47977776655',
            'email' => 'demonstracao.costa@example.com',
            'city' => 'Itajaí',
            'state' => 'SC',
            'origin_id' => $whatsapp,
            'notes' => 'Registro fictício de demonstração. Não representa um cliente real.',
            'is_demo' => 1,
        ], $userId);

        $date = date('Y-m-d', strtotime('+4 days'));
        (new ReservationService())->create([
            'client_mode' => 'existing',
            'client_id' => $first,
            'origin_id' => $site,
            'modality_id' => [(int) Connection::value("SELECT id FROM modalities WHERE slug = 'super-drive'")],
            'vehicle_id' => (int) Connection::value("SELECT id FROM vehicles WHERE slug = 'porsche-911-cabriolet-turbo-s'"),
            'scheduled_date' => $date,
            'start_time' => '10:00',
            'participants' => 1,
            'assigned_user_id' => $userId,
            'operational_status' => 'confirmado',
            'payment_amount' => '500,00',
            'payment_method_id' => (int) Connection::value('SELECT id FROM payment_methods ORDER BY sort_order, id LIMIT 1'),
            'payment_notes' => 'Pagamento parcial fictício de demonstração.',
            'notes' => 'Reserva fictícia de demonstração.',
            'is_demo' => 1,
        ], $userId);

        (new FinanceService())->addExpense([
            'amount_cents' => 15000,
            'description' => 'Despesa fictícia de demonstração',
            'expense_date' => date('Y-m-d'),
            'category_id' => (int) Connection::value('SELECT id FROM expense_categories ORDER BY id LIMIT 1'),
            'is_demo' => 1,
            'source_key' => 'demo-despesa',
        ], $userId);
    }
}
