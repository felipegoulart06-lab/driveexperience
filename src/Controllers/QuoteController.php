<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\DocumentService;
use App\Services\QuoteService;
use App\Support\NotFoundException;

final class QuoteController
{
    public function index(Request $request): void
    {
        view('quotes/index', [
            'title' => 'Orçamentos',
            'quotes' => (new QuoteService())->list(),
            'clients' => Connection::all('SELECT id, full_name FROM clients WHERE archived_at IS NULL ORDER BY full_name LIMIT 300'),
            'modalities' => Connection::all('SELECT id, name, type, base_price_cents FROM modalities WHERE active = 1 ORDER BY sort_order, name'),
            'prices' => Connection::all('SELECT vehicle_id, modality_id, price_cents FROM vehicle_prices'),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL AND active = 1'),
        ]);
    }

    public function store(Request $request): void
    {
        $id = (new QuoteService())->create($request->all(), (int) Auth::id());
        redirect_with('/orcamentos/' . $id, 'ok', 'Orçamento criado.');
    }

    public function show(Request $request, string $id): void
    {
        $quote = (new QuoteService())->find((int) $id);
        if (!$quote) {
            throw new NotFoundException();
        }
        view('quotes/show', ['title' => $quote['code'], 'quote' => $quote]);
    }

    public function pdf(Request $request, string $id): void
    {
        $issued = (new DocumentService())->issueQuote((int) $id, (int) Auth::id());
        redirect('/documentos/' . $issued['public_id']);
    }

    public function convert(Request $request, string $id): void
    {
        $reservationId = (new QuoteService())->convert((int) $id, (int) Auth::id());
        redirect_with('/reservas/' . $reservationId, 'ok', 'Orçamento convertido em reserva.');
    }
}
