<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\DocumentService;
use App\Services\FinanceService;
use App\Services\NotificationService;
use App\Services\ReservationService;
use App\Support\AuthException;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class ReservationController
{
    public function __construct(private ReservationService $reservations = new ReservationService())
    {
    }

    public function index(Request $request): void
    {
        view('reservations/index', [
            'title' => 'Reservas',
            'result' => $this->reservations->list([
                'q' => $request->query('q', ''),
                'status' => $request->query('status', ''),
                'from' => $request->query('from', ''),
                'to' => $request->query('to', ''),
                'vehicle_id' => $request->query('vehicle_id', ''),
            ], max(1, (int) $request->query('page', 1))),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL ORDER BY brand'),
        ]);
    }

    public function create(Request $request): void
    {
        view('reservations/form', $this->formData());
    }

    public function store(Request $request): void
    {
        if ($request->str('intent') === 'edit') {
            \App\Http\Session::putErrors([], $request->all());
            redirect('/reservas/nova');
        }
        $this->keepUploads();
        $normalized = $this->reservations->normalize($request->all());
        if ($request->str('intent') !== 'save') {
            view('reservations/summary', ['title' => 'Confirmar reserva', 'data' => $normalized, 'input' => $request->all()]);
            return;
        }
        $result = $this->reservations->create($request->all(), (int) Auth::id());
        $message = 'Reserva ' . $result['code'] . ' registrada.';
        if (!empty($result['documents_error'])) {
            $message .= ' Os documentos não foram gerados: ' . $result['documents_error'];
        }
        redirect_with('/reservas', empty($result['documents_error']) ? 'ok' : 'aviso', $message);
    }

    public function show(Request $request, string $id): void
    {
        $reservation = $this->require((int) $id);
        view('reservations/show', [
            'title' => $reservation['code'],
            'reservation' => $reservation,
            'items' => $this->reservations->items((int) $id),
            'events' => $this->reservations->events((int) $id),
            'payments' => Connection::all('SELECT p.*, m.name AS method_name FROM payments p LEFT JOIN payment_methods m ON m.id = p.method_id WHERE p.reservation_id = ? ORDER BY p.id DESC', [(int) $id]),
            'documents' => Connection::all('SELECT public_id, type, version, title, issued_at, redacted_at FROM documents WHERE reservation_id = ? ORDER BY id DESC', [(int) $id]),
            'methods' => Connection::all('SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort_order'),
            'vehicles' => Connection::all('SELECT id, brand, model FROM vehicles WHERE deleted_at IS NULL AND active = 1 ORDER BY brand'),
            'users' => Connection::all('SELECT id, name FROM users WHERE active = 1 AND deleted_at IS NULL ORDER BY name'),
            'fullDocument' => Auth::can('clients.sensitive'),
        ]);
    }

    public function whatsapp(Request $request, string $id): void
    {
        if (!Auth::can('reservations.view') && !Auth::can('agenda.view')) {
            throw new AuthException('Seu perfil não pode enviar esta mensagem.');
        }
        try {
            (new NotificationService())->sendReservation((int) $id, $request->all());
        } catch (ValidationException $exception) {
            json_out(['error' => implode(' ', $exception->errors)], 422);
        }
        json_out(['ok' => true]);
    }

    public function update(Request $request, string $id): void
    {
        $this->reservations->update((int) $id, $request->all(), (int) Auth::id());
        redirect_with('/reservas/' . $id, 'ok', 'Reserva atualizada.');
    }

    public function status(Request $request, string $id): void
    {
        $result = $this->reservations->changeStatus((int) $id, $request->str('status'), (int) Auth::id());
        redirect_with('/reservas/' . $id, empty($result['documents_error']) ? 'ok' : 'aviso', 'Situação atualizada.' . (!empty($result['documents_error']) ? ' ' . $result['documents_error'] : ''));
    }

    public function cancel(Request $request, string $id): void
    {
        $this->reservations->cancel((int) $id, $request->str('cancellation_reason'), (int) Auth::id());
        redirect_with('/reservas/' . $id, 'ok', 'Reserva cancelada. O histórico foi preservado.');
    }

    public function pay(Request $request, string $id): void
    {
        if (!Auth::can('reservations.manage') && !Auth::can('finance.manage')) {
            throw new AuthException('Seu perfil não pode registrar pagamentos.');
        }
        $reservation = $this->require((int) $id);
        $amount = parse_money($request->str('payment_amount'));
        if ($amount === null) {
            throw new ValidationException(['payment_amount' => 'Valor inválido.']);
        }
        $paymentId = (new FinanceService())->addPayment([
            'client_id' => (int) $reservation['client_id'],
            'reservation_id' => (int) $id,
            'amount_cents' => $amount,
            'method_id' => $request->int('payment_method_id'),
            'status' => 'confirmado',
            'origin' => 'manual',
            'paid_at' => ($request->str('payment_date') ?: today()) . ' 12:00:00',
            'installments' => max(1, $request->int('payment_installments')),
            'external_transaction_id' => $request->str('payment_external_id'),
            'notes' => $request->str('payment_notes'),
        ], (int) Auth::id());
        try {
            (new DocumentService())->issuePayment($paymentId, (int) Auth::id());
        } catch (\Throwable $exception) {
            redirect_with('/reservas/' . $id, 'aviso', 'Pagamento registrado, mas o recibo não foi gerado: ' . $exception->getMessage());
        }
        redirect_with('/reservas/' . $id, 'ok', 'Pagamento registrado e saldo atualizado.');
    }

    public function csv(Request $request, string $id): void
    {
        $reservation = $this->require((int) $id);
        $types = [
            'ficha_reserva' => 'Ficha de reserva',
            'comprovante_agendamento' => 'Comprovante de agendamento',
            'ordem_servico' => 'Ordem de serviço',
        ];
        $type = (string) $request->query('tipo', 'ficha_reserva');
        if (!isset($types[$type])) {
            $type = 'ficha_reserva';
        }
        $items = implode(' | ', array_map(static fn (array $item): string => $item['description'] . ' ' . money((int) $item['total_cents']), $this->reservations->items((int) $id)));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reservation['code'] . '-' . $type . '.csv"');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Campo', 'Valor'], ';', '"', '\\');
        foreach ([
            'Documento' => $types[$type],
            'Reserva' => (string) $reservation['code'],
            'Cliente' => (string) ($reservation['client_name'] ?? ''),
            'Veículo' => trim((string) (($reservation['brand'] ?? '') . ' ' . ($reservation['model'] ?? ''))),
            'Data' => (string) $reservation['scheduled_date'],
            'Horário' => substr((string) $reservation['start_time'], 0, 5),
            'Duração' => (string) $reservation['duration_minutes'],
            'Participantes' => (string) $reservation['participants'],
            'Origem' => (string) ($reservation['origin_name'] ?? ''),
            'Experiência' => $items,
            'Valor final' => money((int) $reservation['final_cents']),
            'Pago' => money((int) $reservation['paid_cents']),
            'Pendente' => money((int) $reservation['pending_cents']),
        ] as $field => $value) {
            fputcsv($out, [$field, $value], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function document(Request $request, string $id, string $key): void
    {
        $issued = (new DocumentService())->issueReservation($key, (int) $id, (int) Auth::id());
        redirect('/documentos/' . $issued['public_id'] . '?download=1');
    }

    private function require(int $id): array
    {
        $reservation = $this->reservations->find($id);
        if (!$reservation) {
            throw new NotFoundException();
        }
        return $reservation;
    }

    private function formData(): array
    {
        $prices = [];
        foreach (Connection::all('SELECT vehicle_id, modality_id, price_cents FROM vehicle_prices') as $row) {
            $prices[(int) $row['vehicle_id']][(int) $row['modality_id']] = (int) $row['price_cents'];
        }
        return [
            'title' => 'Nova reserva',
            'origins' => Connection::all('SELECT * FROM client_origins WHERE active = 1 ORDER BY sort_order'),
            'platforms' => Connection::all('SELECT * FROM platforms WHERE active = 1 ORDER BY name'),
            'modalities' => Connection::all('SELECT * FROM modalities WHERE active = 1 ORDER BY sort_order'),
            'vehicles' => Connection::all('SELECT * FROM vehicles WHERE deleted_at IS NULL AND active = 1 ORDER BY brand, model'),
            'users' => Connection::all('SELECT id, name FROM users WHERE active = 1 AND deleted_at IS NULL ORDER BY name'),
            'methods' => Connection::all('SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort_order'),
            'clients' => Connection::all('SELECT id, full_name, phone, email FROM clients WHERE archived_at IS NULL AND anonymized_at IS NULL ORDER BY full_name'),
            'partners' => Connection::all('SELECT id, name, commission_bps FROM partners WHERE active = 1 ORDER BY name'),
            'prices' => $prices,
        ];
    }

    private function keepUploads(): void
    {
        foreach (['client_cnh' => 'cnh_file_id', 'client_residence' => 'residence_file_id'] as $field => $target) {
            $file = $_FILES[$field] ?? null;
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                throw new ValidationException([$field => 'Não foi possível receber o arquivo.']);
            }
            if ((int) $file['size'] > 8 * 1024 * 1024) {
                throw new ValidationException([$field => 'O arquivo pode ter no máximo 8 MB.']);
            }
            $binary = file_get_contents((string) $file['tmp_name']);
            if (!is_string($binary) || $binary === '') {
                throw new ValidationException([$field => 'O arquivo chegou vazio.']);
            }
            $mime = $this->uploadMime($binary);
            if ($mime === null) {
                throw new ValidationException([$field => 'Envie a imagem em JPG, PNG ou WebP, ou um PDF.']);
            }
            $_POST[$target] = (string) Connection::insert(
                'INSERT INTO stored_files (mime, byte_size, content, created_at) VALUES (?, ?, ?, ?)',
                [$mime, strlen($binary), $binary, now()]
            );
        }
    }

    private function uploadMime(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (strlen($binary) >= 12 && str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (str_starts_with($binary, '%PDF')) {
            return 'application/pdf';
        }
        return null;
    }
}
