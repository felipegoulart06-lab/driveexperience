<?php

use App\Database\Connection;
use App\Domain\Statuses;

$keep = array_filter([
    'view' => $viewName,
    'vehicle_id' => (string) ($_GET['vehicle_id'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
    'user_id' => (string) ($_GET['user_id'] ?? ''),
], static fn (string $value): bool => $value !== '');
$agendaUrl = static function (string $day) use ($keep): string {
    return '/agenda?' . http_build_query(['date' => $day] + $keep);
};
if ($viewName === 'dia') {
    $prev = date('Y-m-d', (int) strtotime($date . ' -1 day'));
    $next = date('Y-m-d', (int) strtotime($date . ' +1 day'));
} elseif ($viewName === 'mes') {
    $prev = date('Y-m-d', (int) strtotime($date . ' -1 month'));
    $next = date('Y-m-d', (int) strtotime($date . ' +1 month'));
} else {
    $prev = date('Y-m-d', (int) strtotime($date . ' -7 days'));
    $next = date('Y-m-d', (int) strtotime($date . ' +7 days'));
}
$weekdayName = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
$blockLabels = [
    'clima' => 'Condição climática',
    'indisponibilidade' => 'Indisponibilidade',
    'outro' => 'Outro',
    'manutencao' => 'Manutenção',
];
$toneFor = static function (string $status): string {
    return match ($status) {
        'concluido' => 'is-ok',
        'cancelado' => 'is-danger',
        'confirmado', 'em_andamento' => 'is-info',
        default => 'is-warn',
    };
};
$clock = static function (?string $value): ?int {
    if ($value === null || !preg_match('/(\d{2}):(\d{2})/', $value, $match)) {
        return null;
    }
    return ((int) $match[1]) * 60 + (int) $match[2];
};
$showGrid = $viewName === 'semana' || $viewName === 'dia';
$openMin = 8 * 60;
$closeMin = 19 * 60;
if ($showGrid) {
    $activeHours = array_values(array_filter(
        Connection::all('SELECT opens_at, closes_at, active FROM operating_hours'),
        static fn (array $row): bool => (int) $row['active'] === 1
    ));
    if ($activeHours !== []) {
        $openMin = min(array_map(static fn (array $row): int => $clock((string) $row['opens_at']) ?? (8 * 60), $activeHours));
        $closeMin = max(array_map(static fn (array $row): int => $clock((string) $row['closes_at']) ?? (19 * 60), $activeHours));
    }
    $openMin = (int) (floor($openMin / 60) * 60);
    $closeMin = (int) (ceil($closeMin / 60) * 60);
    if ($closeMin <= $openMin) {
        $closeMin = $openMin + 60;
    }
}
$pxPerHour = 48;
$gridHeight = (int) (($closeMin - $openMin) / 60 * $pxPerHour);
$place = static function (int $start, int $end) use ($openMin, $closeMin, $pxPerHour): array {
    $start = max($openMin, min($closeMin, $start));
    $end = max($openMin, min($closeMin, $end));
    if ($end <= $start) {
        $end = min($closeMin, $start + 30);
    }
    return [
        'top' => (int) round(($start - $openMin) / 60 * $pxPerHour),
        'height' => max(22, (int) round(($end - $start) / 60 * $pxPerHour)),
    ];
};
?>
<div class="toolbar agenda-bar">
    <a class="ghost icon-btn" href="<?= e($agendaUrl($prev)) ?>" aria-label="Anterior">‹</a>
    <a class="ghost icon-btn" href="<?= e($agendaUrl($next)) ?>" aria-label="Próximo">›</a>
    <a class="ghost" href="<?= e($agendaUrl(today())) ?>">Hoje</a>
    <form method="get">
        <input type="date" name="date" value="<?= e($date) ?>">
        <select name="view">
            <?php foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês', 'lista' => 'Lista'] as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $viewName === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="vehicle_id"><option value="">Veículo</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= (string) ($_GET['vehicle_id'] ?? '') === (string) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select>
        <select name="status"><option value="">Situação</option><?php foreach (Statuses::OPERATIONAL as $code => $label): ?><option value="<?= e($code) ?>" <?= (string) ($_GET['status'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="user_id"><option value="">Responsável</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= (string) ($_GET['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
        <button class="ghost" type="submit">Ver</button>
    </form>
</div>
<p class="muted agenda-range"><?= e(date_br($from)) ?> a <?= e(date_br($to)) ?></p>
<?php if ($showGrid): ?>
    <?php
    $byDay = [];
    foreach ($reservations as $item) {
        $byDay[$item['scheduled_date']][] = $item;
    }
    $days = [];
    for ($cursor = strtotime($from); $cursor <= strtotime($to); $cursor = strtotime('+1 day', $cursor)) {
        $days[] = date('Y-m-d', $cursor);
    }
    ?>
    <div class="agenda<?= $viewName === 'dia' ? ' is-day' : '' ?>" data-agenda data-agenda-height="<?= $gridHeight ?>">
        <div class="agenda-gutter"></div>
        <?php foreach ($days as $day): $stamp = (int) strtotime($day); ?>
            <div class="agenda-dayhead<?= $day === today() ? ' is-today' : '' ?>"><?= e($weekdayName[(int) date('w', $stamp)] . ' ' . date('d/m', $stamp)) ?></div>
        <?php endforeach; ?>
        <div class="agenda-hours">
            <?php for ($minute = $openMin; $minute <= $closeMin; $minute += 60): ?>
                <span class="agenda-tick" data-top="<?= (int) (($minute - $openMin) / 60 * $pxPerHour) ?>"><?= e(sprintf('%02d:00', intdiv($minute, 60))) ?></span>
            <?php endfor; ?>
        </div>
        <?php foreach ($days as $day): ?>
            <div class="agenda-col<?= $day === today() ? ' is-today' : '' ?>">
                <?php for ($minute = $openMin; $minute <= $closeMin; $minute += 60): ?>
                    <i class="agenda-line" data-top="<?= (int) (($minute - $openMin) / 60 * $pxPerHour) ?>"></i>
                <?php endfor; ?>
                <?php foreach ($blocks as $block): ?>
                    <?php
                    $blockStart = $clock((string) $block['starts_at']);
                    $blockEnd = $clock((string) $block['ends_at']);
                    $blockDay = substr((string) $block['starts_at'], 0, 10);
                    $blockLast = substr((string) $block['ends_at'], 0, 10);
                    if ($blockStart === null || $blockEnd === null || $day < $blockDay || $day > $blockLast) {
                        continue;
                    }
                    $start = $day === $blockDay ? $blockStart : $openMin;
                    $end = $day === $blockLast ? $blockEnd : $closeMin;
                    $box = $place($start, $end);
                    ?>
                    <div class="agenda-block" data-top="<?= $box['top'] ?>" data-height="<?= $box['height'] ?>" title="<?= e($block['reason']) ?>"></div>
                <?php endforeach; ?>
                <?php
                $events = [];
                foreach ($byDay[$day] ?? [] as $item) {
                    $start = $clock((string) ($item['starts_at'] ?: $item['scheduled_date'] . ' ' . $item['start_time']));
                    $end = $clock((string) $item['ends_at']);
                    if ($start === null) {
                        continue;
                    }
                    if ($end === null || $end <= $start) {
                        $end = $start + max(30, (int) $item['duration_minutes']);
                    }
                    $events[] = ['item' => $item, 'start' => $start, 'end' => $end, 'lane' => 0];
                }
                usort($events, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
                $laneEnds = [];
                foreach ($events as $index => $event) {
                    $lane = 0;
                    while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $event['start']) {
                        $lane++;
                    }
                    $laneEnds[$lane] = $event['end'];
                    $events[$index]['lane'] = $lane;
                }
                $laneCount = max(1, count($laneEnds));
                foreach ($events as $event):
                    $item = $event['item'];
                    $box = $place($event['start'], $event['end']);
                    $width = 100 / $laneCount;
                    ?>
                    <a class="agenda-event <?= e($toneFor((string) $item['operational_status'])) ?>" data-top="<?= $box['top'] ?>" data-height="<?= $box['height'] ?>" data-left="<?= e((string) ($event['lane'] * $width)) ?>" data-width="<?= e((string) $width) ?>" href="/reservas/<?= (int) $item['id'] ?>"><?= e(sprintf('%02d:%02d', intdiv($event['start'], 60), $event['start'] % 60)) ?> <?= e($item['full_name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif ($viewName === 'lista'): ?>
    <div class="card table-wrap">
        <table>
            <thead><tr><th>Quando</th><th>Cliente</th><th>Veículo</th><th>Situação</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $item): ?>
                <tr data-href="/reservas/<?= (int) $item['id'] ?>">
                    <td class="when"><a href="/reservas/<?= (int) $item['id'] ?>"><?= e(date_br($item['scheduled_date'] . ' ' . $item['start_time'])) ?></a></td>
                    <td><?= e($item['full_name']) ?></td>
                    <td><?= e(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?></td>
                    <td><span class="badge st-<?= e($item['operational_status']) ?>"><?= e(Statuses::label('operational', $item['operational_status'])) ?></span></td>
                    <td class="row-action"><?php booking_actions($item); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($reservations === []): ?><tr><td colspan="5">Nenhuma reserva neste período.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <?php
    $grouped = [];
    foreach ($reservations as $item) {
        $grouped[$item['scheduled_date']][] = $item;
    }
    ?>
    <div class="agenda-month">
        <?php for ($cursor = strtotime($from); $cursor <= strtotime($to); $cursor = strtotime('+1 day', $cursor)): $day = date('Y-m-d', $cursor); ?>
            <article class="agenda-month-day<?= $day === today() ? ' is-today' : '' ?>">
                <strong><?= e($weekdayName[(int) date('w', $cursor)] . ' ' . date('d/m', $cursor)) ?></strong>
                <?php foreach ($grouped[$day] ?? [] as $item): ?>
                    <a class="agenda-event is-static <?= e($toneFor((string) $item['operational_status'])) ?>" href="/reservas/<?= (int) $item['id'] ?>"><?= e(substr((string) $item['start_time'], 0, 5)) ?> <?= e($item['full_name']) ?></a>
                <?php endforeach; ?>
            </article>
        <?php endfor; ?>
    </div>
<?php endif; ?>
<section class="agenda-panels stack">
    <article class="card">
        <h2>Bloqueios</h2>
        <?php if ($blocks === []): ?>
            <p class="muted">Nenhum bloqueio neste período.</p>
        <?php else: ?>
            <div class="bleed">
                <table>
                    <thead><tr><th>Data</th><th>Horário</th><th>Veículo</th><th>Tipo</th><th>Motivo</th><th>Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <tr>
                            <td class="when"><?= e(date_br(substr((string) $block['starts_at'], 0, 10))) ?></td>
                            <td class="when"><?= e(substr((string) $block['starts_at'], 11, 5)) ?>–<?= e(substr((string) $block['ends_at'], 11, 5)) ?></td>
                            <td><?= e(trim(($block['brand'] ?? '') . ' ' . ($block['model'] ?? '')) ?: 'Frota') ?></td>
                            <td><?= e($blockLabels[$block['block_type']] ?? (string) $block['block_type']) ?></td>
                            <td><?= e($block['reason']) ?></td>
                            <td>
                                <?php if (\App\Auth\Auth::can('agenda.manage') && $block['block_type'] !== 'manutencao'): ?>
                                    <form method="post" action="/agenda/bloqueios/<?= (int) $block['id'] ?>/remover"><?= csrf_field() ?><button class="ghost" type="submit">Remover</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
    <?php if (\App\Auth\Auth::can('agenda.manage')): ?>
    <form method="post" action="/agenda/bloqueios" class="card agenda-block-form">
        <?= csrf_field() ?>
        <h2>Bloquear horário</h2>
        <div class="agenda-form-grid">
            <div class="field"><label>Data</label><input type="date" name="date" value="<?= e($date) ?>" required></div>
            <div class="field"><label>Veículo</label><select name="vehicle_id"><option value="">Todos</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Início</label><input type="time" name="start_time" value="09:00"></div>
            <div class="field"><label>Fim</label><input type="time" name="end_time" value="12:00"></div>
            <div class="field"><label>Tipo</label><select name="block_type"><option value="clima">Condição climática</option><option value="indisponibilidade">Indisponibilidade</option><option value="outro">Outro</option></select></div>
        </div>
        <label class="inline-check agenda-all-day"><input type="checkbox" name="all_day" value="1"> Dia inteiro</label>
        <div class="field"><label>Motivo</label><textarea name="reason" rows="3" required></textarea></div>
        <button type="submit">Bloquear</button>
    </form>
    <?php endif; ?>
</section>
