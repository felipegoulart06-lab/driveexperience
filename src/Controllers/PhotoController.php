<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\DocumentService;
use App\Services\ProductionService;
use App\Support\NotFoundException;

final class PhotoController
{
    public function __construct(private ProductionService $productions = new ProductionService())
    {
    }

    public function index(Request $request): void
    {
        view('photos/index', [
            'title' => 'Pacotes de fotos',
            'items' => $this->productions->list(),
            'packages' => Connection::all("SELECT * FROM modalities WHERE type = 'foto' AND active = 1 ORDER BY sort_order"),
            'clients' => Connection::all('SELECT id, full_name FROM clients WHERE archived_at IS NULL ORDER BY full_name LIMIT 300'),
        ]);
    }

    public function store(Request $request): void
    {
        $id = $this->productions->create($request->all(), (int) Auth::id());
        redirect_with('/fotos/' . $id, 'ok', 'Pacote contratado.');
    }

    public function show(Request $request, string $id): void
    {
        $item = $this->productions->find((int) $id);
        if (!$item) {
            throw new NotFoundException();
        }
        view('photos/show', ['title' => $item['package_name'] ?: 'Produção', 'item' => $item, 'users' => Connection::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name')]);
    }

    public function update(Request $request, string $id): void
    {
        $this->productions->update((int) $id, $request->all(), (int) Auth::id());
        if ($request->str('status') === 'entregue') {
            try {
                (new DocumentService())->issueProduction('comprovante_entrega', (int) $id, (int) Auth::id());
            } catch (\Throwable) {
            }
        }
        redirect_with('/fotos/' . $id, 'ok', 'Produção atualizada.');
    }

    public function document(Request $request, string $id, string $key): void
    {
        (new DocumentService())->issueProduction($key, (int) $id, (int) Auth::id());
        redirect_with('/fotos/' . $id, 'ok', 'Documento de produção emitido.');
    }
}
