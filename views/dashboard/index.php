<?php use App\Domain\Statuses;

$peak = 0;
foreach ($data['sales'] as $bar) {
    $peak = max($peak, (int) $bar['total']);
}
$rankMax = static function (array $rows): int {
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) $row['total']);
    }
    return $max;
};
$originMax = $rankMax($data['origins']);
$experienceMax = $rankMax($data['experiences']);
$vehicleMax = $rankMax($data['vehicles']);
?>
<section class="kpi-strip">
    <div class="kpi"><span>Clientes</span><strong><?= (int) $data['clients'] ?></strong><em><?= (int) $data['new_clients'] ?> novos no mês</em></div>
    <div class="kpi"><span>Hoje</span><strong><?= (int) $data['today'] ?></strong><em>agendamentos</em></div>
    <div class="kpi"><span>Aguardando confirmação</span><strong><?= (int) $data['waiting'] ?></strong></div>
    <div class="kpi"><span>Confirmadas</span><strong><?= (int) $data['confirmed'] ?></strong><em><?= (int) $data['done'] ?> concluídas · <?= (int) $data['cancelled'] ?> canceladas no mês</em></div>
</section>
<?php if ($showFinance): ?>
<section class="kpi-strip stack">
    <div class="kpi"><span>Vendas do mês</span><strong><?= e(money($data['finance']['sold'])) ?></strong><em>valor contratado</em></div>
    <div class="kpi"><span>Recebido</span><strong><?= e(money($data['finance']['received'])) ?></strong></div>
    <div class="kpi is-alert"><span>Pendente</span><strong><?= e(money($data['finance']['pending'])) ?></strong></div>
    <div class="kpi"><span>Resultado de caixa</span><strong><?= e(money($data['finance']['cash_result'])) ?></strong><em>despesas <?= e(money($data['finance']['expenses'])) ?></em></div>
</section>
<?php endif; ?>
<article class="card stack">
    <h2>Vendas por mês</h2>
    <div class="chart">
        <div class="chart-scale">
            <?php for ($tick = 3; $tick >= 0; $tick--): ?>
                <span><?= e(money((int) round($peak * $tick / 3))) ?></span>
            <?php endfor; ?>
        </div>
        <div class="chart-area">
            <div class="chart-grid" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <div class="chart-cols">
                <?php foreach ($data['sales'] as $bar): ?>
                    <?php $share = $peak > 0 ? (int) round(((int) $bar['total'] / $peak) * 100) : 0; ?>
                    <div class="chart-col">
                        <div class="chart-slot">
                            <div class="chart-bar<?= $bar['period'] === date('Y-m') ? ' is-current' : '' ?>" data-bar="<?= $share ?>">
                                <span class="chart-value"><?= e(money((int) $bar['total'])) ?></span>
                            </div>
                        </div>
                        <span class="chart-label"><?= e($bar['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</article>
<section class="grid panels-2 stack">
    <article class="card">
        <h2>Próximos horários</h2>
        <?php if ($data['upcoming'] === []): ?>
            <p class="muted">Nenhum agendamento futuro.</p>
        <?php else: ?>
            <div class="bleed">
                <table class="dash-table">
                    <thead><tr><th>Código</th><th>Data e hora</th><th>Cliente</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['upcoming'] as $item): ?>
                        <tr>
                            <td><a class="code" href="/reservas/<?= (int) $item['id'] ?>"><?= e($item['code']) ?></a></td>
                            <td><?= e(date_br($item['scheduled_date'] . ' ' . $item['start_time'])) ?></td>
                            <td><?= e($item['full_name']) ?></td>
                            <td><span class="badge st-<?= e($item['operational_status']) ?>"><?= e(Statuses::label('operational', $item['operational_status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
    <article class="card">
        <h2>Alertas</h2>
        <?php if ($data['alerts'] === []): ?>
            <p class="muted">Nenhum alerta.</p>
        <?php else: ?>
            <div class="alert-list">
                <?php foreach ($data['alerts'] as $alert): ?>
                    <div class="alert-line">
                        <a class="code" href="/reservas/<?= (int) $alert['id'] ?>"><?= e($alert['code']) ?></a>
                        <span><?= e($alert['full_name']) ?></span>
                        <?php if ((int) $alert['pending_cents'] > 0): ?><span class="num">saldo <?= e(money((int) $alert['pending_cents'])) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<section class="grid cards-3 stack">
    <?php
    $ranks = [
        ['Origem dos clientes', $data['origins'], $originMax, static fn (array $row): string => (string) $row['name']],
        ['Experiências', $data['experiences'], $experienceMax, static fn (array $row): string => (string) $row['name']],
        ['Veículos mais reservados', $data['vehicles'], $vehicleMax, static fn (array $row): string => $row['brand'] . ' ' . $row['model']],
    ];
    foreach ($ranks as [$title, $rows, $max, $label]):
    ?>
    <article class="card">
        <h2><?= e($title) ?></h2>
        <ul class="rank">
            <?php foreach ($rows as $row): ?>
                <?php $total = (int) $row['total']; ?>
                <li class="<?= $total === 0 ? 'is-zero' : '' ?>">
                    <div class="rank-top"><span><?= e($label($row)) ?></span><strong><?= $total ?></strong></div>
                    <div class="rank-track"><span data-share="<?= $max > 0 ? (int) round($total / $max * 100) : 0 ?>"></span></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
    <?php endforeach; ?>
</section>
<article class="card stack">
    <h2>Atividades recentes</h2>
    <?php if ($data['activities'] === []): ?>
        <p class="muted">Ainda não há atividades.</p>
    <?php else: ?>
        <table class="activity-table">
            <tbody>
            <?php foreach ($data['activities'] as $activity): ?>
                <tr>
                    <td class="when"><?= e(date_br($activity['created_at'])) ?></td>
                    <td><?= e($activity['summary']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</article>
