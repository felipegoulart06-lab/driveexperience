<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Services\FleetService;

final class ProductController
{
    public function index(Request $request): void
    {
        view('products/index', ['title' => 'Produtos e experiências', 'items' => (new FleetService())->modalities()]);
    }

    public function store(Request $request): void
    {
        (new FleetService())->saveModality($request->all(), null);
        redirect_with('/produtos', 'ok', 'Produto cadastrado.');
    }

    public function update(Request $request, string $id): void
    {
        (new FleetService())->saveModality($request->all(), (int) $id);
        redirect_with('/produtos', 'ok', 'Produto atualizado.');
    }
}
