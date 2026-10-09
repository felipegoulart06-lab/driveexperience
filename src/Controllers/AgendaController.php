<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\AvailabilityService;
use App\Support\ValidationException;

final class AgendaController
{
    public function index(Request $request): void
    {
        $date = parse_date((string) $request->query('date', today())) ?: today();
        $viewName = (string) $request->query('view', 'semana');
        [$from, $to] = $this->range($viewName, $date);
        $where = "r.deleted_at IS NULL AND r.scheduled_date BETWEEN ? AND ? AND r.operational_status <> 'cancelado'";
        $params = [$from, $to];
        if ($request->query('vehicle_id')) {
            $where .= ' AND r.vehicle_id = ?';
            $params[] = (int) $request->query('vehicle_id');
        }
        if ($request->query('status')) {
            $where .= ' AND r.operational_status = ?';
            $params[] = (string) $request->query('status');
        }
        if ($request->query('user_id')) {
            $where .= ' AND r.assigned_user_id = ?';
            $params[] = (int) $request->query('user_id');
        }
        view('agenda/index', [
            'title' => 'Agenda',
            'viewName' => $viewName,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'reservations' => Connection::all("SELECT r.*, c.full_name, c.phone, c.whatsapp, c.email, v.brand, v.model, (SELECT description FROM reservation_items WHERE reservation_id = r.id ORDER BY id LIMIT 1) AS experience FROM reservations r JOIN clients c ON c.id = r.client_id LEFT JOIN vehicles v ON v.id = r.vehicle_id WHERE {$where} ORDER BY r.scheduled_date, r.start_time", $params),
            'blocks' => Connection::all('SELECT b.*, v.brand, v.model FROM schedule_blocks b LEFT JOIN vehicles v ON v.id = b.vehicle_id WHERE substr(b.starts_at, 1, 10) <= ? AND substr(b.ends_at, 1, 10) >= ? ORDER BY b.starts_at', [$to, $from]),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL ORDER BY brand'),
            'users' => Connection::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name'),
        ]);
    }

    public function block(Request $request): void
    {
        $date = parse_date($request->str('date'));
        $start = $request->bool('all_day') ? '00:00' : parse_time($request->str('start_time'));
        $end = $request->bool('all_day') ? '23:59' : parse_time($request->str('end_time'));
        $reason = $request->str('reason');
        if (!$date || !$start || !$end || $reason === '') {
            throw new ValidationException(['reason' => 'Informe data, horário e motivo do bloqueio.']);
        }
        $starts = $date . ' ' . $start . ':00';
        $ends = $date . ' ' . $end . ':00';
        if ($ends <= $starts) {
            throw new ValidationException(['end_time' => 'O término precisa ser depois do início.']);
        }
        Connection::insert('INSERT INTO schedule_blocks (vehicle_id, starts_at, ends_at, reason, block_type, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $request->int('vehicle_id') ?: null, $starts, $ends, $reason, $request->str('block_type', 'outro'), Auth::id(), now(),
        ]);
        redirect_with('/agenda?date=' . $date, 'ok', 'Horário bloqueado.');
    }

    public function unblock(Request $request, string $id): void
    {
        Connection::run('DELETE FROM schedule_blocks WHERE id = ?', [(int) $id]);
        redirect_with('/agenda', 'ok', 'Bloqueio removido.');
    }

    public function availability(Request $request): void
    {
        try {
            $date = parse_date((string) $request->query('date', ''));
            $time = parse_time((string) $request->query('start', ''));
            $duration = max(15, (int) $request->query('duration', 25));
            if (!$date || !$time || !(int) $request->query('vehicle_id')) {
                json_out(['ok' => false, 'message' => 'Informe veículo, data e horário.']);
            }
            $starts = $date . ' ' . $time . ':00';
            (new AvailabilityService())->assertBookable((int) $request->query('vehicle_id'), $starts, add_minutes($starts, $duration));
            json_out(['ok' => true, 'message' => 'Horário disponível para este veículo.']);
        } catch (ValidationException $exception) {
            json_out(['ok' => false, 'message' => implode(' ', $exception->errors)]);
        }
    }

    private function range(string $view, string $date): array
    {
        if ($view === 'dia') {
            return [$date, $date];
        }
        if ($view === 'mes') {
            return [date('Y-m-01', (int) strtotime($date)), date('Y-m-t', (int) strtotime($date))];
        }
        $monday = date('Y-m-d', (int) strtotime('monday this week', (int) strtotime($date)));
        return [$monday, date('Y-m-d', (int) strtotime($monday . ' +6 days'))];
    }
}
