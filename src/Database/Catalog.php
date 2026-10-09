<?php

declare(strict_types=1);

namespace App\Database;

use App\Domain\DocumentTemplates;

final class Catalog
{
    public static function seed(): void
    {
        self::access();
        self::company();
        self::origins();
        self::platforms();
        self::hours();
        self::payments();
        self::categories();
        self::modalities();
        self::vehicles();
        self::templates();
    }

    private static function access(): void
    {
        if (Connection::value('SELECT id FROM permissions LIMIT 1')) {
            return;
        }
        $permissions = [
            ['Ver painel', 'dashboard.view', 'painel'],
            ['Ver clientes', 'clients.view', 'clientes'],
            ['Gerir clientes', 'clients.manage', 'clientes'],
            ['Ver dados sensíveis do cliente', 'clients.sensitive', 'clientes'],
            ['Anonimizar cliente', 'clients.anonymize', 'clientes'],
            ['Ver reservas', 'reservations.view', 'reservas'],
            ['Gerir reservas', 'reservations.manage', 'reservas'],
            ['Cancelar reservas', 'reservations.cancel', 'reservas'],
            ['Ver agenda', 'agenda.view', 'agenda'],
            ['Gerir agenda', 'agenda.manage', 'agenda'],
            ['Ver veículos', 'vehicles.view', 'veiculos'],
            ['Gerir veículos', 'vehicles.manage', 'veiculos'],
            ['Ver placa', 'vehicles.sensitive', 'veiculos'],
            ['Ver rastreio', 'tracking.view', 'rastreio'],
            ['Gerir rastreio', 'tracking.manage', 'rastreio'],
            ['Ver produtos', 'products.view', 'produtos'],
            ['Gerir produtos', 'products.manage', 'produtos'],
            ['Ver fotos', 'photos.view', 'fotos'],
            ['Gerir fotos', 'photos.manage', 'fotos'],
            ['Ver financeiro', 'finance.view', 'financeiro'],
            ['Gerir financeiro', 'finance.manage', 'financeiro'],
            ['Ver documentos', 'documents.view', 'documentos'],
            ['Emitir documentos', 'documents.issue', 'documentos'],
            ['Ver relatórios', 'reports.view', 'relatorios'],
            ['Ver relatórios financeiros', 'reports.finance', 'relatorios'],
            ['Ver orçamentos', 'quotes.view', 'orcamentos'],
            ['Gerir orçamentos', 'quotes.manage', 'orcamentos'],
            ['Gerir integrações', 'integrations.manage', 'integracoes'],
            ['Gerir usuários', 'users.manage', 'usuarios'],
            ['Gerir configurações', 'settings.manage', 'configuracoes'],
        ];
        foreach ($permissions as $permission) {
            Connection::insert('INSERT INTO permissions (name, slug, module) VALUES (?, ?, ?)', $permission);
        }
        $roles = [
            ['Administrador', 'administrador', 'Acesso completo à operação, ao financeiro e às configurações.'],
            ['Atendimento', 'atendimento', 'Clientes, vendas, reservas, agenda e documentos operacionais.'],
            ['Financeiro', 'financeiro', 'Pagamentos, despesas, relatórios financeiros e documentos autorizados.'],
            ['Operacional', 'operacional', 'Agenda, veículos, reservas e ordens de serviço.'],
        ];
        foreach ($roles as $role) {
            Connection::insert('INSERT INTO roles (name, slug, description, is_system, created_at) VALUES (?, ?, ?, 1, ?)', [...$role, now()]);
        }
        $map = [
            'administrador' => array_column($permissions, 1),
            'atendimento' => ['dashboard.view', 'clients.view', 'clients.manage', 'clients.sensitive', 'reservations.view', 'reservations.manage', 'reservations.cancel', 'agenda.view', 'agenda.manage', 'vehicles.view', 'vehicles.sensitive', 'tracking.view', 'products.view', 'photos.view', 'photos.manage', 'documents.view', 'documents.issue', 'quotes.view', 'quotes.manage'],
            'financeiro' => ['dashboard.view', 'clients.view', 'reservations.view', 'finance.view', 'finance.manage', 'documents.view', 'documents.issue', 'reports.view', 'reports.finance', 'quotes.view'],
            'operacional' => ['dashboard.view', 'agenda.view', 'agenda.manage', 'vehicles.view', 'vehicles.manage', 'vehicles.sensitive', 'tracking.view', 'tracking.manage', 'reservations.view', 'documents.view', 'photos.view'],
        ];
        foreach ($map as $role => $slugs) {
            $roleId = (int) Connection::value('SELECT id FROM roles WHERE slug = ?', [$role]);
            foreach ($slugs as $slug) {
                $permissionId = (int) Connection::value('SELECT id FROM permissions WHERE slug = ?', [$slug]);
                Connection::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permissionId]);
            }
        }
    }

    private static function company(): void
    {
        if (Connection::value('SELECT setting_key FROM settings WHERE setting_key = ?', ['company_name'])) {
            return;
        }
        $settings = [
            'company_name' => 'Drive Xperience',
            'company_document' => '',
            'company_phone' => '+55 (47) 99695-2504',
            'company_email' => 'financeiro@odrivexperience.com.br',
            'company_address' => 'Av. do Estado Dalmo Vieira, 400 - Anexo 1 - Ariribá',
            'company_city' => 'Balneário Camboriú',
            'company_state' => 'SC',
            'company_zip' => '88338-635',
            'company_website' => 'https://odrivexperience.com.br/',
            'buffer_minutes' => '20',
            'alert_hours' => '24',
            'session_hours' => '8',
            'document_retention_months' => '60',
            'cancellation_policy' => 'Cancelamentos e reagendamentos devem ser registrados com o motivo. A política comercial definitiva deve ser confirmada pela empresa antes de ser apresentada ao cliente.',
            'reschedule_policy' => 'O reagendamento depende da disponibilidade do veículo e da agenda. A reserva anterior permanece no histórico.',
            'quote_conditions' => 'Proposta sujeita à disponibilidade do veículo e à confirmação da equipe. Os valores podem ser alterados enquanto o orçamento não for convertido em reserva.',
            'storage_driver' => 'database',
            'demo_loaded' => '0',
            'app_installed' => '1',
        ];
        foreach ($settings as $key => $value) {
            Connection::run('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)', [$key, $value, now()]);
        }
    }

    private static function origins(): void
    {
        if (Connection::value('SELECT id FROM client_origins LIMIT 1')) {
            return;
        }
        $origins = [
            ['Site', 'site', 0],
            ['WhatsApp', 'whatsapp', 0],
            ['Instagram', 'instagram', 0],
            ['Indicação', 'indicacao', 0],
            ['Atendimento presencial', 'presencial', 0],
            ['Venda direta', 'venda-direta', 0],
            ['Plataforma externa', 'plataforma', 1],
            ['Outros', 'outros', 0],
        ];
        $order = 1;
        foreach ($origins as [$name, $slug, $platform]) {
            Connection::insert(
                'INSERT INTO client_origins (name, slug, requires_platform, active, sort_order) VALUES (?, ?, ?, 1, ?)',
                [$name, $slug, $platform, $order++]
            );
        }
    }

    private static function platforms(): void
    {
        if (Connection::value('SELECT id FROM platforms LIMIT 1')) {
            return;
        }
        $stamp = now();
        Connection::insert(
            'INSERT INTO platforms (name, slug, status, connector, notes, active, trust_payment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)',
            ['Webhook genérico', 'generico', 'pendente', 'generico', 'Conector pronto para receber o contrato JSON documentado. Informe o segredo para ativar a assinatura. Enquanto estiver pendente, nenhuma venda é gravada por esta via.', $stamp, $stamp]
        );
    }

    private static function hours(): void
    {
        if (Connection::value('SELECT id FROM operating_hours LIMIT 1')) {
            return;
        }
        for ($day = 0; $day <= 6; $day++) {
            Connection::insert(
                'INSERT INTO operating_hours (weekday, opens_at, closes_at, active) VALUES (?, ?, ?, 1)',
                [$day, '09:00', '18:00']
            );
        }
    }

    private static function payments(): void
    {
        if (Connection::value('SELECT id FROM payment_methods LIMIT 1')) {
            return;
        }
        $methods = ['PIX', 'Dinheiro', 'Cartão de crédito', 'Cartão de débito', 'Transferência', 'Plataforma externa', 'Outro'];
        foreach ($methods as $index => $name) {
            Connection::insert('INSERT INTO payment_methods (name, active, sort_order) VALUES (?, 1, ?)', [$name, $index + 1]);
        }
    }

    private static function categories(): void
    {
        if (Connection::value('SELECT id FROM expense_categories LIMIT 1')) {
            return;
        }
        foreach (['Combustível', 'Manutenção', 'Marketing', 'Taxas de plataforma', 'Impostos', 'Outros'] as $name) {
            Connection::insert('INSERT INTO expense_categories (name, active) VALUES (?, 1)', [$name]);
        }
    }

    private static function modalities(): void
    {
        if (Connection::value('SELECT id FROM modalities LIMIT 1')) {
            return;
        }
        $items = [
            ['Super Drive', 'super-drive', 'Experiência ampliada de direção. A duração inicial é uma estimativa para a agenda e pode ser ajustada.', 'experiencia', 40, 0, 1],
            ['Drive Xperience', 'drive-xperience', 'Direção com instrutor no percurso de referência da operação. No site institucional esta experiência aparece como Drive.', 'experiencia', 25, 0, 2],
            ['Carona', 'carona', 'Passeio como passageiro, com a condução da equipe.', 'experiencia', 20, 0, 3],
            ['Rota da Fama', 'rota-da-fama', 'Percurso estendido de direção.', 'experiencia', 50, 0, 4],
            ['Carona da Fama', 'carona-da-fama', 'Passeio estendido como passageiro.', 'experiencia', 40, 0, 5],
            ['Pacote de fotos Prata', 'fotos-prata', '15 fotos profissionais editadas.', 'foto', 0, 29900, 6],
            ['Pacote de fotos Ouro', 'fotos-ouro', '20 fotos profissionais editadas e reels personalizados para Instagram.', 'foto', 0, 39900, 7],
            ['Pacote de fotos Diamante', 'fotos-diamante', '30 fotos profissionais editadas, conteúdo com Meta Smart Glasses, captação aérea com drone e reels personalizados para Instagram.', 'foto', 0, 79900, 8],
        ];
        $stamp = now();
        foreach ($items as $item) {
            Connection::insert(
                'INSERT INTO modalities (name, slug, description, type, duration_minutes, base_price_cents, active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
                [...$item, $stamp, $stamp]
            );
        }
    }

    private static function vehicles(): void
    {
        if (Connection::value('SELECT id FROM vehicles LIMIT 1')) {
            return;
        }
        $fleet = [
            ['Porsche', '911 Cabriolet Turbo S', 'porsche-911-cabriolet-turbo-s', 'Super Drive 2990; Drive Xperience 1290; Carona 650; Rota da Fama 1890; Carona da Fama 1390'],
            ['Lamborghini', 'Gallardo', 'lamborghini-gallardo', 'Super Drive 2990; Drive Xperience 1290; Carona 650; Rota da Fama 1890; Carona da Fama 1390'],
            ['Maserati', 'GranCabrio', 'maserati-grancabrio', 'Super Drive 1990; Drive Xperience 890; Carona 390; Rota da Fama 1390; Carona da Fama 890'],
            ['Porsche', '718 Boxster S', 'porsche-718-boxster-s', 'Super Drive 1590; Drive Xperience 590; Carona 290; Rota da Fama 990; Carona da Fama 590'],
            ['Dodge', 'Charger R/T', 'dodge-charger-rt', 'Super Drive 1590; Drive Xperience 590; Carona 290; Rota da Fama 990; Carona da Fama 590'],
            ['Chevrolet', 'Camaro SS Convertible', 'camaro-ss-convertible', 'Super Drive 1190; Drive Xperience 390; Rota da Fama 790; Carona da Fama 790'],
        ];
        $prices = [
            'porsche-911-cabriolet-turbo-s' => ['super-drive' => 299000, 'drive-xperience' => 129000, 'carona' => 65000, 'rota-da-fama' => 189000, 'carona-da-fama' => 139000],
            'lamborghini-gallardo' => ['super-drive' => 299000, 'drive-xperience' => 129000, 'carona' => 65000, 'rota-da-fama' => 189000, 'carona-da-fama' => 139000],
            'maserati-grancabrio' => ['super-drive' => 199000, 'drive-xperience' => 89000, 'carona' => 39000, 'rota-da-fama' => 139000, 'carona-da-fama' => 89000],
            'porsche-718-boxster-s' => ['super-drive' => 159000, 'drive-xperience' => 59000, 'carona' => 29000, 'rota-da-fama' => 99000, 'carona-da-fama' => 59000],
            'dodge-charger-rt' => ['super-drive' => 159000, 'drive-xperience' => 59000, 'carona' => 29000, 'rota-da-fama' => 99000, 'carona-da-fama' => 59000],
            'camaro-ss-convertible' => ['super-drive' => 119000, 'drive-xperience' => 39000, 'rota-da-fama' => 79000, 'carona-da-fama' => 79000],
        ];
        $stamp = now();
        foreach ($fleet as [$brand, $model, $slug, $note]) {
            $vehicleId = Connection::insert(
                'INSERT INTO vehicles (brand, model, slug, description, capacity, base_status, notes, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, 2, ?, ?, 1, 0, ?, ?)',
                [$brand, $model, $slug, 'Veículo da frota inicial. Capacidade, ano e placa devem ser confirmados pela operação.', 'disponivel', $note, $stamp, $stamp]
            );
            foreach ($prices[$slug] as $modality => $cents) {
                $modalityId = (int) Connection::value('SELECT id FROM modalities WHERE slug = ?', [$modality]);
                Connection::run('INSERT INTO vehicle_prices (vehicle_id, modality_id, price_cents) VALUES (?, ?, ?)', [$vehicleId, $modalityId, $cents]);
            }
        }
    }

    private static function templates(): void
    {
        if (Connection::value('SELECT id FROM document_templates LIMIT 1')) {
            return;
        }
        foreach (DocumentTemplates::all() as $type => [$name, $body]) {
            Connection::insert(
                'INSERT INTO document_templates (type, name, body, version, updated_at) VALUES (?, ?, ?, 1, ?)',
                [$type, $name, $body, now()]
            );
        }
    }
}
