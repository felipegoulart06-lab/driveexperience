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
<p class="field-help">O mapa atualiza sozinho. O carro aparece na rua quando a tag instalada nele enviar a posição.</p>
<div class="track-layout" data-track data-feed="/rastreio/posicoes"<?= !empty($canManage) ? ' data-manage="1"' : '' ?>>
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
<div class="split">
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
        <div class="full"><button type="submit">Cadastrar tag</button></div>
    </form>
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
    </article>
</div>
<?php endif; ?>
