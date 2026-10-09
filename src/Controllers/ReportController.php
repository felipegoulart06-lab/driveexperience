<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\DocumentService;
use App\Services\ReportService;
use App\Support\AuthException;

final class ReportController
{
    public function index(Request $request): void
    {
        view('reports/index', [
            'title' => 'Relatórios',
            'filters' => $this->filters($request),
            'origins' => Connection::all('SELECT id, name FROM client_origins WHERE active = 1'),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL'),
            'platforms' => Connection::all('SELECT id, name FROM platforms WHERE active = 1'),
            'users' => Connection::all('SELECT id, name FROM users WHERE active = 1'),
            'preview' => $this->preview($request),
        ]);
    }

    public function csv(Request $request): void
    {
        $filters = $this->filters($request);
        $type = (string) $filters['type'];
        $this->guard($type);
        $filename = 'relatorio-' . $type . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $report = new ReportService();
        if ($type === 'financeiro') {
            $data = $report->financial($filters);
            fputcsv($out, ['Indicador', 'Valor'], ';');
            foreach ($data['summary'] as $key => $value) {
                fputcsv($out, [$key, number_format(((int) $value) / 100, 2, ',', '.')], ';');
            }
        } elseif ($type === 'clientes') {
            fputcsv($out, ['Nome', 'CPF', 'Telefone', 'E-mail', 'Cidade', 'Origem'], ';');
            foreach ($report->clients($filters) as $row) {
                fputcsv($out, [$row['full_name'], $row['cpf'], $row['phone'], $row['email'], $row['city'], $row['origin_name']], ';');
            }
        } elseif ($type === 'origens') {
            fputcsv($out, ['Origem', 'Quantidade', 'Valor'], ';');
            foreach ($report->origins($filters) as $row) {
                fputcsv($out, [$row['name'], $row['quantity'], number_format(((int) $row['total']) / 100, 2, ',', '.')], ';');
            }
        } else {
            fputcsv($out, ['Reserva', 'Data', 'Cliente', 'Veículo', 'Operação', 'Financeiro', 'Valor'], ';');
            foreach ($report->reservations($filters)['rows'] as $row) {
                fputcsv($out, [$row['code'], $row['scheduled_date'], $row['full_name'], $row['vehicle'], $row['operational_status'], $row['financial_status'], number_format(((int) $row['final_cents']) / 100, 2, ',', '.')], ';');
            }
        }
        fclose($out);
        exit;
    }

    public function pdf(Request $request): void
    {
        $filters = $this->filters($request);
        $type = (string) $filters['type'];
        $this->guard($type);
        $report = new ReportService();
        $rows = '';
        $summary = '';
        $docType = 'relatorio_reservas';
        if ($type === 'financeiro') {
            $data = $report->financial($filters);
            $docType = 'relatorio_financeiro';
            $summary = 'Recebido ' . money($data['summary']['received']) . ' · Despesas ' . money($data['summary']['expenses']) . ' · Resultado de caixa ' . money($data['summary']['cash_result']);
            foreach ($data['payments'] as $row) {
                $rows .= '<tr><td>' . e($row['full_name']) . '</td><td>' . e((string) $row['code']) . '</td><td>' . e(money((int) $row['amount_cents'])) . '</td></tr>';
            }
        } elseif ($type === 'origens') {
            $docType = 'relatorio_vendas_origem';
            foreach ($report->origins($filters) as $row) {
                $rows .= '<tr><td>' . e($row['name']) . '</td><td>' . e((string) $row['quantity']) . '</td><td>' . e(money((int) $row['total'])) . '</td></tr>';
            }
        } elseif ($type === 'clientes') {
            $docType = 'relatorio_clientes';
            foreach ($report->clients($filters) as $row) {
                $rows .= '<tr><td>' . e($row['full_name']) . '</td><td>' . e((string) $row['cpf']) . '</td><td>' . e((string) $row['origin_name']) . '</td></tr>';
            }
        } else {
            $data = $report->reservations($filters);
            $summary = 'Total vendido no filtro: ' . money($data['total']);
            foreach ($data['rows'] as $row) {
                $rows .= '<tr><td>' . e($row['code']) . '</td><td>' . e($row['full_name']) . '</td><td>' . e((string) $row['vehicle']) . '</td><td>' . e(money((int) $row['final_cents'])) . '</td></tr>';
            }
        }
        $issued = (new DocumentService())->issueReport($docType, 'Relatório ' . $type, [
            'periodo' => ($filters['from'] ?: 'início') . ' a ' . ($filters['to'] ?: 'hoje'),
            'resumo' => $summary . ' Gerado em ' . date('d/m/Y H:i') . '.',
            'tabela' => '<table>' . $rows . '</table>',
        ], (int) Auth::id());
        redirect('/documentos/' . $issued['public_id']);
    }

    private function filters(Request $request): array
    {
        $source = $request->method() === 'POST' ? $request->all() : $_GET;
        return [
            'type' => (string) ($source['type'] ?? 'reservas'),
            'from' => parse_date((string) ($source['from'] ?? '')) ?: '',
            'to' => parse_date((string) ($source['to'] ?? '')) ?: '',
            'vehicle_id' => (string) ($source['vehicle_id'] ?? ''),
            'status' => (string) ($source['status'] ?? ''),
            'financial' => (string) ($source['financial'] ?? ''),
            'origin_id' => (string) ($source['origin_id'] ?? ''),
            'platform_id' => (string) ($source['platform_id'] ?? ''),
            'user_id' => (string) ($source['user_id'] ?? ''),
        ];
    }

    private function preview(Request $request): array
    {
        $filters = $this->filters($request);
        $report = new ReportService();
        return match ($filters['type']) {
            'financeiro' => ['kind' => 'financeiro', 'data' => Auth::can('finance.view') ? $report->financial($filters) : []],
            'clientes' => ['kind' => 'clientes', 'data' => $report->clients($filters)],
            'origens' => ['kind' => 'origens', 'data' => $report->origins($filters)],
            default => ['kind' => 'reservas', 'data' => $report->reservations($filters)],
        };
    }

    private function guard(string $type): void
    {
        if (in_array($type, ['financeiro', 'origens'], true) && !Auth::can('finance.view') && !Auth::can('reports.finance')) {
            throw new AuthException('Seu perfil não pode emitir este relatório.');
        }
    }
}
