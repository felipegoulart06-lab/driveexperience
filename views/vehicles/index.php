<?php use App\Domain\Statuses; ?>
<?php if (\App\Auth\Auth::can('vehicles.manage')): ?>
<div class="toolbar">
    <a class="button-link" href="/veiculos/novo">Novo veículo</a>
</div>
<?php endif; ?>
<div data-fleet>
    <div class="view-switch" role="tablist">
        <button type="button" data-vehicle-view="grade" class="is-active">Grade</button>
        <button type="button" data-vehicle-view="lista">Lista</button>
    </div>
    <div class="grid cards-3" data-vehicle-panel="grade">
        <?php foreach ($vehicles as $vehicle): ?>
            <article class="card vehicle-card">
                <?php if (!empty($covers[(int) $vehicle['id']])): ?>
                    <img class="vehicle-cover" src="/veiculos/<?= (int) $vehicle['id'] ?>/fotos/<?= (int) $covers[(int) $vehicle['id']]['id'] ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>">
                <?php endif; ?>
                <header class="vehicle-head">
                    <a href="/veiculos/<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></a>
                    <span class="badge st-<?= e($vehicle['display_status']) ?>"><?= e(Statuses::label('vehicle', $vehicle['display_status'])) ?></span>
                </header>
                <p class="vehicle-meta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H7v-2a2 2 0 0 0-4 0Z"/><path d="M5 18v2"/><path d="M19 18v2"/></svg>
                    <span><?= (int) $vehicle['capacity'] ?> lugares</span>
                </p>
                <table class="vehicle-prices">
                    <thead><tr><th>Experiência</th><th class="num">Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($experiences as $experience): ?>
                        <?php $cents = $prices[(int) $vehicle['id']][(int) $experience['id']] ?? null; ?>
                        <tr>
                            <td><?= e($experience['name']) ?></td>
                            <td class="num"><?php if ($cents): ?><?= e(money($cents)) ?><?php else: ?><span class="muted">não oferecido</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (\App\Auth\Auth::can('vehicles.manage')): ?>
                    <div class="vehicle-foot"><a class="ghost" href="/veiculos/<?= (int) $vehicle['id'] ?>?editar=1">Editar</a></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="card table-wrap" data-vehicle-panel="lista" hidden>
        <table class="vehicle-list">
            <thead>
                <tr>
                    <th>Veículo</th>
                    <th>Situação</th>
                    <th>Lugares</th>
                    <?php foreach ($experiences as $experience): ?><th class="num"><?= e($experience['name']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehicles as $vehicle): ?>
                <tr>
                    <td><a href="/veiculos/<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></a></td>
                    <td><span class="badge st-<?= e($vehicle['display_status']) ?>"><?= e(Statuses::label('vehicle', $vehicle['display_status'])) ?></span></td>
                    <td class="num"><?= (int) $vehicle['capacity'] ?></td>
                    <?php foreach ($experiences as $experience): ?>
                        <?php $cents = $prices[(int) $vehicle['id']][(int) $experience['id']] ?? null; ?>
                        <td class="num"><?php if ($cents): ?><?= e(money($cents)) ?><?php else: ?><span class="muted">não oferecido</span><?php endif; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($vehicles === []): ?><tr><td colspan="<?= 3 + count($experiences) ?>">Nenhum veículo.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
