<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class DashboardService
{
    public function data(): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $finance = (new FinanceService())->summary($monthStart, $monthEnd);
        $hours = max(1, (int) Settings::get('alert_hours', '24'));
        return [
            'clients' => (int) Connection::value('SELECT COUNT(*) FROM clients WHERE archived_at IS NULL'),
            'new_clients' => (int) Connection::value('SELECT COUNT(*) FROM clients WHERE archived_at IS NULL AND substr(created_at, 1, 10) BETWEEN ? AND ?', [$monthStart, $monthEnd]),
            'today' => (int) Connection::value("SELECT COUNT(*) FROM reservations WHERE deleted_at IS NULL AND scheduled_date = ? AND operational_status <> 'cancelado'", [today()]),
            'waiting' => (int) Connection::value("SELECT COUNT(*) FROM reservations WHERE deleted_at IS NULL AND operational_status = 'aguardando_confirmacao'"),
            'confirmed' => (int) Connection::value("SELECT COUNT(*) FROM reservations WHERE deleted_at IS NULL AND operational_status = 'confirmado'"),
            'done' => (int) Connection::value("SELECT COUNT(*) FROM reservations WHERE deleted_at IS NULL AND operational_status = 'concluido' AND substr(COALESCE(completed_at, updated_at), 1, 10) BETWEEN ? AND ?", [$monthStart, $monthEnd]),
            'cancelled' => (int) Connection::value("SELECT COUNT(*) FROM reservations WHERE deleted_at IS NULL AND operational_status = 'cancelado' AND substr(COALESCE(cancelled_at, updated_at), 1, 10) BETWEEN ? AND ?", [$monthStart, $monthEnd]),
            'finance' => $finance,
            'upcoming' => Connection::all(
                "SELECT r.id, r.code, r.scheduled_date, r.start_time, r.operational_status, r.financial_status, r.pending_cents, c.full_name, v.brand, v.model
                 FROM reservations r JOIN clients c ON c.id = r.client_id LEFT JOIN vehicles v ON v.id = r.vehicle_id
                 WHERE r.deleted_at IS NULL AND r.operational_status <> 'cancelado' AND r.scheduled_date >= ?
                 ORDER BY r.scheduled_date, r.start_time LIMIT 8",
                [today()]
            ),
            'alerts' => Connection::all(
                "SELECT r.id, r.code, r.starts_at, r.pending_cents, c.full_name
                 FROM reservations r JOIN clients c ON c.id = r.client_id
                 WHERE r.deleted_at IS NULL AND r.operational_status NOT IN ('cancelado', 'concluido')
                 AND ((r.starts_at IS NOT NULL AND r.starts_at BETWEEN ? AND ?) OR r.pending_cents > 0)
                 ORDER BY r.starts_at LIMIT 8",
                [now(), date('Y-m-d H:i:s', time() + $hours * 3600)]
            ),
            'origins' => Connection::all(
                'SELECT o.name, COUNT(c.id) AS total FROM client_origins o LEFT JOIN clients c ON c.origin_id = o.id AND c.archived_at IS NULL GROUP BY o.id, o.name ORDER BY total DESC'
            ),
            'experiences' => Connection::all(
                "SELECT m.name, COUNT(ri.id) AS total FROM modalities m
                 LEFT JOIN reservation_items ri ON ri.modality_id = m.id
                 LEFT JOIN reservations r ON r.id = ri.reservation_id AND r.deleted_at IS NULL AND r.operational_status <> 'cancelado'
                 WHERE m.type = 'experiencia' GROUP BY m.id, m.name ORDER BY total DESC"
            ),
            'vehicles' => Connection::all(
                "SELECT v.brand, v.model, COUNT(r.id) AS total FROM vehicles v
                 LEFT JOIN reservations r ON r.vehicle_id = v.id AND r.deleted_at IS NULL AND r.operational_status <> 'cancelado'
                 WHERE v.deleted_at IS NULL GROUP BY v.id, v.brand, v.model ORDER BY total DESC LIMIT 6"
            ),
            'sales' => $this->salesSeries(),
            'activities' => Connection::all('SELECT * FROM activities ORDER BY id DESC LIMIT 12'),
        ];
    }

    private function salesSeries(): array
    {
        $start = date('Y-m-01', strtotime('-5 months'));
        $rows = Connection::all(
            "SELECT substr(created_at, 1, 7) AS period, SUM(final_cents) AS total, COUNT(*) AS quantity
             FROM reservations WHERE deleted_at IS NULL AND operational_status <> 'cancelado' AND created_at >= ?
             GROUP BY substr(created_at, 1, 7) ORDER BY period",
            [$start]
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['period']] = $row;
        }
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $period = date('Y-m', strtotime('-' . $i . ' months'));
            $series[] = [
                'period' => $period,
                'label' => date('m/Y', strtotime($period . '-01')),
                'total' => (int) ($indexed[$period]['total'] ?? 0),
                'quantity' => (int) ($indexed[$period]['quantity'] ?? 0),
            ];
        }
        return $series;
    }
}
