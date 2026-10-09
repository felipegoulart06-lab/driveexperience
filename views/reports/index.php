<?php
use App\Domain\Statuses;
$reportNames = ['reservas' => 'Reservas', 'financeiro' => 'Financeiro', 'clientes' => 'Clientes', 'origens' => 'Vendas por origem'];
$reportName = $reportNames[$filters['type']] ?? 'Reservas';
?>
<section class="card report-filters">
    <h2>Filtros</h2>
    <form id="report-filters" method="get" action="/relatorios">
        <div class="report-grid">
            <div class="field span-2"><label>Relatório</label>
                <select name="type">
                    <?php foreach ($reportNames as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $filters['type'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>De</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
            <div class="field"><label>Até</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
            <div class="field"><label>Veículo</label><select name="vehicle_id"><option value="">Todos</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= $filters['vehicle_id'] == $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Situação operacional</label><select name="status"><option value="">Todas</option><?php foreach (Statuses::OPERATIONAL as $code => $label): ?><option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Situação financeira</label><select name="financial"><option value="">Todas</option><?php foreach (Statuses::FINANCIAL as $code => $label): ?><option value="<?= e($code) ?>" <?= $filters['financial'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Origem</label><select name="origin_id"><option value="">Todas</option><?php foreach ($origins as $origin): ?><option value="<?= (int) $origin['id'] ?>" <?= $filters['origin_id'] == $origin['id'] ? 'selected' : '' ?>><?= e($origin['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Plataforma</label><select name="platform_id"><option value="">Todas</option><?php foreach ($platforms as $platform): ?><option value="<?= (int) $platform['id'] ?>" <?= $filters['platform_id'] == $platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Responsável</label><select name="user_id"><option value="">Todos</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        </div>
    </form>
    <div class="report-foot">
        <button type="submit" form="report-filters">Atualizar</button>
        <span class="report-divider" aria-hidden="true"></span>
        <a class="button-link ghost" href="/relatorios/csv?<?= e(http_build_query($filters)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>Exportar CSV</a>
        <form method="post" action="/relatorios/pdf"><?= csrf_field() ?><?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?><button class="ghost" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>Gerar PDF</button></form>
    </div>
</section>
<article class="card report-result">
    <header class="report-head">
        <h2><?= e($reportName) ?></h2>
        <?php if ($preview['kind'] === 'reservas'): ?>
            <p class="report-total"><span>Total filtrado</span><strong><?= e(money($preview['data']['total'])) ?></strong></p>
        <?php elseif ($preview['kind'] === 'origens'): ?>
            <?php $originTotal = 0; foreach ($preview['data'] as $row) { $originTotal += (int) $row['total']; } ?>
            <p class="report-total"><span>Total filtrado</span><strong><?= e(money($originTotal)) ?></strong></p>
        <?php endif; ?>
    </header>
    <?php if ($preview['kind'] === 'financeiro'): ?>
        <?php if ($preview['data'] === []): ?><p>Seu perfil não visualiza o relatório financeiro.</p><?php else: ?>
            <p>Vendido <?= e(money($preview['data']['summary']['sold'])) ?> · recebido <?= e(money($preview['data']['summary']['received'])) ?> · despesas <?= e(money($preview['data']['summary']['expenses'])) ?> · caixa <?= e(money($preview['data']['summary']['cash_result'])) ?></p>
        <?php endif; ?>
    <?php elseif ($preview['kind'] === 'clientes'): ?>
        <div class="bleed table-wrap">
            <table class="report-table"><thead><tr><th>Nome</th><th>CPF</th><th>Origem</th></tr></thead><tbody>
            <?php foreach ($preview['data'] as $row): ?><tr><td><?= e($row['full_name']) ?></td><td><?= e((string) $row['cpf']) ?></td><td><?= e((string) $row['origin_name']) ?></td></tr><?php endforeach; ?>
            </tbody><tfoot><tr><td colspan="3"><?= count($preview['data']) ?> registro(s)</td></tr></tfoot></table>
        </div>
    <?php elseif ($preview['kind'] === 'origens'): ?>
        <div class="bleed table-wrap">
            <table class="report-table"><thead><tr><th>Origem</th><th>Quantidade</th><th class="num">Valor</th></tr></thead><tbody>
            <?php foreach ($preview['data'] as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= (int) $row['quantity'] ?></td><td class="num"><?= e(money((int) $row['total'])) ?></td></tr><?php endforeach; ?>
            </tbody><tfoot><tr><td colspan="2"><?= count($preview['data']) ?> registro(s)</td><td class="num"><?= e(money($originTotal)) ?></td></tr></tfoot></table>
        </div>
    <?php else: ?>
        <div class="bleed table-wrap">
            <table class="report-table">
                <thead><tr><th>Reserva</th><th>Data</th><th>Cliente</th><th>Veículo</th><th class="num">Valor</th></tr></thead>
                <tbody>
                <?php foreach ($preview['data']['rows'] as $row): ?>
                    <tr>
                        <td><a class="code" href="/reservas/<?= (int) $row['id'] ?>"><?= e($row['code']) ?></a></td>
                        <td class="when"><?= e(date_br($row['scheduled_date'])) ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e((string) $row['vehicle']) ?></td>
                        <td class="num"><?= e(money((int) $row['final_cents'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4"><?= count($preview['data']['rows']) ?> registro(s)</td><td class="num"><?= e(money($preview['data']['total'])) ?></td></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
</article>
