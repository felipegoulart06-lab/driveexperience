<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\FleetService;
use App\Support\NotFoundException;

final class VehicleController
{
    public function __construct(private FleetService $fleet = new FleetService())
    {
    }

    public function index(Request $request): void
    {
        $experiences = Connection::all("SELECT id, name FROM modalities WHERE active = 1 AND type = 'experiencia' ORDER BY sort_order, name");
        $prices = [];
        foreach (Connection::all('SELECT vehicle_id, modality_id, price_cents FROM vehicle_prices') as $row) {
            $prices[(int) $row['vehicle_id']][(int) $row['modality_id']] = (int) $row['price_cents'];
        }
        $covers = [];
        foreach ($this->fleet->images() as $image) {
            $vehicleId = (int) $image['vehicle_id'];
            if (!isset($covers[$vehicleId])) {
                $covers[$vehicleId] = $image;
            }
        }
        view('vehicles/index', [
            'title' => 'Veículos',
            'vehicles' => $this->fleet->list(),
            'experiences' => $experiences,
            'prices' => $prices,
            'covers' => $covers,
            'fullPlate' => Auth::can('vehicles.sensitive'),
        ]);
    }

    public function create(Request $request): void
    {
        view('vehicles/form', ['title' => 'Novo veículo', 'vehicle' => ['active' => 1, 'capacity' => 2, 'base_status' => 'disponivel'], 'prices' => $this->fleet->modalities()]);
    }

    public function store(Request $request): void
    {
        $id = $this->fleet->save($request->all(), null, (int) Auth::id());
        redirect_with('/veiculos/' . $id, 'ok', 'Veículo cadastrado.');
    }

    public function show(Request $request, string $id): void
    {
        $vehicle = $this->fleet->find((int) $id);
        if (!$vehicle) {
            throw new NotFoundException();
        }
        view('vehicles/show', [
            'title' => $vehicle['brand'] . ' ' . $vehicle['model'],
            'vehicle' => $vehicle,
            'prices' => $this->fleet->prices((int) $id),
            'logs' => Connection::all('SELECT * FROM maintenance_logs WHERE vehicle_id = ? ORDER BY id DESC', [(int) $id]),
            'images' => $this->fleet->images((int) $id),
            'fullPlate' => Auth::can('vehicles.sensitive'),
        ]);
    }

    public function photo(Request $request, string $id, string $foto): void
    {
        $row = Connection::one(
            'SELECT f.mime, f.content, i.filename FROM vehicle_images i JOIN stored_files f ON f.id = i.file_id WHERE i.vehicle_id = ? AND i.id = ?',
            [(int) $id, (int) $foto]
        );
        if (!$row || !is_string($row['content']) || $row['content'] === '') {
            throw new NotFoundException();
        }
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $row['filename']) ?: 'foto.webp';
        header('Content-Type: ' . (string) $row['mime']);
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $row['content'];
        exit;
    }

    public function movePhoto(Request $request, string $id, string $foto): void
    {
        $this->fleet->moveImage((int) $id, (int) $foto, $request->str('direction'));
        redirect_with('/veiculos/' . (int) $id . '?editar=1', 'ok', 'Ordem atualizada. A primeira foto é a capa.');
    }

    public function deletePhoto(Request $request, string $id, string $foto): void
    {
        $this->fleet->deleteImage((int) $id, (int) $foto);
        redirect_with('/veiculos/' . (int) $id . '?editar=1', 'ok', 'Foto excluída.');
    }

    public function update(Request $request, string $id): void
    {
        $this->fleet->save($request->all(), (int) $id, (int) Auth::id());
        redirect_with('/veiculos/' . $id, 'ok', 'Veículo atualizado.');
    }

    public function destroy(Request $request, string $id): void
    {
        $this->fleet->delete((int) $id);
        redirect_with('/veiculos', 'ok', 'Veículo desativado.');
    }

    public function maintenance(Request $request, string $id): void
    {
        $this->fleet->maintenance((int) $id, $request->all(), (int) Auth::id());
        redirect_with('/veiculos/' . $id, 'ok', 'Manutenção e bloqueio registrados.');
    }
}
