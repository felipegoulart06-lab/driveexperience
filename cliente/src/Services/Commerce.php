<?php

declare(strict_types=1);

namespace Cliente\Services;

use App\Database\Connection;
use App\Services\AvailabilityService;
use App\Services\Sequence;
use App\Support\ValidationException;

final class Commerce
{
    public function bookExperience(int $clientId, int $vehicleId, int $modalityId, string $date, string $time, bool $demoClient): array
    {
        $offer = (new Catalog())->price($vehicleId, $modalityId);
        if (!$offer) {
            throw new ValidationException(['modality' => 'Esta experiência não está disponível.']);
        }
        $parsedDate = $date !== '' ? parse_date($date) : null;
        $parsedTime = $time !== '' ? parse_time($time) : null;
        if (($date !== '' && !$parsedDate) || ($time !== '' && !$parsedTime)) {
            throw new ValidationException(['date' => 'Data ou horário inválido.']);
        }
        if (($parsedDate && !$parsedTime) || (!$parsedDate && $parsedTime)) {
            throw new ValidationException(['date' => 'Informe a data e o horário, ou deixe os dois em branco para agendar depois.']);
        }
        $starts = null;
        $ends = null;
        $duration = (int) $offer['duration_minutes'];
        if ($parsedDate && $parsedTime) {
            if ($duration < 15) {
                throw new ValidationException(['date' => 'Esta modalidade ainda não tem duração para a agenda.']);
            }
            $starts = $parsedDate . ' ' . $parsedTime . ':00';
            $ends = add_minutes($starts, $duration);
            (new AvailabilityService())->assertBookable($vehicleId, $starts, $ends);
        }
        $cents = (int) $offer['price_cents'];
        $stamp = now();
        return Connection::transaction(function () use ($clientId, $vehicleId, $modalityId, $offer, $cents, $parsedDate, $parsedTime, $starts, $ends, $duration, $demoClient, $stamp): array {
            $reservationId = $this->createReservation($clientId, $vehicleId, $modalityId, $offer, $cents, $parsedDate, $parsedTime, $starts, $ends, $duration, $demoClient, $stamp);
            $orderCode = $this->orderCode();
            $orderId = Connection::insert(
                'INSERT INTO client_orders (public_id, code, client_id, kind, status, subtotal_cents, discount_cents, freight_cents, total_cents, reservation_id, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?)',
                [bin2hex(random_bytes(16)), $orderCode, $clientId, 'experiencia', 'aguardando_pagamento', $cents, $cents, $reservationId, $demoClient ? 1 : 0, $stamp, $stamp]
            );
            Connection::insert(
                'INSERT INTO client_order_items (order_id, description, quantity, unit_cents, total_cents, vehicle_id, modality_id) VALUES (?, ?, 1, ?, ?, ?, ?)',
                [$orderId, $offer['name'] . ' · ' . $offer['brand'] . ' ' . $offer['model'], $cents, $cents, $vehicleId, $modalityId]
            );
            $this->notify($clientId, 'Pedido registrado', 'O pedido ' . $orderCode . ' aguarda a confirmação do pagamento.', 'operacional');
            return ['code' => $orderCode, 'reservation' => $reservationId];
        });
    }

    public function checkoutCart(int $clientId, array $cart, string $couponCode, bool $demo): string
    {
        if ($cart === []) {
            throw new ValidationException(['cart' => 'O carrinho está vazio.']);
        }
        $stamp = now();
        return Connection::transaction(function () use ($clientId, $cart, $couponCode, $demo, $stamp): string {
            $lines = [];
            $subtotal = 0;
            $demoOrder = $demo;
            $kinds = [];
            foreach ($cart as $line) {
                $kind = (string) ($line['kind'] ?? 'produto');
                $kinds[$kind] = true;
                if ($kind === 'foto') {
                    $photo = (new Catalog())->photo((int) ($line['modality_id'] ?? 0));
                    if (!$photo) {
                        throw new ValidationException(['cart' => 'Um pacote de fotos do carrinho não está mais disponível.']);
                    }
                    $qty = max(1, (int) ($line['quantity'] ?? 1));
                    $unit = (int) $photo['base_price_cents'];
                    $lineTotal = $unit * $qty;
                    $subtotal += $lineTotal;
                    $lines[] = ['kind' => 'foto', 'photo' => $photo, 'qty' => $qty, 'unit' => $unit, 'total' => $lineTotal];
                    continue;
                }
                if ($kind === 'experiencia') {
                    $prepared = $this->prepareExperience((int) ($line['vehicle_id'] ?? 0), (int) ($line['modality_id'] ?? 0), (string) ($line['date'] ?? ''), (string) ($line['time'] ?? ''));
                    $subtotal += $prepared['cents'];
                    $demoOrder = $demoOrder || !empty($prepared['offer']['is_demo']);
                    $lines[] = ['kind' => 'experiencia', 'prepared' => $prepared];
                    continue;
                }
                $product = Connection::one('SELECT * FROM shop_products WHERE id = ? AND active = 1', [(int) ($line['product_id'] ?? 0)]);
                if (!$product) {
                    throw new ValidationException(['cart' => 'Um produto do carrinho não está mais disponível.']);
                }
                $qty = max(1, (int) $line['quantity']);
                $variant = null;
                if (!empty($line['variant_id'])) {
                    $variant = Connection::one('SELECT * FROM shop_variants WHERE id = ? AND product_id = ?', [(int) $line['variant_id'], (int) $product['id']]);
                    if (!$variant || (int) $variant['stock'] < $qty) {
                        throw new ValidationException(['cart' => 'Estoque insuficiente para ' . $product['name'] . '.']);
                    }
                } elseif ((int) $product['stock'] < $qty && !(int) $product['preorder']) {
                    throw new ValidationException(['cart' => 'Estoque insuficiente para ' . $product['name'] . '.']);
                }
                $unit = (int) ($product['promo_cents'] ?: $product['price_cents']);
                $lineTotal = $unit * $qty;
                $subtotal += $lineTotal;
                $demoOrder = $demoOrder || (int) $product['is_demo'] === 1;
                $lines[] = ['kind' => 'produto', 'product' => $product, 'variant' => $variant, 'qty' => $qty, 'unit' => $unit, 'total' => $lineTotal];
            }
            $discount = $this->couponDiscount($couponCode, $subtotal, $clientId);
            $total = max(0, $subtotal - $discount);
            $code = $this->orderCode();
            $orderKind = count($kinds) === 1 ? (array_key_first($kinds) === 'produto' ? 'loja' : (string) array_key_first($kinds)) : 'compra';
            $orderId = Connection::insert(
                'INSERT INTO client_orders (public_id, code, client_id, kind, status, subtotal_cents, discount_cents, freight_cents, total_cents, coupon_code, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
                [bin2hex(random_bytes(16)), $code, $clientId, $orderKind, 'aguardando_pagamento', $subtotal, $discount, $total, $couponCode !== '' ? strtoupper($couponCode) : null, $demoOrder ? 1 : 0, $stamp, $stamp]
            );
            $reservationId = null;
            foreach ($lines as $item) {
                if ($item['kind'] === 'foto') {
                    Connection::insert(
                        'INSERT INTO client_order_items (order_id, description, quantity, unit_cents, total_cents, modality_id) VALUES (?, ?, ?, ?, ?, ?)',
                        [$orderId, $item['photo']['name'], $item['qty'], $item['unit'], $item['total'], (int) $item['photo']['id']]
                    );
                    continue;
                }
                if ($item['kind'] === 'experiencia') {
                    $prepared = $item['prepared'];
                    $created = $this->createReservation(
                        $clientId,
                        $prepared['vehicle_id'],
                        $prepared['modality_id'],
                        $prepared['offer'],
                        $prepared['cents'],
                        $prepared['date'],
                        $prepared['time'],
                        $prepared['starts'],
                        $prepared['ends'],
                        $prepared['duration'],
                        $demoOrder,
                        $stamp
                    );
                    if ($reservationId === null && $created) {
                        $reservationId = $created;
                    }
                    Connection::insert(
                        'INSERT INTO client_order_items (order_id, description, quantity, unit_cents, total_cents, vehicle_id, modality_id) VALUES (?, ?, 1, ?, ?, ?, ?)',
                        [$orderId, $prepared['label'], $prepared['cents'], $prepared['cents'], $prepared['vehicle_id'], $prepared['modality_id']]
                    );
                    continue;
                }
                $product = $item['product'];
                $variant = $item['variant'];
                $label = $product['name'];
                if ($variant) {
                    $label .= ' ' . trim((string) $variant['size'] . ' ' . (string) $variant['color']);
                    Connection::run('UPDATE shop_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$item['qty'], (int) $variant['id'], $item['qty']]);
                }
                Connection::run('UPDATE shop_products SET stock = stock - ? WHERE id = ? AND stock >= ?', [$item['qty'], (int) $product['id'], $item['qty']]);
                Connection::insert(
                    'INSERT INTO client_order_items (order_id, description, quantity, unit_cents, total_cents, variant_id) VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $label, $item['qty'], $item['unit'], $item['total'], $variant ? (int) $variant['id'] : null]
                );
            }
            if ($reservationId) {
                Connection::run('UPDATE client_orders SET reservation_id = ? WHERE id = ?', [$reservationId, $orderId]);
            }
            if ($discount > 0) {
                $coupon = Connection::one('SELECT id FROM coupons WHERE code = ?', [strtoupper($couponCode)]);
                Connection::insert(
                    'INSERT INTO coupon_uses (coupon_id, order_id, client_id, created_at) VALUES (?, ?, ?, ?)',
                    [(int) $coupon['id'], $orderId, $clientId, $stamp]
                );
            }
            $this->notify($clientId, 'Pedido registrado', 'O pedido ' . $code . ' reúne o que estava no carrinho e aguarda a confirmação do pagamento.', 'operacional');
            return $code;
        });
    }

    public function createGift(int $clientId, int $vehicleId, int $modalityId, string $recipient, string $giver, string $message, bool $demo): string
    {
        $offer = (new Catalog())->price($vehicleId, $modalityId);
        if (!$offer) {
            throw new ValidationException(['modality' => 'Este vale não está disponível.']);
        }
        if (mb_strlen(trim($recipient)) < 3 || mb_strlen(trim($giver)) < 3) {
            throw new ValidationException(['recipient' => 'Informe quem presenteia e quem recebe.']);
        }
        $stamp = now();
        $cents = (int) $offer['price_cents'];
        return Connection::transaction(function () use ($clientId, $vehicleId, $modalityId, $recipient, $giver, $message, $offer, $cents, $demo, $stamp): string {
            $orderCode = $this->orderCode();
            $orderId = Connection::insert(
                'INSERT INTO client_orders (public_id, code, client_id, kind, status, subtotal_cents, total_cents, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [bin2hex(random_bytes(16)), $orderCode, $clientId, 'vale', 'aguardando_pagamento', $cents, $cents, $demo ? 1 : 0, $stamp, $stamp]
            );
            $voucher = 'VP-' . strtoupper(bin2hex(random_bytes(4)));
            Connection::insert(
                'INSERT INTO gift_vouchers (public_id, code, order_id, buyer_client_id, recipient_name, giver_name, message, vehicle_id, modality_id, amount_cents, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [bin2hex(random_bytes(16)), $voucher, $orderId, $clientId, trim($recipient), trim($giver), trim($message), $vehicleId, $modalityId, $cents, 'aguardando_pagamento', $stamp]
            );
            Connection::insert(
                'INSERT INTO client_order_items (order_id, description, quantity, unit_cents, total_cents, vehicle_id, modality_id) VALUES (?, ?, 1, ?, ?, ?, ?)',
                [$orderId, 'Vale ' . $offer['name'] . ' · ' . $offer['brand'] . ' ' . $offer['model'], $cents, $cents, $vehicleId, $modalityId]
            );
            $this->notify($clientId, 'Vale registrado', 'O vale ' . $voucher . ' só fica disponível depois da confirmação do pagamento.', 'operacional');
            return $voucher;
        });
    }

    public function photoLine(int $modalityId): array
    {
        $photo = (new Catalog())->photo($modalityId);
        if (!$photo) {
            throw new ValidationException(['modality' => 'Este pacote de fotos não está disponível.']);
        }
        return ['kind' => 'foto', 'modality_id' => (int) $photo['id'], 'quantity' => 1];
    }

    public function experienceLine(int $vehicleId, int $modalityId, string $date, string $time): array
    {
        $prepared = $this->prepareExperience($vehicleId, $modalityId, $date, $time);
        return [
            'kind' => 'experiencia',
            'vehicle_id' => $prepared['vehicle_id'],
            'modality_id' => $prepared['modality_id'],
            'date' => (string) ($prepared['date'] ?? ''),
            'time' => (string) ($prepared['time'] ?? ''),
            'quantity' => 1,
        ];
    }

    private function prepareExperience(int $vehicleId, int $modalityId, string $date, string $time): array
    {
        $offer = (new Catalog())->price($vehicleId, $modalityId);
        if (!$offer || ($offer['type'] ?? '') !== 'experiencia') {
            throw new ValidationException(['modality' => 'Esta experiência não está disponível.']);
        }
        $parsedDate = $date !== '' ? parse_date($date) : null;
        $parsedTime = $time !== '' ? parse_time($time) : null;
        if (($date !== '' && !$parsedDate) || ($time !== '' && !$parsedTime)) {
            throw new ValidationException(['date' => 'Data ou horário inválido.']);
        }
        if (($parsedDate && !$parsedTime) || (!$parsedDate && $parsedTime)) {
            throw new ValidationException(['date' => 'Informe a data e o horário, ou deixe os dois em branco para agendar depois.']);
        }
        $starts = null;
        $ends = null;
        $duration = (int) $offer['duration_minutes'];
        if ($parsedDate && $parsedTime) {
            if ($duration < 15) {
                throw new ValidationException(['date' => 'Esta modalidade ainda não tem duração para a agenda.']);
            }
            $starts = $parsedDate . ' ' . $parsedTime . ':00';
            $ends = add_minutes($starts, $duration);
            (new AvailabilityService())->assertBookable($vehicleId, $starts, $ends);
        }
        return [
            'vehicle_id' => $vehicleId,
            'modality_id' => $modalityId,
            'offer' => $offer,
            'cents' => (int) $offer['price_cents'],
            'label' => $offer['name'] . ' · ' . $offer['brand'] . ' ' . $offer['model'],
            'date' => $parsedDate,
            'time' => $parsedTime,
            'starts' => $starts,
            'ends' => $ends,
            'duration' => $duration,
        ];
    }

    private function createReservation(int $clientId, int $vehicleId, int $modalityId, array $offer, int $cents, ?string $parsedDate, ?string $parsedTime, ?string $starts, ?string $ends, int $duration, bool $demoClient, string $stamp): ?int
    {
        if (!$starts || !$ends || !$parsedDate || !$parsedTime) {
            return null;
        }
        $number = Sequence::next('reservation-' . date('Y'));
        $code = sprintf('DX-%s-%05d', date('Y'), $number);
        $origin = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'aplicativo'");
        $reservationId = Connection::insert(
            'INSERT INTO reservations (public_id, code, client_id, origin_id, operational_status, financial_status, vehicle_id, scheduled_date, start_time, starts_at, ends_at, duration_minutes, participants, notes, original_cents, discount_cents, fees_cents, final_cents, paid_cents, pending_cents, is_demo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 0, 0, ?, 0, ?, ?, ?, ?)',
            [
                bin2hex(random_bytes(16)), $code, $clientId, $origin ?: null,
                'aguardando_confirmacao', 'aguardando_pagamento', $vehicleId,
                $parsedDate, $parsedTime, $starts, $ends, $duration,
                'Pedido pelo aplicativo do cliente. Pagamento ainda não confirmado.',
                $cents, $cents, $cents, $demoClient || !empty($offer['is_demo']) ? 1 : 0, $stamp, $stamp,
            ]
        );
        Connection::insert(
            'INSERT INTO reservation_items (reservation_id, modality_id, description, quantity, unit_price_cents, total_cents) VALUES (?, ?, ?, 1, ?, ?)',
            [$reservationId, $modalityId, $offer['name'] . ' · ' . $offer['brand'] . ' ' . $offer['model'], $cents, $cents]
        );
        Connection::insert(
            'INSERT INTO reservation_events (reservation_id, event_type, to_status, message, created_at) VALUES (?, ?, ?, ?, ?)',
            [$reservationId, 'created', 'aguardando_confirmacao', 'Reserva criada pelo aplicativo. Sem pagamento confirmado.', $stamp]
        );
        return $reservationId;
    }

    public function couponDiscount(string $code, int $subtotal, int $clientId): int
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return 0;
        }
        $coupon = Connection::one('SELECT * FROM coupons WHERE code = ? AND active = 1', [$code]);
        if (!$coupon) {
            throw new ValidationException(['coupon' => 'Cupom inválido.']);
        }
        if ($coupon['expires_on'] && (string) $coupon['expires_on'] < today()) {
            throw new ValidationException(['coupon' => 'Este cupom expirou.']);
        }
        if ($subtotal < (int) $coupon['min_cents']) {
            throw new ValidationException(['coupon' => 'O pedido não atingiu o valor mínimo do cupom.']);
        }
        if ($coupon['max_uses'] !== null) {
            $used = (int) Connection::value('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ?', [(int) $coupon['id']]);
            if ($used >= (int) $coupon['max_uses']) {
                throw new ValidationException(['coupon' => 'Este cupom esgotou.']);
            }
        }
        $already = Connection::value('SELECT id FROM coupon_uses WHERE coupon_id = ? AND client_id = ?', [(int) $coupon['id'], $clientId]);
        if ($already) {
            throw new ValidationException(['coupon' => 'Você já usou este cupom.']);
        }
        if ($coupon['kind'] === 'percent') {
            return (int) round($subtotal * (int) $coupon['amount'] / 10000);
        }
        return min($subtotal, (int) $coupon['amount']);
    }

    private function orderCode(): string
    {
        $number = Sequence::next('client-order-' . date('Y'));
        return sprintf('PD-%s-%05d', date('Y'), $number);
    }

    private function notify(int $clientId, string $title, string $body, string $channel): void
    {
        Connection::insert(
            'INSERT INTO client_notifications (client_id, title, body, channel, created_at) VALUES (?, ?, ?, ?, ?)',
            [$clientId, $title, $body, $channel, now()]
        );
    }
}
