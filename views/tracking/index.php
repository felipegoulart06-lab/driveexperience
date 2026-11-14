<?php
$labels = [
    'movendo' => 'Em movimento',
    'parado' => 'Parado',
    'sem_sinal' => 'Sem sinal',
    'sem_tag' => 'Sem tag',
];
$hasFix = false;
foreach ($items as $item) {
    if ($item['lat'] !== null) {
        $hasFix = true;
    }
}
?>
<p class="field-help">Mapa Mapbox. Atualiza sozinho: o carro aparece na rua quando a tag enviar a posição.</p>
<?php if (empty($mapboxToken)): ?>
<div class="flash erro">Defina a variável MAPBOX_TOKEN no ambiente para carregar o mapa.</div>
<?php endif; ?>
<div class="track-layout" data-track data-feed="/rastreio/posicoes" data-mapbox-token="<?= e((string) ($mapboxToken ?? '')) ?>"<?= !empty($canManage) ? ' data-manage="1"' : '' ?>>
    <div class="track-map" data-track-map></div>
    <aside class="card track-side">
        <h2>Frota</h2>
        <ul class="track-list" data-track-list>
            <?php foreach ($items as $item): ?>
                <?php $key = $item['id'] ? 't' . (int) $item['id'] : 'v' . (int) $item['vehicle_id']; ?>
                <li class="track-row" data-track-key="<?= e($key) ?>">
                    <div>
                        <strong><?= e((string) $item['vehicle']) ?></strong>
                        <span class="muted"><?= $item['tag'] ? e((string) $item['name']) . ' · ' . e((string) $item['tag']) : 'Nenhuma tag ligada' ?></span>
                    </div>
                    <span class="badge st-<?= e((string) $item['state']) ?>" data-track-state><?= e($labels[$item['state']] ?? 'Sem sinal') ?><?php if ($item['state'] === 'movendo' && $item['speed'] !== null): ?> · <?= e((string) (int) round((float) $item['speed'])) ?> km/h<?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="track-empty" data-track-empty<?= $hasFix ? ' hidden' : '' ?>>Nenhuma posição recebida ainda.</p>
    </aside>
</div>
<?php if (!empty($canManage)): ?>
<?php $creatingTag = isset($_GET['nova-tag']); ?>
<?php if (!$creatingTag): ?>
<div class="toolbar">
    <a class="button-link" href="/rastreio?nova-tag=1">Ligar tag</a>
    <a class="ghost sheet-action" href="/rastreio?ajuda=1">Como a tag entra no mapa</a>
</div>
<?php endif; ?>
<?php if ($creatingTag || isset($_GET['ajuda'])): ?>
<div class="split">
    <?php if ($creatingTag): ?>
    <form method="post" action="/rastreio" class="card form-grid">
        <?= csrf_field() ?>
        <div class="full"><h2>Ligar tag ao carro</h2></div>
        <div class="field"><label>Veículo</label>
            <select name="vehicle_id">
                <option value="">Definir depois</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Nome</label><input name="name" required placeholder="Tag do Camaro"></div>
        <div class="field full"><label>Código da tag</label><input name="external_id" required placeholder="IMEI ou identificador do aparelho"></div>
        <div class="full client-actions">
            <button type="submit">Cadastrar tag</button>
            <a class="ghost sheet-action" href="/rastreio">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
    <?php if (isset($_GET['ajuda']) || $creatingTag): ?>
    <article class="card stack">
        <h2>Como a tag entra no mapa</h2>
        <p>A marca ainda pode ser qualquer uma. O aparelho, ou o sistema da marca, precisa enviar a posição para este endereço. Tag que só abre no aplicativo do fabricante, sem saída de dados, não aparece aqui.</p>
        <p class="code"><?= e($endpoint) ?></p>
        <p>Cabeçalho <span class="code">Authorization: Bearer</span> com a chave abaixo. O campo <span class="code">tag</span> tem de ser o mesmo código cadastrado.</p>
        <pre class="track-sample">{
  "tag": "codigo-da-tag",
  "lat": -26.9906,
  "lng": -48.6348,
  "speed": 35,
  "heading": 90,
  "at": "2026-10-01T10:15:00-03:00"
}</pre>
        <div class="field"><label>Chave</label><input class="code" readonly value="<?= e($token) ?>"></div>
        <form method="post" action="/rastreio/chave"><?= csrf_field() ?><button class="ghost" type="submit" data-confirm="Gerar outra chave? A tag atual para de enviar até receber a chave nova.">Gerar nova chave</button></form>
        <?php foreach ($items as $item): ?>
            <?php if (empty($item['id'])) continue; ?>
            <form method="post" action="/rastreio/<?= (int) $item['id'] ?>/remover" class="track-remove"><?= csrf_field() ?><button class="btn-danger" type="submit" data-confirm="Remover a tag <?= e((string) $item['tag']) ?>?">Remover <?= e((string) $item['vehicle']) ?></button></form>
        <?php endforeach; ?>
        <?php if (!$creatingTag): ?><p><a class="ghost sheet-action" href="/rastreio">Fechar</a></p><?php endif; ?>
    </article>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
