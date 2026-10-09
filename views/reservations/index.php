<?php use App\Domain\Statuses; ?>
<div class="toolbar filter-bar">
    <form method="get" action="/reservas" class="reservation-filters">
        <input type="search" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Cliente, código ou vendedor">
        <select name="status"><option value="">Situação</option><?php foreach (Statuses::OPERATIONAL as $code => $label): ?><option value="<?= e($code) ?>" <?= (($_GET['status'] ?? '') === $code) ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="vehicle_id"><option value="">Veículo</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= (string) ($_GET['vehicle_id'] ?? '') === (string) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select>
        <label class="field-inline"><span>De</span><input type="date" name="from" value="<?= e((string) ($_GET['from'] ?? '')) ?>"></label>
        <label class="field-inline"><span>Até</span><input type="date" name="to" value="<?= e((string) ($_GET['to'] ?? '')) ?>"></label>
        <div class="filter-actions">
            <button class="ghost" type="submit">Filtrar</button>
            <a href="/reservas">Limpar</a>
        </div>
    </form>
    <?php if (\App\Auth\Auth::can('reservations.manage')): ?><a class="button-link" href="/reservas/nova">Nova reserva</a><?php endif; ?>
</div>
<div class="card table-wrap">
    <table class="reservations-table">
        <thead><tr><th>Reserva</th><th>Cliente</th><th>Quando</th><th>Veículo</th><th>Operação</th><th>Financeiro</th><th>Valor</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $row): ?>
            <?php $pending = (int) $row['pending_cents']; ?>
            <tr data-href="/reservas/<?= (int) $row['id'] ?>">
                <td><a class="code" href="/reservas/<?= (int) $row['id'] ?>"><?= e($row['code']) ?></a></td>
                <td><?= e($row['client_name']) ?></td>
                <td class="when"><?= e(date_br((string) $row['scheduled_date'])) ?> <?= e(substr((string) $row['start_time'], 0, 5)) ?></td>
                <td><?= e(trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''))) ?></td>
                <td><span class="badge st-<?= e($row['operational_status']) ?>"><?= e(Statuses::label('operational', $row['operational_status'])) ?></span></td>
                <td><span class="badge st-<?= e($row['financial_status']) ?>"><?= e(Statuses::label('financial', $row['financial_status'])) ?></span></td>
                <td class="num value-cell"><span><?= e(money((int) $row['final_cents'])) ?></span><?php if ($pending > 0): ?><span class="value-note is-pending">Pendente <?= e(money($pending)) ?></span><?php else: ?><span class="value-note is-paid">Quitado</span><?php endif; ?></td>
                <td class="row-action"><?php booking_actions($row); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?><tr><td colspan="8">Nenhuma reserva.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="8"><?= (int) $result['total'] ?> reserva(s)</td></tr></tfoot>
    </table>
</div>
<?php if ($result['pages'] > 1): ?><div class="pager"><?php for ($page = 1; $page <= $result['pages']; $page++): ?><a class="button-link ghost" href="/reservas?page=<?= $page ?>"><?= $page ?></a><?php endfor; ?></div><?php endif; ?>
