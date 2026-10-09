<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\ClientService;
use App\Support\NotFoundException;

final class ClientController
{
    public function __construct(private ClientService $clients = new ClientService())
    {
    }

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        view('clients/index', [
            'title' => 'Clientes',
            'result' => $this->clients->list(['q' => $request->query('q', ''), 'archived' => $request->query('archived')], $page),
            'q' => (string) $request->query('q', ''),
        ]);
    }

    public function create(Request $request): void
    {
        view('clients/form', ['title' => 'Novo cliente', 'client' => [], 'origins' => $this->origins(), 'platforms' => $this->platforms()]);
    }

    public function store(Request $request): void
    {
        $id = $this->clients->create($request->all(), (int) Auth::id());
        redirect_with('/clientes/' . $id, 'ok', 'Cliente cadastrado.');
    }

    public function search(Request $request): void
    {
        $rows = $this->clients->search((string) $request->query('q', ''));
        json_out(['results' => array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'full_name' => $row['full_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
        ], $rows)]);
    }

    public function show(Request $request, string $id): void
    {
        $client = $this->require((int) $id);
        view('clients/show', [
            'title' => $client['full_name'],
            'client' => $client,
            'history' => $this->clients->history((int) $id),
            'fullDocument' => Auth::can('clients.sensitive'),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $this->clients->update((int) $id, $request->all(), (int) Auth::id());
        redirect_with('/clientes/' . $id, 'ok', 'Cliente atualizado.');
    }

    public function archive(Request $request, string $id): void
    {
        $this->clients->archive((int) $id, (int) Auth::id());
        redirect_with('/clientes', 'ok', 'Cliente arquivado.');
    }

    public function contact(Request $request, string $id): void
    {
        $this->clients->addContact((int) $id, $request->all(), (int) Auth::id());
        redirect_with('/clientes/' . $id, 'ok', 'Contato registrado.');
    }

    public function export(Request $request, string $id): void
    {
        json_out($this->clients->export((int) $id));
    }

    public function anonymize(Request $request, string $id): void
    {
        if ($request->str('confirm') !== 'REMOVER') {
            back_with_errors(['confirm' => 'Digite REMOVER para confirmar a exclusão dos dados pessoais.'], '/clientes/' . $id);
        }
        $this->clients->anonymize((int) $id, (int) Auth::id());
        redirect_with('/clientes/' . $id, 'ok', 'Dados pessoais removidos. Os registros financeiros foram preservados sem identificação.');
    }

    private function require(int $id): array
    {
        $client = $this->clients->find($id);
        if (!$client) {
            throw new NotFoundException();
        }
        return $client;
    }

    private function origins(): array
    {
        return Connection::all('SELECT * FROM client_origins WHERE active = 1 ORDER BY sort_order');
    }

    private function platforms(): array
    {
        return Connection::all('SELECT * FROM platforms WHERE active = 1 ORDER BY name');
    }
}
