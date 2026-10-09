<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\FinanceService;

final class FinanceController
{
    public function index(Request $request): void
    {
        $from = parse_date((string) $request->query('from', '')) ?: date('Y-m-01');
        $to = parse_date((string) $request->query('to', '')) ?: date('Y-m-t');
        view('finance/index', [
            'title' => 'Financeiro',
            'from' => $from,
            'to' => $to,
            'summary' => (new FinanceService())->summary($from, $to),
            'payments' => Connection::all("SELECT p.*, c.full_name, r.code, m.name AS method_name FROM payments p JOIN clients c ON c.id = p.client_id LEFT JOIN reservations r ON r.id = p.reservation_id LEFT JOIN payment_methods m ON m.id = p.method_id WHERE substr(COALESCE(p.paid_at, p.created_at), 1, 10) BETWEEN ? AND ? ORDER BY p.id DESC", [$from, $to]),
            'expenses' => Connection::all('SELECT e.*, c.name AS category FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.deleted_at IS NULL AND e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC', [$from, $to]),
            'fees' => Connection::all("SELECT id, code, platform_fee_cents FROM reservations WHERE platform_fee_cents > 0 AND deleted_at IS NULL ORDER BY id DESC LIMIT 20"),
            'categories' => Connection::all('SELECT * FROM expense_categories WHERE active = 1'),
            'methods' => Connection::all('SELECT * FROM payment_methods WHERE active = 1'),
        ]);
    }

    public function expense(Request $request): void
    {
        $amount = parse_money($request->str('amount'));
        (new FinanceService())->addExpense([
            'amount_cents' => (int) $amount,
            'description' => $request->str('description'),
            'expense_date' => $request->str('expense_date'),
            'category_id' => $request->int('category_id'),
            'method_id' => $request->int('method_id'),
            'reservation_id' => $request->int('reservation_id') ?: null,
        ], (int) Auth::id());
        redirect_with('/financeiro', 'ok', 'Despesa registrada.');
    }

    public function destroyExpense(Request $request, string $id): void
    {
        (new FinanceService())->deleteExpense((int) $id, (int) Auth::id());
        redirect_with('/financeiro', 'ok', 'Despesa excluída logicamente.');
    }

    public function refund(Request $request, string $id): void
    {
        (new FinanceService())->refund((int) $id, (int) Auth::id());
        redirect_with('/financeiro', 'ok', 'Pagamento estornado e saldo recalculado.');
    }

    public function fee(Request $request, string $id): void
    {
        (new FinanceService())->launchPlatformFee((int) $id, (int) Auth::id());
        redirect_with('/financeiro', 'ok', 'Taxa da plataforma lançada como despesa.');
    }
}
