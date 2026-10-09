<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Http\Request;
use App\Services\DashboardService;

final class DashboardController
{
    public function index(Request $request): void
    {
        view('dashboard/index', ['title' => 'Painel', 'data' => (new DashboardService())->data(), 'showFinance' => Auth::can('finance.view')]);
    }
}
