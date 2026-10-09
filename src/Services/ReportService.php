<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Database\Connection;
use App\Domain\Statuses;

final class ReportService
{
    public function reservations(array $filters): array
    {
        $where = ['r.deleted_at IS NULL'];
        $params = [];
        $this->between($where, $params, 'r.scheduled_date', $filters);
        $this->equals($where, $params, 'r.vehicle_id', $filters['vehicle_id'] ?? null);
        $this->equals($where, $params, 'r.operational_status', $filters['status'] ?? null);
        $this->equals($where, $params, 'r.financial_status', $filters['financial'] ?? null);
        $this->equals($where, $params, 'r.origin_id', $filters['origin_id'] ?? null);
        $this->equals($where, $params, 'r.platform_id', $filters['platform_id'] ?? null);
        $this->equals($where, $params, 'r.assigned_user_id', $filters['user_id'] ?? null);
        if (!empty($filters['client_id'])) {
            $where[] = 'r.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }
        $rows = Connection::all(
            'SELECT r.id, r.code, r.scheduled_date, r.start_time, r.operational_status, r.financial_status, r.final_cents, r.paid_cents, r.pending_cents, c.full_name, v.brand, v.model, o.name AS origin_name
             FROM reservations r JOIN clients c ON c.id = r.client_id
             LEFT JOIN vehicles v ON v.id = r.vehicle_id LEFT JOIN client_origins o ON o.id = r.origin_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.scheduled_date, r.start_time',
            $params
        );
        $total = 0;
        foreach ($rows as &$row) {
            $total += (int) $row['final_cents'];
            $row['operational_status'] = Statuses::label('operational', $row['operational_status']);
            $row['financial_status'] = Statuses::label('financial', $row['financial_status']);
            $row['vehicle'] = trim((string) $row['brand'] . ' ' . (string) $row['model']);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    public function financial(array $filters): array
    {
        $from = $filters['from'] ?: date('Y-m-01');
        $to = $filters['to'] ?: date('Y-m-t');
        $summary = (new FinanceService())->summary($from, $to);
        $payments = Connection::all(
            "SELECT p.paid_at, p.amount_cents, p.origin, p.status, c.full_name, r.code
             FROM payments p JOIN clients c ON c.id = p.client_id LEFT JOIN reservations r ON r.id = p.reservation_id
             WHERE p.status = 'confirmado' AND substr(COALESCE(p.paid_at, p.created_at), 1, 10) BETWEEN ? AND ? ORDER BY p.paid_at",
            [$from, $to]
        );
        $expenses = Connection::all(
            'SELECT e.expense_date, e.description, e.amount_cents, c.name AS category FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.deleted_at IS NULL AND e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date',
            [$from, $to]
        );
        return compact('summary', 'payments', 'expenses', 'from', 'to');
    }

    public function clients(array $filters): array
    {
        $where = ['c.archived_at IS NULL'];
        $params = [];
        if (!empty($filters['origin_id'])) {
            $where[] = 'c.origin_id = ?';
            $params[] = (int) $filters['origin_id'];
        }
        $rows = Connection::all(
            'SELECT c.full_name, c.cpf, c.phone, c.email, c.city, c.state, o.name AS origin_name, c.created_at FROM clients c LEFT JOIN client_origins o ON o.id = c.origin_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.full_name',
            $params
        );
        $full = Auth::can('clients.sensitive');
        foreach ($rows as &$row) {
            $row['cpf'] = mask_cpf($row['cpf'], $full);
        }
        return $rows;
    }

    public function origins(array $filters): array
    {
        $from = $filters['from'] ?: date('Y-m-01');
        $to = $filters['to'] ?: date('Y-m-t');
        return Connection::all(
            "SELECT COALESCE(o.name, 'Sem origem') AS name, COUNT(r.id) AS quantity, COALESCE(SUM(r.final_cents), 0) AS total
             FROM reservations r LEFT JOIN client_origins o ON o.id = r.origin_id
             WHERE r.deleted_at IS NULL AND r.operational_status <> 'cancelado' AND substr(r.created_at, 1, 10) BETWEEN ? AND ?
             GROUP BY o.id, o.name ORDER BY total DESC",
            [$from, $to]
        );
    }

    private function between(array &$where, array &$params, string $column, array $filters): void
    {
        if (!empty($filters['from'])) {
            $where[] = $column . ' >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = $column . ' <= ?';
            $params[] = $filters['to'];
        }
    }

    private function equals(array &$where, array &$params, string $column, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $where[] = $column . ' = ?';
            $params[] = $value;
        }
    }
}
