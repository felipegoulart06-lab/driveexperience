<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\Settings;
use App\Services\TrackingService;

final class TrackingController
{
    public function __construct(private TrackingService $tracking = new TrackingService())
    {
    }

    public function index(Request $request): void
    {
        view('tracking/index', [
            'title' => 'Rastreio',
            'items' => $this->tracking->snapshot(),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL AND active = 1 ORDER BY brand, model'),
            'canManage' => Auth::can('tracking.manage'),
            'endpoint' => $this->endpoint(),
            'token' => Auth::can('tracking.manage') ? (string) Settings::get('tracking_token', '') : '',
        ]);
    }

    public function feed(Request $request): void
    {
        json_out(['items' => $this->tracking->snapshot()]);
    }

    public function store(Request $request): void
    {
        $this->tracking->save($request->all());
        redirect_with('/rastreio', 'ok', 'Tag cadastrada. Quando ela enviar a posição, o carro aparece no mapa.');
    }

    public function destroy(Request $request, string $id): void
    {
        $this->tracking->delete((int) $id);
        redirect_with('/rastreio', 'ok', 'Tag removida.');
    }

    public function rotate(Request $request): void
    {
        Settings::set('tracking_token', bin2hex(random_bytes(24)), Auth::id());
        redirect_with('/rastreio', 'ok', 'Nova chave gerada. Atualize o aparelho ou a plataforma da tag.');
    }

    public function ingest(Request $request): void
    {
        $expected = (string) Settings::get('tracking_token', '');
        $given = $this->presentedToken($request);
        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            json_out(['error' => 'Chave de rastreio inválida.'], 401);
        }
        $result = $this->tracking->ingest($request->json());
        json_out($result, $result['accepted'] > 0 ? 200 : 422);
    }

    private function endpoint(): string
    {
        $https = is_https();
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host . '/api/rastreio';
    }

    private function presentedToken(Request $request): string
    {
        $header = $request->header('Authorization');
        if (str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }
        $own = $request->header('X-DX-Tracking');
        return $own !== '' ? $own : trim((string) $request->query('token', ''));
    }
}
