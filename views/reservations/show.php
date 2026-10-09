<?php use App\Domain\Statuses; ?>
<?php
$canChange = \App\Auth\Auth::can('reservations.manage') && $reservation['vehicle_id'] && !in_array($reservation['operational_status'], ['cancelado', 'concluido'], true);
$canPay = \App\Auth\Auth::can('reservations.manage') || \App\Auth\Auth::can('finance.manage');
$canCancel = \App\Auth\Auth::can('reservations.cancel') && $reservation['operational_status'] !== 'cancelado';
$canEdit = $canChange || $canPay || $canCancel;
$editing = $canEdit && (($errors ?? []) !== [] || (string) ($_GET['editar'] ?? '') === '1');
?>
<?php
$documentLabels = [
    'ficha_reserva' => 'Ficha de reserva',
    'comprovante_agendamento' => 'Comprovante de agendamento',
    'ordem_servico' => 'Ordem de serviço',
    'termo_responsabilidade' => 'Termo de responsabilidade',
    'recibo_pagamento' => 'Recibo de pagamento',
];
$eventLabels = [
    'created' => 'Criação',
    'updated' => 'Alteração',
    'status' => 'Situação',
    'cancelled' => 'Cancelamento',
];
$paymentLabels = [
    'confirmado' => 'Confirmado',
    'estornado' => 'Estornado',
    'pendente' => 'Pendente',
];
$when = trim(date_br((string) $reservation['scheduled_date']) . ' ' . substr((string) $reservation['start_time'], 0, 5));
?>
<div class="split">
    <article class="card">
        <dl class="fact-list">
            <div><dt>Situação</dt><dd><span class="badge st-<?= e($reservation['operational_status']) ?>"><?= e(Statuses::label('operational', $reservation['operational_status'])) ?></span></dd></div>
            <div><dt>Financeiro</dt><dd><span class="badge st-<?= e($reservation['financial_status']) ?>"><?= e(Statuses::label('financial', $reservation['financial_status'])) ?></span></dd></div>
            <div><dt>Cliente</dt><dd><a href="/clientes/<?= (int) $reservation['client_id'] ?>"><?= e($reservation['client_name'] ?? $reservation['full_name'] ?? '') ?></a></dd></div>
            <div><dt>Veículo</dt><dd><?= e(trim(($reservation['brand'] ?? '') . ' ' . ($reservation['model'] ?? '')) ?: '—') ?></dd></div>
            <div><dt>Quando</dt><dd><?= e($when) ?></dd></div>
            <div><dt>Duração</dt><dd><?= (int) $reservation['duration_minutes'] ?> min</dd></div>
            <div><dt>Participantes</dt><dd><?= (int) $reservation['participants'] ?></dd></div>
            <div><dt>Origem</dt><dd><?= e((string) ($reservation['origin_name'] ?? '—')) ?><?php if (!empty($reservation['external_transaction_id'])): ?> · <?= e($reservation['external_transaction_id']) ?><?php endif; ?></dd></div>
            <?php if (!empty($reservation['partner_name'])): ?><div><dt>Parceria</dt><dd><?= e($reservation['partner_name']) ?> · <?= e(money((int) ($reservation['commission_cents'] ?? 0))) ?></dd></div><?php endif; ?>
            <div><dt>Experiência</dt><dd><?php if ($items === []): ?>—<?php else: ?><?php foreach ($items as $item): ?><span><?= e($item['description']) ?> · <?= e(money((int) $item['total_cents'])) ?></span><br><?php endforeach; ?><?php endif; ?></dd></div>
            <div><dt>Valor final</dt><dd><?= e(money((int) $reservation['final_cents'])) ?></dd></div>
            <div><dt>Pago</dt><dd><?= e(money((int) $reservation['paid_cents'])) ?></dd></div>
            <div><dt>Pendente</dt><dd><?= e(money((int) $reservation['pending_cents'])) ?></dd></div>
            <?php if (trim((string) $reservation['notes']) !== ''): ?><div><dt>Observações</dt><dd><?= e((string) $reservation['notes']) ?></dd></div><?php endif; ?>
            <?php if ($reservation['cancellation_reason']): ?><div><dt>Cancelamento</dt><dd><?= e($reservation['cancellation_reason']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($canEdit && !$editing): ?><a class="reservation-edit" href="/reservas/<?= (int) $reservation['id'] ?>?editar=1">Editar</a><?php endif; ?>
        <?php if ($editing): ?><a class="ghost sheet-action" href="/reservas/<?= (int) $reservation['id'] ?>">Fechar edição</a><?php endif; ?>
    </article>
    <article class="card" data-doc-export data-reservation="<?= (int) $reservation['id'] ?>">
        <h2>Documentos</h2>
        <?php
        $exportTypes = [
            'ficha_reserva' => 'Ficha de reserva',
            'comprovante_agendamento' => 'Comprovante de agendamento',
            'ordem_servico' => 'Ordem de serviço',
        ];
        $latestPdf = [];
        foreach ($documents as $document) {
            if (!isset($latestPdf[$document['type']]) && empty($document['redacted_at']) && isset($exportTypes[$document['type']])) {
                $latestPdf[$document['type']] = $document;
            }
        }
        ?>
        <p class="field-help" data-doc-hint>Selecione um documento.</p>
        <div class="choice-row" role="group" aria-label="Documento">
            <?php foreach ($exportTypes as $type => $label): ?>
                <button type="button" class="doc-choice" data-doc="<?= e($type) ?>" aria-pressed="false"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
        <button type="button" data-doc-download disabled>Baixar</button>
        <div class="doc-formats" data-doc-formats hidden>
            <p class="field-help">Formato do documento selecionado</p>
            <div class="choice-row">
                <a class="ghost doc-format" data-doc-csv href="/reservas/<?= (int) $reservation['id'] ?>/csv">CSV</a>
                <?php foreach ($exportTypes as $type => $label): ?>
                    <?php if (isset($latestPdf[$type])): ?>
                        <a class="ghost doc-format" data-doc-pdf="<?= e($type) ?>" hidden href="/documentos/<?= e($latestPdf[$type]['public_id']) ?>?download=1">PDF</a>
                    <?php elseif (\App\Auth\Auth::can('documents.issue')): ?>
                        <form data-doc-issue="<?= e($type) ?>" hidden method="post" action="/reservas/<?= (int) $reservation['id'] ?>/documentos/<?= e($type) ?>"><?= csrf_field() ?><button class="ghost" type="submit">PDF</button></form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($documents === []): ?>
            <p class="muted">Nenhum documento emitido.</p>
        <?php else: ?>
            <div class="bleed">
                <table class="doc-table">
                    <thead><tr><th>Documento</th><th>Versão</th><th>Emitido</th></tr></thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?= e($documentLabels[$document['type']] ?? (string) $document['title']) ?></td>
                            <td>v<?= (int) $document['version'] ?></td>
                            <td class="when"><?= e(date_br($document['issued_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</div>
<?php if ($editing && $canPay): ?>
<form method="post" action="/reservas/<?= (int) $reservation['id'] ?>/pagamentos" class="card stack form-grid">
    <?= csrf_field() ?>
    <h2 class="full">Registrar pagamento</h2>
    <div class="field"><label>Valor</label><input name="payment_amount" required></div>
    <div class="field"><label>Forma</label><select name="payment_method_id"><?php foreach ($methods as $method): ?><option value="<?= (int) $method['id'] ?>"><?= e($method['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Data</label><input type="date" name="payment_date" value="<?= e(today()) ?>"></div>
    <div class="field"><label>Parcelas</label><input type="number" name="payment_installments" value="1" min="1"></div>
    <div class="field"><label>Referência</label><input name="payment_external_id"></div>
    <div class="field full"><label>Observação</label><textarea name="payment_notes"></textarea></div>
    <div class="full"><button type="submit">Lançar pagamento</button></div>
</form>
<?php endif; ?>
<section class="card">
    <h2>Pagamentos</h2>
    <?php if ($payments === []): ?>
        <p class="muted">Nenhum pagamento.</p>
    <?php else: ?>
        <div class="bleed">
            <table>
                <thead><tr><th>Data</th><th>Valor</th><th>Forma</th><th>Situação</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="when"><?= e(date_br($payment['paid_at'])) ?></td>
                        <td><?= e(money((int) $payment['amount_cents'])) ?></td>
                        <td><?= e((string) ($payment['method_name'] ?: '—')) ?></td>
                        <td><?= e($paymentLabels[$payment['status']] ?? (string) $payment['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if ($editing && $canChange): ?>
<form method="post" action="/reservas/<?= (int) $reservation['id'] ?>" class="card stack form-grid">
    <?= csrf_field() ?>
    <h2 class="full">Alterar agenda</h2>
    <div class="field"><label>Data</label><input type="date" name="scheduled_date" value="<?= e((string) $reservation['scheduled_date']) ?>"></div>
    <div class="field"><label>Horário</label><input type="time" name="start_time" value="<?= e(substr((string) $reservation['start_time'], 0, 5)) ?>"></div>
    <div class="field"><label>Duração</label><input type="number" name="duration_minutes" value="<?= (int) $reservation['duration_minutes'] ?>"></div>
    <div class="field"><label>Participantes</label><input type="number" name="participants" value="<?= (int) $reservation['participants'] ?>"></div>
    <div class="field"><label>Veículo</label><select name="vehicle_id"><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= (int) $reservation['vehicle_id'] === (int) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Responsável</label><select name="assigned_user_id"><option value="">Não definido</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= (int) $reservation['assigned_user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Desconto</label><input name="discount" value="<?= e(number_format(((int) $reservation['discount_cents']) / 100, 2, ',', '.')) ?>"></div>
    <div class="field"><label>Taxas</label><input name="additional_fees" value="<?= e(number_format(((int) $reservation['fees_cents']) / 100, 2, ',', '.')) ?>"></div>
    <div class="field full"><label>Observações</label><textarea name="notes"><?= e((string) $reservation['notes']) ?></textarea></div>
    <div class="full"><button type="submit">Salvar alteração</button></div>
</form>
<form method="post" action="/reservas/<?= (int) $reservation['id'] ?>/status" class="card stack actions">
    <?= csrf_field() ?>
    <select name="status">
        <?php foreach (Statuses::TRANSITIONS[$reservation['operational_status']] ?? [] as $code): ?><option value="<?= e($code) ?>"><?= e(Statuses::label('operational', $code)) ?></option><?php endforeach; ?>
    </select>
    <button type="submit">Atualizar situação</button>
</form>
<?php endif; ?>
<?php if ($editing && $canCancel): ?>
<form method="post" action="/reservas/<?= (int) $reservation['id'] ?>/cancelar" class="card stack">
    <?= csrf_field() ?>
    <div class="field"><label>Motivo do cancelamento</label><textarea name="cancellation_reason" required></textarea></div>
    <button class="btn-danger" type="submit">Cancelar reserva</button>
</form>
<?php endif; ?>
<article class="card">
    <h2>Histórico</h2>
    <?php if ($events === []): ?>
        <p class="muted">Nenhum registro.</p>
    <?php else: ?>
        <div class="bleed">
            <table>
                <thead><tr><th>Data</th><th>Evento</th><th>Descrição</th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php $kind = (string) ($event['event_type'] ?? $event['type'] ?? ''); ?>
                    <tr>
                        <td class="when"><?= e(date_br($event['created_at'])) ?></td>
                        <td><?= e($eventLabels[$kind] ?? $kind) ?></td>
                        <td><?= e((string) ($event['message'] ?? $event['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
