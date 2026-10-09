<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class FinanceService
{
    public function addPayment(array $data, int $userId): int
    {
        $amount = (int) ($data['amount_cents'] ?? 0);
        if ($amount <= 0) {
            throw new ValidationException(['payment_amount' => 'Informe um valor de pagamento maior que zero.']);
        }
        $clientId = (int) ($data['client_id'] ?? 0);
        if (!Connection::one('SELECT id FROM clients WHERE id = ?', [$clientId])) {
            throw new ValidationException(['client_id' => 'Cliente do pagamento não encontrado.']);
        }
        $methodId = (int) ($data['method_id'] ?? 0) ?: null;
        if ($methodId && !Connection::one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) {
            throw new ValidationException(['payment_method_id' => 'Forma de pagamento inválida.']);
        }
        $status = (string) ($data['status'] ?? 'confirmado');
        if (!in_array($status, ['confirmado', 'pendente', 'estornado'], true)) {
            $status = 'confirmado';
        }
        $stamp = now();
        $id = Connection::insert(
            'INSERT INTO payments (public_id, client_id, reservation_id, production_id, amount_cents, method_id, status, origin, paid_at, installments, external_transaction_id, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                bin2hex(random_bytes(16)),
                $clientId,
                ($data['reservation_id'] ?? null) ?: null,
                ($data['production_id'] ?? null) ?: null,
                $amount,
                $methodId,
                $status,
                (string) ($data['origin'] ?? 'manual'),
                $data['paid_at'] ?? ($status === 'confirmado' ? $stamp : null),
                max(1, (int) ($data['installments'] ?? 1)),
                blank_to_null((string) ($data['external_transaction_id'] ?? '')),
                blank_to_null((string) ($data['notes'] ?? '')),
                $userId,
                $stamp,
                $stamp,
            ]
        );
        if (!empty($data['reservation_id'])) {
            $this->recalculate((int) $data['reservation_id']);
        }
        activity('pagamento', 'Pagamento de ' . money($amount) . ' registrado', 'payment', $id);
        audit('payment', 'payment', $id, ['amount_cents' => $amount, 'status' => $status]);
        return $id;
    }

    public function refund(int $id, int $userId): void
    {
        $payment = Connection::one('SELECT * FROM payments WHERE id = ?', [$id]);
        if (!$payment) {
            throw new NotFoundException('Pagamento não encontrado.');
        }
        Connection::run('UPDATE payments SET status = ?, updated_at = ? WHERE id = ?', ['estornado', now(), $id]);
        if ($payment['reservation_id']) {
            $this->recalculate((int) $payment['reservation_id']);
        }
        activity('pagamento', 'Pagamento estornado', 'payment', $id);
        audit('refund', 'payment', $id, ['user' => $userId]);
    }

    public function recalculate(int $reservationId): void
    {
        $reservation = Connection::one('SELECT final_cents, operational_status FROM reservations WHERE id = ?', [$reservationId]);
        if (!$reservation) {
            return;
        }
        $paid = (int) Connection::value(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE reservation_id = ? AND status = 'confirmado'",
            [$reservationId]
        );
        $final = (int) $reservation['final_cents'];
        $pending = max(0, $final - $paid);
        Connection::run(
            'UPDATE reservations SET paid_cents = ?, pending_cents = ?, financial_status = ?, updated_at = ? WHERE id = ?',
            [$paid, $pending, $this->financialStatus($final, $paid), now(), $reservationId]
        );
    }

    public function financialStatus(int $final, int $paid): string
    {
        if ($final <= 0) {
            return 'sem_cobranca';
        }
        if ($paid <= 0) {
            return 'aguardando_pagamento';
        }
        if ($paid < $final) {
            return 'parcial';
        }
        return 'pago';
    }

    public function addExpense(array $data, int $userId): int
    {
        $amount = (int) ($data['amount_cents'] ?? 0);
        $description = trim((string) ($data['description'] ?? ''));
        $date = parse_date($data['expense_date'] ?? '');
        $errors = [];
        if ($amount <= 0) {
            $errors['amount'] = 'Informe o valor da despesa.';
        }
        if ($description === '') {
            $errors['description'] = 'Descreva a despesa.';
        }
        if ($date === null) {
            $errors['expense_date'] = 'Informe a data da despesa.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $source = blank_to_null((string) ($data['source_key'] ?? ''));
        if ($source && Connection::one('SELECT id FROM expenses WHERE source_key = ? AND deleted_at IS NULL', [$source])) {
            throw new ValidationException(['description' => 'Esta despesa já foi lançada.']);
        }
        $stamp = now();
        $id = Connection::insert(
            'INSERT INTO expenses (category_id, amount_cents, description, expense_date, reservation_id, client_id, method_id, source_key, is_demo, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) ($data['category_id'] ?? 0) ?: null,
                $amount,
                $description,
                $date,
                ($data['reservation_id'] ?? null) ?: null,
                ($data['client_id'] ?? null) ?: null,
                (int) ($data['method_id'] ?? 0) ?: null,
                $source,
                !empty($data['is_demo']) ? 1 : 0,
                $userId,
                $userId,
                $stamp,
                $stamp,
            ]
        );
        activity('despesa', 'Despesa registrada: ' . $description, 'expense', $id);
        audit('expense', 'expense', $id, ['amount_cents' => $amount]);
        return $id;
    }

    public function deleteExpense(int $id, int $userId): void
    {
        if (!Connection::one('SELECT id FROM expenses WHERE id = ? AND deleted_at IS NULL', [$id])) {
            throw new NotFoundException('Despesa não encontrada.');
        }
        Connection::run('UPDATE expenses SET deleted_at = ?, updated_by = ?, updated_at = ? WHERE id = ?', [now(), $userId, now(), $id]);
        audit('delete', 'expense', $id);
    }

    public function launchPlatformFee(int $reservationId, int $userId): int
    {
        $reservation = Connection::one('SELECT * FROM reservations WHERE id = ?', [$reservationId]);
        if (!$reservation || (int) $reservation['platform_fee_cents'] <= 0) {
            throw new ValidationException(['platform_fee' => 'Não há taxa de plataforma para lançar.']);
        }
        $category = (int) Connection::value("SELECT id FROM expense_categories WHERE name = 'Taxas de plataforma'");
        return $this->addExpense([
            'amount_cents' => (int) $reservation['platform_fee_cents'],
            'description' => 'Taxa de plataforma da reserva ' . $reservation['code'],
            'expense_date' => today(),
            'reservation_id' => $reservationId,
            'client_id' => (int) $reservation['client_id'],
            'category_id' => $category,
            'source_key' => 'platform-fee-' . $reservationId,
        ], $userId);
    }

    public function summary(string $from, string $to): array
    {
        $sold = (int) Connection::value(
            "SELECT COALESCE(SUM(final_cents), 0) FROM reservations WHERE deleted_at IS NULL AND operational_status <> 'cancelado' AND substr(created_at, 1, 10) BETWEEN ? AND ?",
            [$from, $to]
        );
        $received = (int) Connection::value(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE status = 'confirmado' AND substr(COALESCE(paid_at, created_at), 1, 10) BETWEEN ? AND ?",
            [$from, $to]
        );
        $pending = (int) Connection::value(
            "SELECT COALESCE(SUM(pending_cents), 0) FROM reservations WHERE deleted_at IS NULL AND operational_status <> 'cancelado'"
        );
        $expenses = (int) Connection::value(
            'SELECT COALESCE(SUM(amount_cents), 0) FROM expenses WHERE deleted_at IS NULL AND expense_date BETWEEN ? AND ?',
            [$from, $to]
        );
        return [
            'sold' => $sold,
            'received' => $received,
            'pending' => $pending,
            'expenses' => $expenses,
            'cash_result' => $received - $expenses,
            'accrual_result' => $sold - $expenses,
        ];
    }
}
