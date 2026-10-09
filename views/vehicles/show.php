<?php use App\Domain\Statuses; ?>
<?php
$canEdit = \App\Auth\Auth::can('vehicles.manage');
$editing = $canEdit && (string) ($_GET['editar'] ?? '') === '1';
$vehicleId = (int) $vehicle['id'];
$arrow = static function (string $direction): string {
    $path = $direction === 'esquerda' ? 'M15 18 9 12l6-6' : 'M9 18l6-6-6-6';
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $path . '"/></svg>';
};
?>
<?php if ($canEdit): ?>
<div class="toolbar">
    <?php if ($editing): ?>
        <a class="ghost button-link" href="/veiculos/<?= $vehicleId ?>">Fechar edição</a>
    <?php else: ?>
        <a class="button-link" href="/veiculos/<?= $vehicleId ?>?editar=1">Editar</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($images !== []): ?>
<p class="field-help"><?= $editing ? 'A primeira foto, da esquerda para a direita, é a capa da grade.' : 'A primeira foto é a capa da grade. Excluir e reordenar só ficam disponíveis em Editar.' ?></p>
<div class="vehicle-gallery<?= $editing ? ' is-editing' : '' ?>">
    <?php foreach ($images as $index => $image): ?>
        <?php $imageId = (int) $image['id']; ?>
        <div class="vehicle-shot">
            <?php if ($editing): ?>
                <form method="post" action="/veiculos/<?= $vehicleId ?>/fotos/<?= $imageId ?>/mover" class="shot-move shot-move-left">
                    <?= csrf_field() ?>
                    <input type="hidden" name="direction" value="esquerda">
                    <button type="submit" aria-label="Mover para a esquerda"<?= $index === 0 ? ' disabled' : '' ?>><?= $arrow('esquerda') ?></button>
                </form>
            <?php endif; ?>
            <button type="button" class="shot-open" data-photo-open="/veiculos/<?= $vehicleId ?>/fotos/<?= $imageId ?>" aria-label="Ampliar foto">
                <img src="/veiculos/<?= $vehicleId ?>/fotos/<?= $imageId ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>">
            </button>
            <?php if ($index === 0): ?><span class="shot-cover">Capa</span><?php endif; ?>
            <?php if ($editing): ?>
                <form method="post" action="/veiculos/<?= $vehicleId ?>/fotos/<?= $imageId ?>/mover" class="shot-move shot-move-right">
                    <?= csrf_field() ?>
                    <input type="hidden" name="direction" value="direita">
                    <button type="submit" aria-label="Mover para a direita"<?= $index === count($images) - 1 ? ' disabled' : '' ?>><?= $arrow('direita') ?></button>
                </form>
                <form method="post" action="/veiculos/<?= $vehicleId ?>/fotos/<?= $imageId ?>/excluir" class="shot-delete" data-photo-delete>
                    <?= csrf_field() ?>
                    <button class="btn-danger" type="submit">Excluir</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<div class="photo-zoom" data-photo-zoom hidden>
    <button type="button" class="photo-zoom-close" data-photo-close aria-label="Fechar">Fechar</button>
    <img alt="">
</div>
<?php endif; ?>
<div class="split">
    <article class="card">
        <p><span class="badge st-<?= e($vehicle['display_status']) ?>"><?= e(Statuses::label('vehicle', $vehicle['display_status'])) ?></span></p>
        <p><?= (int) $vehicle['capacity'] ?> lugares<?php if ($vehicle['model_year']): ?> · <?= (int) $vehicle['model_year'] ?><?php endif; ?></p>
        <?php if ($fullPlate): ?><p>Placa: <?= e((string) ($vehicle['plate'] ?: 'não informada')) ?></p><?php endif; ?>
        <p><?= e((string) $vehicle['description']) ?></p>
        <p><?= e((string) $vehicle['notes']) ?></p>
        <h2>Preços</h2>
        <?php foreach ($prices as $price): ?><p><?= e($price['name']) ?> · <?= $price['price_cents'] !== null ? e(money((int) $price['price_cents'])) : 'não oferecido' ?></p><?php endforeach; ?>
        <?php if (\App\Auth\Auth::can('vehicles.manage')): ?>
            <p><a href="/veiculos/<?= (int) $vehicle['id'] ?>?editar=1">Os dados são editados no formulário abaixo.</a></p>
            <form method="post" action="/veiculos/<?= (int) $vehicle['id'] ?>/excluir"><?= csrf_field() ?><button class="btn-danger" data-confirm="Desativar este veículo?" type="submit">Desativar</button></form>
        <?php endif; ?>
    </article>
    <?php if (\App\Auth\Auth::can('vehicles.manage')): ?>
    <form method="post" action="/veiculos/<?= (int) $vehicle['id'] ?>/manutencao" class="card">
        <?= csrf_field() ?>
        <h2>Manutenção</h2>
        <div class="field"><label>Descrição</label><textarea name="description" required></textarea></div>
        <div class="field"><label>Início</label><input type="datetime-local" name="starts_at" required></div>
        <div class="field"><label>Fim</label><input type="datetime-local" name="ends_at" required></div>
        <div class="field"><label>Custo</label><input name="cost" value="0,00"></div>
        <label class="check"><input type="checkbox" name="mark_unavailable" value="1"> Marcar o veículo como em manutenção</label>
        <button type="submit">Registrar bloqueio</button>
    </form>
    <?php endif; ?>
</div>
<?php if (\App\Auth\Auth::can('vehicles.manage')): ?>
<div class="stack"><?php $prices = $prices; require base_path('views/vehicles/form.php'); ?></div>
<?php endif; ?>
<article class="card stack">
    <h2>Histórico de manutenção</h2>
    <?php foreach ($logs as $log): ?><p><?= e(date_br($log['starts_at'])) ?> – <?= e(date_br($log['ends_at'])) ?> · <?= e($log['description']) ?> · <?= e(money((int) $log['cost_cents'])) ?></p><?php endforeach; ?>
    <?php if ($logs === []): ?><p class="muted">Nenhum registro.</p><?php endif; ?>
</article>
