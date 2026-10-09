<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Database\Connection;
use App\Domain\Statuses;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class ReservationService
{
    public function __construct(
        private ClientService $clients = new ClientService(),
        private AvailabilityService $availability = new AvailabilityService(),
        private FinanceService $finance = new FinanceService(),
        private DocumentService $documents = new DocumentService()
    ) {
    }

    public function find(int $id): ?array
    {
        return Connection::one(
            'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone, c.email AS client_email, c.cpf AS client_cpf,
                    c.city AS client_city, c.state AS client_state, o.name AS origin_name, p.name AS platform_name,
                    v.brand, v.model, v.plate, v.capacity, u.name AS assignee_name, pa.name AS partner_name
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             LEFT JOIN client_origins o ON o.id = r.origin_id
             LEFT JOIN platforms p ON p.id = r.platform_id
             LEFT JOIN vehicles v ON v.id = r.vehicle_id
             LEFT JOIN users u ON u.id = r.assigned_user_id
             LEFT JOIN partners pa ON pa.id = r.partner_id
             WHERE r.id = ? AND r.deleted_at IS NULL',
            [$id]
        );
    }

    public function items(int $id): array
    {
        return Connection::all('SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id', [$id]);
    }

    public function events(int $id): array
    {
        return Connection::all(
            'SELECT e.*, u.name AS user_name FROM reservation_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.reservation_id = ? ORDER BY e.id DESC',
            [$id]
        );
    }

    public function list(array $filters, int $page = 1): array
    {
        $page = max(1, $page);
        $where = ['r.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'r.operational_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['financial'])) {
            $where[] = 'r.financial_status = ?';
            $params[] = $filters['financial'];
        }
        if (!empty($filters['vehicle_id'])) {
            $where[] = 'r.vehicle_id = ?';
            $params[] = (int) $filters['vehicle_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'r.scheduled_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'r.scheduled_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['q'])) {
            $like = like_term((string) $filters['q']);
            $where[] = '(c.full_name LIKE ? ESCAPE \'\\\' OR r.code LIKE ? ESCAPE \'\\\' OR r.external_transaction_id LIKE ? ESCAPE \'\\\')';
            array_push($params, $like, $like, $like);
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Connection::value("SELECT COUNT(*) FROM reservations r JOIN clients c ON c.id = r.client_id WHERE {$sqlWhere}", $params);
        $pages = max(1, (int) ceil($total / 15));
        $page = min($page, $pages);
        $rows = Connection::all(
            "SELECT r.*, c.full_name AS client_name, c.phone, c.whatsapp, c.email, v.brand, v.model,
                    (SELECT description FROM reservation_items WHERE reservation_id = r.id ORDER BY id LIMIT 1) AS experience
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             LEFT JOIN vehicles v ON v.id = r.vehicle_id
             WHERE {$sqlWhere}
             ORDER BY r.scheduled_date IS NULL, r.scheduled_date DESC, r.id DESC
             LIMIT 15 OFFSET " . (($page - 1) * 15),
            $params
        );
        return compact('rows', 'total', 'page', 'pages');
    }

    public function normalize(array $input): array
    {
        $errors = [];
        $mode = ($input['client_mode'] ?? 'new') === 'existing' ? 'existing' : 'new';
        $clientInput = [
            'mode' => $mode,
            'client_id' => (int) ($input['client_id'] ?? 0),
            'full_name' => $input['client_name'] ?? '',
            'cpf' => $input['client_cpf'] ?? '',
            'phone' => $this->phoneWithCode($input, 'client_phone_code', 'client_phone'),
            'whatsapp' => $this->phoneWithCode($input, 'client_whatsapp_code', 'client_whatsapp'),
            'email' => $input['client_email'] ?? '',
            'city' => $input['client_city'] ?? '',
            'state' => $input['client_state'] ?? '',
            'birth_date' => $input['client_birth'] ?? '',
            'origin_id' => $input['origin_id'] ?? null,
            'platform_id' => $input['platform_id'] ?? null,
            'external_id' => $input['external_transaction_id'] ?? '',
            'notes' => $input['client_notes'] ?? '',
            'instagram' => $input['client_instagram'] ?? '',
        ];
        if ($mode === 'existing') {
            $client = $this->clients->find($clientInput['client_id']);
            if (!$client || $client['archived_at'] || $client['anonymized_at']) {
                $errors['client_id'] = 'Selecione um cliente ativo.';
            }
        } else {
            $cnhFileId = (int) ($input['cnh_file_id'] ?? 0);
            if ($cnhFileId <= 0 || !Connection::one('SELECT id FROM stored_files WHERE id = ?', [$cnhFileId])) {
                $errors['client_cnh'] = 'Envie a CNH de quem vai fazer a reserva.';
            }
            $residenceFileId = (int) ($input['residence_file_id'] ?? 0);
            if ($residenceFileId > 0 && !Connection::one('SELECT id FROM stored_files WHERE id = ?', [$residenceFileId])) {
                $errors['client_residence'] = 'O comprovante de residência enviado não foi encontrado.';
            }
            try {
                $this->clients->check($clientInput);
                $duplicates = $this->clients->duplicates($clientInput);
                $confirm = (string) ($input['confirm_duplicate'] ?? '');
                if ($duplicates !== [] && $confirm === '') {
                    $errors['client_name'] = 'Já existe um cadastro compatível. Vincule o cliente ou confirme um novo cadastro.';
                    $errors['_duplicates'] = json_encode(array_map(static fn (array $row): array => [
                        'id' => (int) $row['id'],
                        'full_name' => $row['full_name'],
                        'phone' => $row['phone'],
                        'email' => $row['email'],
                        'cpf' => mask_cpf($row['cpf'], false),
                    ], $duplicates), JSON_UNESCAPED_UNICODE);
                }
                if ($confirm === 'link' && !in_array((int) ($input['link_client_id'] ?? 0), array_map(static fn (array $row): int => (int) $row['id'], $duplicates), true)) {
                    $errors['link_client_id'] = 'Escolha o cadastro que deve ser vinculado.';
                }
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors);
            }
        }

        $origin = Connection::one('SELECT * FROM client_origins WHERE id = ? AND active = 1', [(int) ($input['origin_id'] ?? 0)]);
        if (!$origin) {
            $errors['origin_id'] = 'Selecione a origem da venda.';
        }
        $platformId = (int) ($input['platform_id'] ?? 0) ?: null;
        $externalId = blank_to_null((string) ($input['external_transaction_id'] ?? ''));
        if ($origin && (int) $origin['requires_platform']) {
            if (!$platformId) {
                $errors['platform_id'] = 'Informe a plataforma.';
            }
            if ($externalId === null) {
                $errors['external_transaction_id'] = 'Informe o identificador da venda na plataforma.';
            }
        }
        $modalityIds = array_values(array_unique(array_map('intval', (array) ($input['modality_id'] ?? []))));
        $modalities = $modalityIds === [] ? [] : Connection::all(
            'SELECT * FROM modalities WHERE active = 1 AND id IN (' . implode(',', $modalityIds) . ')'
        );
        if (count($modalities) !== count(array_filter($modalityIds))) {
            $errors['modality_id'] = 'Selecione ao menos uma experiência ou produto válido.';
        }
        $experiences = array_values(array_filter($modalities, static fn (array $row): bool => $row['type'] === 'experiencia'));
        $vehicleId = (int) ($input['vehicle_id'] ?? 0) ?: null;
        $vehicle = $vehicleId ? Connection::one('SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL AND active = 1', [$vehicleId]) : null;
        $date = parse_date($input['scheduled_date'] ?? '');
        $time = parse_time($input['start_time'] ?? '');
        $needsSchedule = $experiences !== [];
        if ($needsSchedule && !$vehicle) {
            $errors['vehicle_id'] = 'Selecione o veículo da experiência.';
        }
        if ($needsSchedule && (!$date || !$time)) {
            $errors['scheduled_date'] = 'Informe data e horário da experiência.';
        }
        $items = [];
        $original = 0;
        foreach ($modalities as $modality) {
            $unit = $modality['type'] === 'experiencia'
                ? $this->vehiclePrice((int) $vehicleId, (int) $modality['id'])
                : (int) $modality['base_price_cents'];
            if ($unit === null) {
                $errors['modality_id'] = ($modality['name'] ?? 'Modalidade') . ' não está disponível para o veículo selecionado.';
                continue;
            }
            $items[] = [
                'modality_id' => (int) $modality['id'],
                'description' => $modality['name'],
                'type' => $modality['type'],
                'quantity' => 1,
                'unit_price_cents' => $unit,
                'total_cents' => $unit,
            ];
            $original += $unit;
        }
        $discountKind = (string) ($input['discount_kind'] ?? 'reais');
        if (!in_array($discountKind, ['reais', 'percent'], true)) {
            $discountKind = 'reais';
        }
        $discountBps = 0;
        if ($discountKind === 'percent') {
            $rawRate = trim((string) ($input['discount'] ?? ''));
            $discountBps = $rawRate === '' ? 0 : $this->percentBps($rawRate);
            if ($discountBps === null) {
                $errors['discount'] = 'Informe a porcentagem do desconto, de 0 a 100.';
                $discountBps = 0;
            }
            $discount = (int) round($original * $discountBps / 10000);
        } else {
            $discount = parse_money($input['discount'] ?? '0');
            if ($discount === null || $discount < 0) {
                $errors['discount'] = 'Desconto inválido.';
                $discount = 0;
            }
        }
        $fees = parse_money($input['additional_fees'] ?? '0');
        if ($fees === null || $fees < 0) {
            $errors['additional_fees'] = 'Taxa adicional inválida.';
            $fees = 0;
        }
        if ($discount > $original + (int) $fees) {
            $errors['discount'] = 'O desconto não pode ser maior que o valor da venda.';
        }
        $duration = $experiences === [] ? 0 : max(array_map(static fn (array $row): int => (int) $row['duration_minutes'], $experiences));
        if ($needsSchedule && $duration < 15) {
            $errors['duration_minutes'] = 'A duração mínima da experiência é de 15 minutos.';
        }
        $participants = max(0, (int) ($input['participants'] ?? 1));
        if ($needsSchedule && $participants < 1) {
            $errors['participants'] = 'Informe o número de participantes.';
        }
        if ($vehicle && $participants > (int) $vehicle['capacity']) {
            $errors['participants'] = 'A quantidade de participantes excede a capacidade do veículo (' . $vehicle['capacity'] . ').';
        }
        $status = (string) ($input['operational_status'] ?? 'aguardando_confirmacao');
        $allowed = ['novo', 'aguardando_dados', 'aguardando_confirmacao', 'confirmado', 'em_andamento'];
        if ($date && $date < today()) {
            $allowed[] = 'concluido';
        }
        if (!in_array($status, $allowed, true)) {
            $errors['operational_status'] = 'Situação inicial inválida.';
        }
        $assignee = (int) ($input['assigned_user_id'] ?? 0) ?: null;
        if ($assignee && !Connection::one('SELECT id FROM users WHERE id = ? AND active = 1 AND deleted_at IS NULL', [$assignee])) {
            $errors['assigned_user_id'] = 'Responsável inválido.';
        }
        $starts = null;
        $ends = null;
        if ($vehicle && $date && $time && $duration >= 15) {
            $starts = $date . ' ' . $time . ':00';
            $ends = add_minutes($starts, $duration);
        }
        $paymentAmount = parse_money($input['payment_amount'] ?? '0');
        if ($paymentAmount === null || $paymentAmount < 0) {
            $errors['payment_amount'] = 'Valor do pagamento inválido.';
            $paymentAmount = 0;
        }
        $paymentPlan = (string) ($input['payment_plan'] ?? '');
        if ($paymentPlan !== '' && !in_array($paymentPlan, ['total', 'slip', 'convenio'], true)) {
            $errors['payment_plan'] = 'Selecione como o valor será pago.';
        }
        $partnerMode = (string) ($input['partner_mode'] ?? 'none');
        if (!in_array($partnerMode, ['none', 'existing', 'new'], true)) {
            $partnerMode = 'none';
        }
        $partnerId = (int) ($input['partner_id'] ?? 0) ?: null;
        $partner = null;
        $commissionBps = 0;
        if ($partnerMode === 'existing') {
            $partner = $partnerId ? Connection::one('SELECT * FROM partners WHERE id = ? AND active = 1', [$partnerId]) : null;
            if (!$partner) {
                $errors['partner_id'] = 'Selecione o parceiro.';
            } else {
                $commissionBps = (int) $partner['commission_bps'];
            }
        } elseif ($partnerMode === 'new') {
            $partnerName = person_name((string) ($input['partner_name'] ?? ''));
            $commissionBps = $this->percentBps((string) ($input['partner_commission'] ?? ''));
            if (mb_strlen($partnerName) < 3) {
                $errors['partner_name'] = 'Informe o nome do parceiro.';
            }
            if ($commissionBps === null || $commissionBps <= 0) {
                $errors['partner_commission'] = 'Informe a comissão do parceiro, em porcentagem.';
                $commissionBps = 0;
            }
            $partnerEmail = mb_strtolower(trim((string) ($input['partner_email'] ?? '')));
            if ($partnerEmail !== '' && !filter_var($partnerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['partner_email'] = 'E-mail do parceiro inválido.';
            }
            $partner = [
                'name' => $partnerName,
                'phone' => $this->phoneWithCode($input, 'partner_phone_code', 'partner_phone'),
                'email' => $partnerEmail,
                'instagram' => ltrim(trim((string) ($input['partner_instagram'] ?? '')), '@'),
                'commission_bps' => (int) $commissionBps,
            ];
        }
        foreach (['platform_gross', 'platform_fee', 'platform_net'] as $moneyField) {
            if (parse_money($input[$moneyField] ?? '0') === null) {
                $errors[$moneyField] = 'Valor inválido.';
            }
        }
        $gross = (int) (parse_money($input['platform_gross'] ?? '0') ?? 0);
        $fee = (int) (parse_money($input['platform_fee'] ?? '0') ?? 0);
        $net = (int) (parse_money($input['platform_net'] ?? '0') ?? 0);
        $final = $original - (int) $discount + (int) $fees;
        $methodId = (int) ($input['payment_method_id'] ?? 0) ?: null;
        if ($paymentPlan === 'convenio') {
            $final = 0;
            $paymentAmount = 0;
            $methodId = null;
        } elseif ($paymentPlan === 'total') {
            $paymentAmount = max(0, $final);
            if ($final > 0 && !$methodId) {
                $errors['payment_method_id'] = 'Selecione a forma de pagamento.';
            }
        } elseif ($paymentPlan === 'slip') {
            if ($final <= 0) {
                $errors['payment_amount'] = 'Não há valor para dividir.';
            } elseif ((int) $paymentAmount <= 0 || (int) $paymentAmount >= $final) {
                $errors['payment_amount'] = 'No slip, informe a parte paga agora, menor que o total. O restante fica pendente.';
            }
            if ((int) $paymentAmount > 0 && !$methodId) {
                $errors['payment_method_id'] = 'Selecione a forma de pagamento.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $commissionCents = (int) round($final * (int) $commissionBps / 10000);
        return [
            'client_mode' => $mode,
            'client_id' => (int) ($input['client_id'] ?? 0),
            'client' => $clientInput,
            'confirm_duplicate' => (string) ($input['confirm_duplicate'] ?? ''),
            'link_client_id' => (int) ($input['link_client_id'] ?? 0),
            'origin_id' => (int) $origin['id'],
            'platform_id' => $platformId,
            'external_transaction_id' => $externalId,
            'external_reservation_code' => blank_to_null((string) ($input['external_reservation_code'] ?? '')),
            'platform_gross_cents' => $gross,
            'platform_fee_cents' => $fee,
            'platform_net_cents' => $net,
            'platform_reported_paid_cents' => 0,
            'items' => $items,
            'vehicle_id' => $vehicle ? (int) $vehicle['id'] : null,
            'scheduled_date' => $starts ? $date : null,
            'start_time' => $starts ? $time : null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'duration_minutes' => $starts ? $duration : 0,
            'participants' => max(1, $participants),
            'assigned_user_id' => $assignee,
            'notes' => blank_to_null((string) ($input['notes'] ?? '')),
            'operational_status' => $status,
            'discount_cents' => (int) $discount,
            'discount_kind' => $discountKind,
            'discount_bps' => (int) $discountBps,
            'fees_cents' => (int) $fees,
            'original_cents' => $original,
            'final_cents' => $final,
            'payment_cents' => (int) $paymentAmount,
            'payment_plan' => $paymentPlan,
            'payment_method_id' => $methodId,
            'payment_date' => parse_date($input['payment_date'] ?? '') ?: today(),
            'payment_installments' => max(1, (int) ($input['payment_installments'] ?? 1)),
            'payment_external_id' => blank_to_null((string) ($input['payment_external_id'] ?? '')),
            'payment_notes' => blank_to_null((string) ($input['payment_notes'] ?? '')),
            'register_platform_payment' => !empty($input['register_platform_payment']),
            'allow_outside_hours' => !empty($input['outside_hours']),
            'is_demo' => !empty($input['is_demo']),
            'client_name' => $mode === 'existing' ? (string) ($client['full_name'] ?? '') : (string) $clientInput['full_name'],
            'vehicle_name' => $vehicle ? $vehicle['brand'] . ' ' . $vehicle['model'] : '—',
            'origin_name' => (string) $origin['name'],
            'partner_mode' => $partnerMode,
            'partner_id' => $partnerMode === 'existing' ? (int) ($partner['id'] ?? 0) : null,
            'partner_new' => $partnerMode === 'new' ? $partner : null,
            'partner_name' => $partnerMode === 'none' ? '' : (string) ($partner['name'] ?? ''),
            'commission_bps' => (int) $commissionBps,
            'commission_cents' => $commissionCents,
            'cnh_file_id' => $mode === 'new' ? (int) ($input['cnh_file_id'] ?? 0) : 0,
            'residence_file_id' => $mode === 'new' ? (int) ($input['residence_file_id'] ?? 0) : 0,
            'instagram' => (string) ($clientInput['instagram'] ?? ''),
        ];
    }

    public function create(array $input, int $userId): array
    {
        $data = $this->normalize($input);
        $result = Connection::transaction(fn (): array => $this->persist($data, $userId));
        return $this->afterSave($result, $userId);
    }

    public function update(int $id, array $input, int $userId): void
    {
        $current = $this->find($id);
        if (!$current) {
            throw new NotFoundException('Reserva não encontrada.');
        }
        if (in_array($current['operational_status'], ['cancelado', 'concluido'], true) && (Auth::user()['role_slug'] ?? '') !== 'administrador') {
            throw new ValidationException(['scheduled_date' => 'Esta reserva não pode mais ser alterada.']);
        }
        $date = parse_date($input['scheduled_date'] ?? $current['scheduled_date']);
        $time = parse_time($input['start_time'] ?? $current['start_time']);
        $vehicleId = (int) ($input['vehicle_id'] ?? $current['vehicle_id']);
        $duration = (int) ($input['duration_minutes'] ?? $current['duration_minutes']);
        $participants = (int) ($input['participants'] ?? $current['participants']);
        $vehicle = Connection::one('SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL AND active = 1', [$vehicleId]);
        $errors = [];
        if (!$vehicle) {
            $errors['vehicle_id'] = 'Selecione um veículo ativo.';
        }
        if (!$date || !$time) {
            $errors['scheduled_date'] = 'Informe data e horário.';
        }
        if ($duration < 15) {
            $errors['duration_minutes'] = 'A duração mínima é de 15 minutos.';
        }
        if ($vehicle && $participants > (int) $vehicle['capacity']) {
            $errors['participants'] = 'Participantes acima da capacidade do veículo.';
        }
        $discount = array_key_exists('discount', $input) ? parse_money((string) $input['discount']) : (int) $current['discount_cents'];
        $fees = array_key_exists('additional_fees', $input) ? parse_money((string) $input['additional_fees']) : (int) $current['fees_cents'];
        if ($discount === null || $fees === null) {
            $errors['discount'] = 'Valores inválidos.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $starts = $date . ' ' . $time . ':00';
        $ends = add_minutes($starts, $duration);
        $final = (int) $current['original_cents'] - (int) $discount + (int) $fees;
        if ($final < 0) {
            throw new ValidationException(['discount' => 'O desconto não pode zerar a reserva abaixo de zero.']);
        }
        Connection::transaction(function () use ($vehicleId, $starts, $ends, $id, $input, $current, $date, $time, $duration, $participants, $discount, $fees, $final, $userId): void {
            $this->availability->assertBookable($vehicleId, $starts, $ends, $id, !empty($input['outside_hours']));
            Connection::run(
                'UPDATE reservations SET vehicle_id = ?, scheduled_date = ?, start_time = ?, starts_at = ?, ends_at = ?, duration_minutes = ?, participants = ?, assigned_user_id = ?, notes = ?, discount_cents = ?, fees_cents = ?, final_cents = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                [
                    $vehicleId, $date, $time, $starts, $ends, $duration, $participants,
                    (int) ($input['assigned_user_id'] ?? 0) ?: null,
                    blank_to_null((string) ($input['notes'] ?? '')),
                    (int) $discount, (int) $fees, $final, $userId, now(), $id,
                ]
            );
            $this->finance->recalculate($id);
            $this->event($id, 'updated', (string) $current['operational_status'], (string) $current['operational_status'], 'Reserva alterada', $userId);
        });
        activity('reserva', 'Reserva ' . $current['code'] . ' alterada', 'reservation', $id);
        audit('update', 'reservation', $id, ['code' => $current['code']]);
    }

    public function changeStatus(int $id, string $status, int $userId, ?string $reason = null): array
    {
        $current = $this->find($id);
        if (!$current) {
            throw new NotFoundException('Reserva não encontrada.');
        }
        $from = (string) $current['operational_status'];
        $allowed = Statuses::TRANSITIONS[$from] ?? [];
        $admin = (Auth::user()['role_slug'] ?? '') === 'administrador';
        if ($admin && $from === 'concluido' && $status === 'confirmado') {
            $allowed[] = 'confirmado';
        }
        if ($admin && $from === 'cancelado' && $status === 'aguardando_confirmacao') {
            $allowed[] = 'aguardando_confirmacao';
        }
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['status' => 'Esta mudança de situação não é permitida.']);
        }
        if ($status === 'cancelado') {
            return $this->cancel($id, (string) $reason, $userId);
        }
        Connection::run(
            'UPDATE reservations SET operational_status = ?, completed_at = ?, updated_by = ?, updated_at = ? WHERE id = ?',
            [$status, $status === 'concluido' ? now() : $current['completed_at'], $userId, now(), $id]
        );
        $this->event($id, 'status', $from, $status, 'Situação alterada para ' . Statuses::label('operational', $status), $userId);
        activity('reserva', 'Reserva ' . $current['code'] . ' marcada como ' . Statuses::label('operational', $status), 'reservation', $id);
        $warning = null;
        if (in_array($status, ['confirmado', 'concluido'], true)) {
            $warning = $this->issueOperationalDocuments($id, $userId);
        }
        return ['id' => $id, 'code' => $current['code'], 'documents_error' => $warning];
    }

    public function cancel(int $id, string $reason, int $userId): array
    {
        $current = $this->find($id);
        if (!$current) {
            throw new NotFoundException('Reserva não encontrada.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new ValidationException(['cancellation_reason' => 'Informe o motivo do cancelamento.']);
        }
        $from = (string) $current['operational_status'];
        if ($from === 'cancelado') {
            throw new ValidationException(['status' => 'A reserva já está cancelada.']);
        }
        Connection::run(
            'UPDATE reservations SET operational_status = ?, cancellation_reason = ?, cancelled_at = ?, updated_by = ?, updated_at = ? WHERE id = ?',
            ['cancelado', $reason, now(), $userId, now(), $id]
        );
        $this->event($id, 'cancelled', $from, 'cancelado', $reason, $userId);
        activity('reserva', 'Reserva ' . $current['code'] . ' cancelada', 'reservation', $id);
        audit('cancel', 'reservation', $id, ['code' => $current['code']]);
        return ['id' => $id, 'code' => $current['code'], 'documents_error' => null];
    }

    public function recordExternal(array $draft, int $userId, bool $dry = false): array
    {
        $externalId = blank_to_null((string) ($draft['external_id'] ?? ''));
        $platformId = (int) ($draft['platform_id'] ?? 0);
        if ($externalId === null || $platformId <= 0) {
            return ['status' => 'error', 'message' => 'Identificador externo e plataforma são obrigatórios.'];
        }
        $existing = Connection::one(
            'SELECT id, code FROM reservations WHERE platform_id = ? AND external_transaction_id = ? AND deleted_at IS NULL',
            [$platformId, $externalId]
        );
        if ($existing) {
            return ['status' => 'duplicate', 'message' => 'Venda já importada.', 'id' => (int) $existing['id'], 'code' => $existing['code']];
        }
        $modality = $this->matchByName('modalities', (string) ($draft['product'] ?? ''));
        if (!$modality) {
            return ['status' => 'error', 'message' => 'Produto não reconhecido: ' . ($draft['product'] ?? 'vazio')];
        }
        $vehicle = null;
        if (trim((string) ($draft['vehicle'] ?? '')) !== '') {
            $vehicle = $this->matchVehicle((string) $draft['vehicle']);
            if (!$vehicle) {
                return ['status' => 'error', 'message' => 'Veículo não reconhecido ou ambíguo: ' . $draft['vehicle']];
            }
        }
        if ($modality['type'] === 'experiencia' && !$vehicle && !empty($draft['scheduled_date'])) {
            return ['status' => 'error', 'message' => 'A experiência precisa de um veículo reconhecido.'];
        }
        $customer = (array) ($draft['customer'] ?? []);
        $match = $this->clients->matchExternal([
            'full_name' => $customer['name'] ?? '',
            'cpf' => $customer['cpf'] ?? '',
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? '',
        ]);
        if (in_array($match['status'], ['conflict', 'ambiguous'], true)) {
            return ['status' => 'error', 'message' => $match['message']];
        }
        $date = parse_date($draft['scheduled_date'] ?? '');
        $time = parse_time($draft['start_time'] ?? '');
        if ($dry) {
            return ['status' => 'ready', 'message' => $match['status'] === 'one' ? 'Cliente existente será vinculado.' : 'Novo cliente será cadastrado.'];
        }
        try {
            $originId = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'plataforma'");
            $unit = $modality['type'] === 'experiencia' && $vehicle
                ? $this->vehiclePrice((int) $vehicle['id'], (int) $modality['id'])
                : (int) $modality['base_price_cents'];
            if ($unit === null) {
                return ['status' => 'error', 'message' => 'A modalidade não é oferecida para o veículo informado.'];
            }
            $gross = (int) ($draft['gross_cents'] ?? 0);
            $final = $gross > 0 ? $gross : $unit;
            $duration = max(15, (int) $modality['duration_minutes']);
            $starts = null;
            $ends = null;
            $status = 'aguardando_dados';
            if ($vehicle && $date && $time && $modality['type'] === 'experiencia') {
                $starts = $date . ' ' . $time . ':00';
                $ends = add_minutes($starts, $duration);
                $status = 'aguardando_confirmacao';
            }
            $clientPayload = [
                'mode' => 'new',
                'client_id' => (int) ($match['id'] ?? 0),
                'full_name' => $customer['name'] ?? '',
                'cpf' => $customer['cpf'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'whatsapp' => $customer['phone'] ?? '',
                'email' => $customer['email'] ?? '',
                'city' => $customer['city'] ?? '',
                'state' => $customer['state'] ?? '',
                'origin_id' => $originId,
                'platform_id' => $platformId,
                'external_id' => $customer['external_id'] ?? '',
            ];
            $data = [
                'client_mode' => 'new',
                'client_id' => (int) ($match['id'] ?? 0),
                'client' => $clientPayload,
                'confirm_duplicate' => $match['status'] === 'one' ? 'link' : 'create',
                'link_client_id' => (int) ($match['id'] ?? 0),
                'origin_id' => $originId,
                'platform_id' => $platformId,
                'external_transaction_id' => $externalId,
                'external_reservation_code' => blank_to_null((string) ($draft['external_reservation_code'] ?? '')),
                'platform_gross_cents' => $gross,
                'platform_fee_cents' => (int) ($draft['fee_cents'] ?? 0),
                'platform_net_cents' => (int) ($draft['net_cents'] ?? 0),
                'platform_reported_paid_cents' => (int) ($draft['reported_paid_cents'] ?? 0),
                'items' => [[
                    'modality_id' => (int) $modality['id'],
                    'description' => $modality['name'],
                    'type' => $modality['type'],
                    'quantity' => 1,
                    'unit_price_cents' => $final,
                    'total_cents' => $final,
                ]],
                'vehicle_id' => $vehicle ? (int) $vehicle['id'] : null,
                'scheduled_date' => $starts ? $date : null,
                'start_time' => $starts ? $time : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'duration_minutes' => $starts ? $duration : 0,
                'participants' => max(1, (int) ($draft['participants'] ?? 1)),
                'assigned_user_id' => null,
                'notes' => blank_to_null((string) ($draft['notes'] ?? '')),
                'operational_status' => $status,
                'discount_cents' => 0,
                'fees_cents' => 0,
                'original_cents' => $final,
                'final_cents' => $final,
                'payment_cents' => 0,
                'register_platform_payment' => false,
                'allow_outside_hours' => $date !== null && $date < today(),
                'is_demo' => false,
            ];
            if ($vehicle && $participants = (int) $data['participants']) {
                if ($participants > (int) $vehicle['capacity']) {
                    return ['status' => 'error', 'message' => 'Participantes acima da capacidade do veículo.'];
                }
            }
            $saved = Connection::transaction(function () use ($data, $userId, $draft, $platformId): array {
                $result = $this->persist($data, $userId);
                $platform = Connection::one('SELECT trust_payment FROM platforms WHERE id = ?', [$platformId]);
                if ($platform && (int) $platform['trust_payment'] === 1 && (int) ($draft['reported_paid_cents'] ?? 0) > 0) {
                    $this->finance->addPayment([
                        'client_id' => $result['client_id'],
                        'reservation_id' => $result['id'],
                        'amount_cents' => (int) $draft['reported_paid_cents'],
                        'origin' => 'plataforma',
                        'status' => 'confirmado',
                        'external_transaction_id' => $data['external_transaction_id'],
                        'notes' => 'Pagamento aceito porque a plataforma está configurada para confiar no status financeiro.',
                    ], $userId);
                }
                return $result;
            });
            $saved = $this->afterSave($saved, $userId);
            $saved['status'] = 'created';
            $trusted = (int) Connection::value('SELECT trust_payment FROM platforms WHERE id = ?', [$platformId]) === 1;
            if ((int) ($draft['reported_paid_cents'] ?? 0) > 0 && !$trusted) {
                $saved['message'] = 'A plataforma informou pagamento, mas ele não foi contabilizado automaticamente.';
            }
            return $saved;
        } catch (ValidationException $exception) {
            return ['status' => 'error', 'message' => implode(' ', $exception->errors)];
        }
    }

    private function persist(array $data, int $userId): array
    {
        $data['payment_cents'] = (int) ($data['payment_cents'] ?? 0);
        $data['payment_plan'] = (string) ($data['payment_plan'] ?? '');
        $data['commission_bps'] = (int) ($data['commission_bps'] ?? 0);
        $data['commission_cents'] = (int) ($data['commission_cents'] ?? 0);
        $data['partner_id'] = $data['partner_id'] ?? null;
        $data['partner_new'] = $data['partner_new'] ?? null;
        $data['cnh_file_id'] = (int) ($data['cnh_file_id'] ?? 0);
        $data['residence_file_id'] = (int) ($data['residence_file_id'] ?? 0);
        $data['instagram'] = (string) ($data['instagram'] ?? ($data['client']['instagram'] ?? ''));
        $data['payment_method_id'] = $data['payment_method_id'] ?? null;
        $data['payment_date'] = $data['payment_date'] ?? today();
        $data['payment_installments'] = (int) ($data['payment_installments'] ?? 1);
        $data['payment_external_id'] = $data['payment_external_id'] ?? null;
        $data['payment_notes'] = $data['payment_notes'] ?? null;
        $data['register_platform_payment'] = !empty($data['register_platform_payment']);
        $data['allow_outside_hours'] = !empty($data['allow_outside_hours']);
        if ($data['external_transaction_id'] && $data['platform_id']) {
            $duplicate = Connection::one(
                'SELECT id, code FROM reservations WHERE platform_id = ? AND external_transaction_id = ? AND deleted_at IS NULL',
                [$data['platform_id'], $data['external_transaction_id']]
            );
            if ($duplicate) {
                return ['status' => 'duplicate', 'id' => (int) $duplicate['id'], 'code' => $duplicate['code'], 'client_id' => 0];
            }
        }
        if ($data['client_mode'] === 'existing') {
            $clientId = $this->clients->resolve($data['client'], '', null, $userId);
        } elseif ($data['confirm_duplicate'] === 'link') {
            $clientId = $this->clients->resolve($data['client'], 'link', $data['link_client_id'], $userId);
        } else {
            $clientId = $this->clients->resolve($data['client'], $data['confirm_duplicate'] === 'create' ? 'create' : '', null, $userId);
        }
        if ($data['client_mode'] !== 'existing') {
            Connection::run(
                'UPDATE clients SET instagram = COALESCE(?, instagram), cnh_file_id = COALESCE(NULLIF(?, 0), cnh_file_id), residence_file_id = COALESCE(NULLIF(?, 0), residence_file_id), updated_at = ? WHERE id = ?',
                [
                    $data['instagram'] !== '' ? $data['instagram'] : null,
                    (int) $data['cnh_file_id'],
                    (int) $data['residence_file_id'],
                    now(),
                    $clientId,
                ]
            );
        }
        $partnerId = $data['partner_id'] ?? null;
        if (!empty($data['partner_new'])) {
            $created = $data['partner_new'];
            $partnerId = Connection::insert(
                'INSERT INTO partners (name, phone, email, instagram, commission_bps, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
                [
                    $created['name'],
                    blank_to_null((string) $created['phone']),
                    $created['email'] !== '' ? $created['email'] : null,
                    $created['instagram'] !== '' ? $created['instagram'] : null,
                    (int) $created['commission_bps'],
                    now(),
                    now(),
                ]
            );
        }
        if ($data['starts_at'] && $data['vehicle_id']) {
            $this->availability->assertBookable((int) $data['vehicle_id'], $data['starts_at'], $data['ends_at'], null, (bool) $data['allow_outside_hours']);
        }
        $number = Sequence::next('reservation-' . date('Y'));
        $code = sprintf('DX-%s-%05d', date('Y'), $number);
        $stamp = now();
        $financial = $this->finance->financialStatus((int) $data['final_cents'], 0);
        $id = Connection::insert(
            'INSERT INTO reservations (public_id, code, client_id, origin_id, platform_id, external_transaction_id, external_reservation_code, platform_gross_cents, platform_fee_cents, platform_net_cents, platform_reported_paid_cents, operational_status, financial_status, vehicle_id, scheduled_date, start_time, starts_at, ends_at, duration_minutes, participants, assigned_user_id, notes, original_cents, discount_cents, fees_cents, final_cents, paid_cents, pending_cents, is_demo, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)',
            [
                bin2hex(random_bytes(16)), $code, $clientId, $data['origin_id'], $data['platform_id'],
                $data['external_transaction_id'], $data['external_reservation_code'],
                $data['platform_gross_cents'], $data['platform_fee_cents'], $data['platform_net_cents'], $data['platform_reported_paid_cents'],
                $data['operational_status'], $financial, $data['vehicle_id'], $data['scheduled_date'], $data['start_time'],
                $data['starts_at'], $data['ends_at'], $data['duration_minutes'], $data['participants'], $data['assigned_user_id'],
                $data['notes'], $data['original_cents'], $data['discount_cents'], $data['fees_cents'], $data['final_cents'],
                $data['final_cents'], !empty($data['is_demo']) ? 1 : 0, $userId, $userId, $stamp, $stamp,
            ]
        );
        foreach ($data['items'] as $item) {
            Connection::insert(
                'INSERT INTO reservation_items (reservation_id, modality_id, description, quantity, unit_price_cents, total_cents) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $item['modality_id'], $item['description'], $item['quantity'], $item['unit_price_cents'], $item['total_cents']]
            );
            if (($item['type'] ?? '') === 'foto') {
                Connection::insert(
                    'INSERT INTO productions (public_id, client_id, reservation_id, modality_id, amount_cents, contracted_at, status, is_demo, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [bin2hex(random_bytes(16)), $clientId, $id, $item['modality_id'], $item['total_cents'], $stamp, 'nao_iniciado', !empty($data['is_demo']) ? 1 : 0, $userId, $userId, $stamp, $stamp]
                );
            }
        }
        if ((int) $data['payment_cents'] > 0) {
            $this->finance->addPayment([
                'client_id' => $clientId,
                'reservation_id' => $id,
                'amount_cents' => (int) $data['payment_cents'],
                'method_id' => $data['payment_method_id'],
                'status' => 'confirmado',
                'origin' => 'manual',
                'paid_at' => $data['payment_date'] . ' 12:00:00',
                'installments' => $data['payment_installments'],
                'external_transaction_id' => $data['payment_external_id'],
                'notes' => $data['payment_notes'],
            ], $userId);
        } else {
            $this->finance->recalculate($id);
        }
        if (!empty($data['register_platform_payment']) && (int) $data['platform_net_cents'] > 0) {
            $this->finance->addPayment([
                'client_id' => $clientId,
                'reservation_id' => $id,
                'amount_cents' => (int) $data['platform_net_cents'],
                'status' => 'confirmado',
                'origin' => 'plataforma',
                'notes' => 'Valor líquido informado manualmente no cadastro da venda.',
                'external_transaction_id' => $data['external_transaction_id'],
            ], $userId);
        }
        Connection::run(
            'UPDATE reservations SET partner_id = ?, commission_bps = ?, commission_cents = ?, payment_plan = ? WHERE id = ?',
            [$partnerId ?: null, (int) $data['commission_bps'], (int) $data['commission_cents'], $data['payment_plan'] !== '' ? $data['payment_plan'] : null, $id]
        );
        $this->event($id, 'created', null, $data['operational_status'], 'Reserva criada', $userId);
        activity('reserva', 'Reserva ' . $code . ' criada', 'reservation', $id);
        audit('create', 'reservation', $id, ['code' => $code]);
        return [
            'status' => 'created',
            'id' => $id,
            'code' => $code,
            'client_id' => $clientId,
            'operational_status' => $data['operational_status'],
        ];
    }

    private function afterSave(array $result, int $userId): array
    {
        $result['documents_error'] = null;
        if (($result['status'] ?? '') === 'created' && in_array($result['operational_status'] ?? '', ['confirmado', 'concluido'], true)) {
            $result['documents_error'] = $this->issueOperationalDocuments((int) $result['id'], $userId);
        }
        return $result;
    }

    private function issueOperationalDocuments(int $id, int $userId): ?string
    {
        try {
            foreach (['ficha_reserva', 'comprovante_agendamento', 'ordem_servico', 'termo_responsabilidade'] as $type) {
                $this->documents->issueReservation($type, $id, $userId);
            }
        } catch (\Throwable $exception) {
            error_log('Falha ao emitir documentos da reserva ' . $id);
            return $exception->getMessage();
        }
        return null;
    }

    private function vehiclePrice(int $vehicleId, int $modalityId): ?int
    {
        if ($vehicleId <= 0) {
            return null;
        }
        $price = Connection::value(
            'SELECT price_cents FROM vehicle_prices WHERE vehicle_id = ? AND modality_id = ?',
            [$vehicleId, $modalityId]
        );
        return $price === null ? null : (int) $price;
    }

    private function matchByName(string $table, string $name): ?array
    {
        $needle = fold($name);
        if ($needle === '' || $table !== 'modalities') {
            return null;
        }
        $exact = [];
        $partial = [];
        foreach (Connection::all('SELECT * FROM modalities WHERE active = 1') as $row) {
            $label = fold((string) $row['name']);
            $slug = fold((string) $row['slug']);
            if ($needle === $label || $needle === $slug) {
                $exact[] = $row;
            } elseif (str_contains($label, $needle) || str_contains($needle, $label)) {
                $partial[] = $row;
            }
        }
        if (count($exact) === 1) {
            return $exact[0];
        }
        return count($exact) === 0 && count($partial) === 1 ? $partial[0] : null;
    }

    private function matchVehicle(string $name): ?array
    {
        $needle = fold($name);
        $hits = [];
        foreach (Connection::all('SELECT * FROM vehicles WHERE deleted_at IS NULL AND active = 1') as $row) {
            $label = fold($row['brand'] . ' ' . $row['model']);
            if ($needle === $label || str_contains($label, $needle) || str_contains($needle, $label)) {
                $hits[] = $row;
            }
        }
        return count($hits) === 1 ? $hits[0] : null;
    }

    private function phoneWithCode(array $input, string $codeKey, string $numberKey): string
    {
        $raw = trim((string) ($input[$numberKey] ?? ''));
        if ($raw === '') {
            return '';
        }
        $code = only_digits((string) ($input[$codeKey] ?? '55')) ?: '55';
        if ($code === '55') {
            return phone_digits($raw);
        }
        return '+' . $code . ' ' . $raw;
    }

    private function percentBps(string $value): ?int
    {
        $value = trim(str_replace('%', '', $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) {
            return null;
        }
        $bps = (int) round(((float) $value) * 100);
        if ($bps < 0 || $bps > 10000) {
            return null;
        }
        return $bps;
    }

    private function event(int $id, string $type, ?string $from, ?string $to, string $message, int $userId): void
    {
        Connection::insert(
            'INSERT INTO reservation_events (reservation_id, event_type, from_status, to_status, message, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $type, $from, $to, $message, $userId, now()]
        );
    }
}
