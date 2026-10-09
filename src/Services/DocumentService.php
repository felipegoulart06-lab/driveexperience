<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Database\Connection;
use App\Domain\DocumentTemplates;
use App\Domain\Statuses;
use App\Support\NotFoundException;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class DocumentService
{
    public function __construct(private FileStore $files = new FileStore())
    {
    }

    public function issueReservation(string $type, int $reservationId, int $userId): array
    {
        $reservation = (new ReservationService($this->clientsWithoutLoop(), new AvailabilityService(), new FinanceService(), $this))->find($reservationId);
        if (!$reservation) {
            throw new NotFoundException('Reserva não encontrada.');
        }
        $tokens = $this->reservationTokens($reservation);
        $title = DocumentTemplates::all()[$type][0] ?? 'Documento';
        return $this->store($type, $title . ' ' . $reservation['code'], $tokens, [
            'reservation_id' => $reservationId,
            'client_id' => (int) $reservation['client_id'],
        ], $userId, $reservation['code']);
    }

    public function issuePayment(int $paymentId, int $userId): array
    {
        $payment = Connection::one(
            'SELECT p.*, m.name AS method_name, r.code, r.final_cents, r.pending_cents, c.full_name
             FROM payments p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN reservations r ON r.id = p.reservation_id
             LEFT JOIN payment_methods m ON m.id = p.method_id
             WHERE p.id = ?',
            [$paymentId]
        );
        if (!$payment) {
            throw new NotFoundException('Pagamento não encontrado.');
        }
        $tokens = $this->companyTokens() + [
            'documento' => (string) $payment['public_id'],
            'codigo' => (string) ($payment['code'] ?? '—'),
            'cliente_nome' => (string) $payment['full_name'],
            'valor_lancamento' => money((int) $payment['amount_cents']),
            'data_pagamento' => date_br((string) ($payment['paid_at'] ?? $payment['created_at'])),
            'forma_pagamento' => (string) ($payment['method_name'] ?? 'Não informada'),
            'parcelas' => (string) $payment['installments'],
            'referencia' => (string) ($payment['external_transaction_id'] ?: $payment['notes'] ?: '—'),
            'valor_final' => money((int) ($payment['final_cents'] ?? $payment['amount_cents'])),
            'saldo' => money((int) ($payment['pending_cents'] ?? 0)),
        ];
        return $this->store('recibo_pagamento', 'Recibo ' . ($payment['code'] ?? $payment['id']), $tokens, [
            'reservation_id' => $payment['reservation_id'] ? (int) $payment['reservation_id'] : null,
            'client_id' => (int) $payment['client_id'],
            'payment_id' => $paymentId,
        ], $userId, (string) ($payment['code'] ?? 'pagamento'));
    }

    public function issueQuote(int $quoteId, int $userId): array
    {
        $quote = Connection::one('SELECT q.*, c.full_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?', [$quoteId]);
        if (!$quote) {
            throw new NotFoundException('Orçamento não encontrado.');
        }
        $rows = '';
        foreach (Connection::all('SELECT * FROM quote_items WHERE quote_id = ?', [$quoteId]) as $item) {
            $rows .= '<tr><td>' . e($item['description']) . '</td><td>' . e((string) $item['quantity']) . '</td><td>' . e(money((int) $item['total_cents'])) . '</td></tr>';
        }
        $tokens = $this->companyTokens() + [
            'codigo' => (string) $quote['code'],
            'cliente_nome' => (string) $quote['full_name'],
            'validade' => date_br($quote['valid_until']),
            'status_orcamento' => (string) $quote['status'],
            'valor_final' => money((int) $quote['total_cents']),
            'condicoes' => (string) ($quote['conditions'] ?: Settings::get('quote_conditions', '')),
            'observacoes' => (string) ($quote['notes'] ?: '—'),
            'tabela' => '<table><tr><th>Item</th><th>Qtd.</th><th>Valor</th></tr>' . $rows . '</table>',
        ];
        return $this->store('orcamento', 'Orçamento ' . $quote['code'], $tokens, [
            'quote_id' => $quoteId,
            'client_id' => (int) $quote['client_id'],
        ], $userId, (string) $quote['code'], ['tabela']);
    }

    public function issueProduction(string $type, int $productionId, int $userId): array
    {
        $production = Connection::one(
            'SELECT p.*, c.full_name, m.name AS package_name, r.code, u.name AS producer_name
             FROM productions p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN modalities m ON m.id = p.modality_id
             LEFT JOIN reservations r ON r.id = p.reservation_id
             LEFT JOIN users u ON u.id = p.producer_user_id
             WHERE p.id = ?',
            [$productionId]
        );
        if (!$production) {
            throw new NotFoundException('Produção não encontrada.');
        }
        $tokens = $this->companyTokens() + [
            'cliente_nome' => (string) $production['full_name'],
            'pacote' => (string) ($production['package_name'] ?? 'Pacote'),
            'valor_final' => money((int) $production['amount_cents']),
            'data' => date_br((string) $production['contracted_at']),
            'status_producao' => Statuses::label('production', (string) $production['status']),
            'responsavel' => (string) ($production['producer_name'] ?: '—'),
            'previsao' => date_br($production['due_date']),
            'entrega' => date_br($production['delivered_at']),
            'codigo' => (string) ($production['code'] ?: 'Venda avulsa'),
            'observacoes' => (string) ($production['notes'] ?: '—'),
            'link' => (string) ($production['private_link'] ?: 'Não informado'),
        ];
        return $this->store($type, 'Produção ' . $production['id'], $tokens, [
            'production_id' => $productionId,
            'client_id' => (int) $production['client_id'],
            'reservation_id' => $production['reservation_id'] ? (int) $production['reservation_id'] : null,
        ], $userId, 'producao-' . $productionId);
    }

    public function issueReport(string $type, string $title, array $tokens, int $userId, ?int $clientId = null): array
    {
        return $this->store($type, $title, $this->companyTokens() + $tokens, [
            'client_id' => $clientId,
        ], $userId, 'relatorio', ['tabela']);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return Connection::one('SELECT * FROM documents WHERE public_id = ?', [$publicId]);
    }

    public function canView(array $document): bool
    {
        $type = (string) $document['type'];
        if ($document['redacted_at']) {
            return Auth::can('clients.anonymize') || Auth::can('settings.manage');
        }
        return match ($type) {
            'ordem_servico' => Auth::can('agenda.view') || Auth::can('vehicles.manage') || Auth::can('reservations.manage'),
            'ficha_reserva', 'termo_responsabilidade' => Auth::can('reservations.manage') || Auth::can('clients.sensitive'),
            'recibo_pagamento', 'relatorio_financeiro', 'relatorio_vendas_origem' => Auth::can('finance.view') || Auth::can('reservations.manage'),
            'orcamento' => Auth::can('quotes.view') || Auth::can('reservations.manage'),
            'relatorio_clientes' => Auth::can('clients.view') && Auth::can('reports.view') || Auth::can('clients.sensitive'),
            'relatorio_reservas' => Auth::can('reports.view') || Auth::can('reservations.view'),
            default => Auth::can('documents.view') || Auth::can('reservations.manage') || Auth::can('agenda.view'),
        };
    }

    public function binary(array $document): string
    {
        if ($document['redacted_at']) {
            throw new RuntimeException('Documento removido por solicitação de privacidade.');
        }
        return $this->files->get((string) $document['storage_driver'], $document['storage_key'], $document['content']);
    }

    private function reservationTokens(array $reservation): array
    {
        $fullDocument = Auth::can('clients.sensitive') || Auth::can('reservations.manage');
        $items = Connection::all('SELECT description FROM reservation_items WHERE reservation_id = ?', [(int) $reservation['id']]);
        $payments = Connection::all(
            "SELECT amount_cents, paid_at, status FROM payments WHERE reservation_id = ? AND status = 'confirmado' ORDER BY id",
            [(int) $reservation['id']]
        );
        $paymentRows = '';
        foreach ($payments as $payment) {
            $paymentRows .= '<tr><td>' . e(date_br((string) $payment['paid_at'])) . '</td><td>' . e(money((int) $payment['amount_cents'])) . '</td></tr>';
        }
        $assignee = Connection::value('SELECT name FROM users WHERE id = ?', [(int) $reservation['assigned_user_id']]);
        return $this->companyTokens() + [
            'codigo' => (string) $reservation['code'],
            'status_operacional' => Statuses::label('operational', (string) $reservation['operational_status']),
            'status_financeiro' => Statuses::label('financial', (string) $reservation['financial_status']),
            'cliente_nome' => (string) $reservation['client_name'],
            'cliente_documento' => mask_cpf($reservation['client_cpf'], $fullDocument),
            'cliente_telefone' => (string) ($reservation['client_phone'] ?: '—'),
            'cliente_email' => (string) ($reservation['client_email'] ?: '—'),
            'cliente_cidade' => trim((string) $reservation['client_city'] . ' ' . (string) $reservation['client_state']) ?: '—',
            'origem' => (string) ($reservation['origin_name'] ?: '—'),
            'plataforma' => (string) ($reservation['platform_name'] ?: '—'),
            'codigo_externo' => (string) ($reservation['external_transaction_id'] ?: '—'),
            'reserva_externa' => (string) ($reservation['external_reservation_code'] ?: '—'),
            'veiculo' => trim((string) $reservation['brand'] . ' ' . (string) $reservation['model']) ?: '—',
            'placa' => mask_plate($reservation['plate'], Auth::can('vehicles.sensitive')),
            'experiencia' => $items === [] ? '—' : implode(', ', array_column($items, 'description')),
            'data' => date_br($reservation['scheduled_date']),
            'horario' => (string) ($reservation['start_time'] ?: '—'),
            'duracao' => ((int) $reservation['duration_minutes']) > 0 ? $reservation['duration_minutes'] . ' min' : '—',
            'participantes' => (string) $reservation['participants'],
            'responsavel' => (string) ($assignee ?: '—'),
            'valor_original' => money((int) $reservation['original_cents']),
            'desconto' => money((int) $reservation['discount_cents']),
            'taxas' => money((int) $reservation['fees_cents']),
            'valor_final' => money((int) $reservation['final_cents']),
            'valor_pago' => money((int) $reservation['paid_cents']),
            'saldo' => money((int) $reservation['pending_cents']),
            'observacoes' => (string) ($reservation['notes'] ?: '—'),
            'pagamentos' => $paymentRows === '' ? '<p>Nenhum pagamento confirmado.</p>' : '<table><tr><th>Data</th><th>Valor</th></tr>' . $paymentRows . '</table>',
            'politica' => (string) Settings::get('cancellation_policy', ''),
            'endereco' => trim((string) Settings::get('company_address', '') . ' - ' . (string) Settings::get('company_city', '')),
        ];
    }

    private function companyTokens(): array
    {
        return [
            'empresa' => (string) Settings::get('company_name', 'Drive Xperience'),
            'telefone_empresa' => (string) Settings::get('company_phone', ''),
            'emissao' => date('d/m/Y H:i'),
        ];
    }

    private function store(string $type, string $title, array $tokens, array $links, int $userId, string $name, array $raw = ['pagamentos', 'tabela']): array
    {
        $template = Connection::one('SELECT * FROM document_templates WHERE type = ?', [$type]);
        if (!$template) {
            $defaults = DocumentTemplates::all()[$type] ?? null;
            if (!$defaults) {
                throw new RuntimeException('Modelo de documento não encontrado.');
            }
            $body = $defaults[1];
            $templateVersion = 1;
            $templateName = $defaults[0];
        } else {
            $body = (string) $template['body'];
            $templateVersion = (int) $template['version'];
            $templateName = (string) $template['name'];
        }
        $html = $this->render($templateName, $this->applyTokens($body, $tokens, $raw), $tokens);
        $binary = $this->pdf($html);
        $scope = 'type = ?';
        $params = [$type];
        foreach (['reservation_id', 'payment_id', 'quote_id', 'production_id'] as $column) {
            if (!empty($links[$column])) {
                $scope .= " AND {$column} = ?";
                $params[] = $links[$column];
            }
        }
        $version = (int) Connection::value("SELECT COALESCE(MAX(version), 0) FROM documents WHERE {$scope}", $params) + 1;
        $publicId = bin2hex(random_bytes(16));
        $filename = $this->filename($type, $name, $version);
        $stored = $this->files->put($binary, $filename, 'application/pdf');
        $id = Connection::insert(
            'INSERT INTO documents (public_id, type, version, template_version, title, reservation_id, client_id, payment_id, production_id, quote_id, filename, mime, byte_size, checksum, content, storage_driver, storage_key, issued_at, issued_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId, $type, $version, $templateVersion, $title,
                $links['reservation_id'] ?? null, $links['client_id'] ?? null, $links['payment_id'] ?? null,
                $links['production_id'] ?? null, $links['quote_id'] ?? null,
                $filename, 'application/pdf', strlen($binary), hash('sha256', $binary),
                $stored['content'], $stored['driver'], $stored['key'], now(), $userId,
            ]
        );
        activity('documento', $templateName . ' v' . $version . ' emitido', 'document', $id);
        audit('document', 'document', $id, ['type' => $type, 'version' => $version]);
        return ['id' => $id, 'public_id' => $publicId, 'version' => $version];
    }

    private function applyTokens(string $body, array $tokens, array $raw): string
    {
        foreach ($tokens as $key => $value) {
            if (in_array((string) $key, $raw, true)) {
                continue;
            }
            $body = str_replace('{{' . $key . '}}', e((string) $value), $body);
        }
        foreach ($raw as $key) {
            $body = str_replace('{{' . $key . '}}', (string) ($tokens[$key] ?? ''), $body);
        }
        return $body;
    }

    private function render(string $title, string $body, array $tokens): string
    {
        $company = e((string) ($tokens['empresa'] ?? 'Drive Xperience'));
        $issued = e((string) ($tokens['emissao'] ?? date('d/m/Y H:i')));
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;color:#122033;font-size:12px}
            header{border-bottom:3px solid #0E6EBE;padding-bottom:8px;margin-bottom:16px}
            .brand{font-size:18px;color:#071833;font-weight:bold;letter-spacing:.4px}
            .sub{color:#0E6EBE;font-size:10px}
            h1{font-size:16px;margin:12px 0}
            h2{font-size:13px}
            table{width:100%;border-collapse:collapse;margin:8px 0 14px}
            th,td{border:1px solid #D5DEE8;padding:6px 8px;text-align:left;vertical-align:top}
            th{background:#F3F6FA;width:22%}
            footer{position:fixed;bottom:-8px;left:0;right:0;font-size:9px;color:#5C6B80;border-top:1px solid #D5DEE8;padding-top:4px}
        </style></head><body><header><div class="brand">' . $company . '</div><div class="sub">Experiências com supercarros · Balneário Camboriú</div></header><h1>' . e($title) . '</h1>' . $body . '<footer>' . $company . ' · Emitido em ' . $issued . ' · Documento interno protegido por autenticação</footer></body></html>';
    }

    private function pdf(string $html): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('A biblioteca de PDF não está instalada. Execute composer install no servidor.');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $temp = sys_get_temp_dir();
        $options->set('tempDir', $temp);
        $options->set('fontDir', $temp);
        $options->set('fontCache', $temp);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        return $dompdf->output();
    }

    private function filename(string $type, string $name, int $version): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $type . '-' . $name) ?: 'documento';
        return trim($safe, '-') . '-v' . $version . '.pdf';
    }

    private function clientsWithoutLoop(): ClientService
    {
        return new ClientService();
    }
}
