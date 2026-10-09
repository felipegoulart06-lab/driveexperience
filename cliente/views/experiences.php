<?php
$lead = null;
$vehicleFrom = null;
foreach ($vehicles as $vehicle) {
    if ($lead === null && !empty($vehicle['image_file_id'])) {
        $lead = $vehicle;
    }
    if (!empty($vehicle['from_cents']) && ($vehicleFrom === null || (int) $vehicle['from_cents'] < $vehicleFrom)) {
        $vehicleFrom = (int) $vehicle['from_cents'];
    }
}
$photoFrom = null;
foreach ($photos as $photo) {
    $cents = (int) $photo['base_price_cents'];
    if ($photoFrom === null || $cents < $photoFrom) {
        $photoFrom = $cents;
    }
}
?>
<?php
$pista = require CLIENT_PATH . '/content/pista.php';
$people = $pista['people'];
$peopleTitle = 'Na pista';
$peopleIntro = $pista['intro'];
$peopleCta = true;
?>
<div class="wrap page">
    <h1>Experiências</h1>
    <p class="lead">Escolha o tipo. Os carros aparecem só no card de veículos.</p>
    <?php require CLIENT_PATH . '/views/partials/people.php'; ?>
    <div class="kinds">
        <a class="kind" href="/experiencias/veiculos">
            <div class="shot wide"><?php if ($lead): ?><img class="cover" src="/midia/<?= (int) $lead['image_file_id'] ?>" alt=""><?php endif; ?></div>
            <div class="kind-body">
                <h2>Veículos</h2>
                <p>Direção e carona nos supercarros da pista.</p>
                <?php if ($vehicleFrom): ?><p class="price num">a partir de <?= e(money($vehicleFrom)) ?></p><?php endif; ?>
            </div>
        </a>
        <a class="kind" href="/experiencias/fotos">
            <div class="kind-band kind-blue"><h2>Fotos</h2></div>
            <div class="kind-body">
                <p>Pacotes Prata, Ouro e Diamante, com o preço da tabela.</p>
                <?php if ($photoFrom): ?><p class="price num">a partir de <?= e(money($photoFrom)) ?></p><?php endif; ?>
            </div>
        </a>
        <a class="kind" href="/experiencias/casamentos">
            <div class="kind-band kind-green"><h2>Casamentos</h2></div>
            <div class="kind-body">
                <p>Carro no cortejo e fotos na mesma compra.</p>
                <p class="meta">Valor do formato com a equipe</p>
            </div>
        </a>
        <a class="kind" href="/experiencias/eventos">
            <div class="kind-band kind-amber"><h2>Eventos</h2></div>
            <div class="kind-body">
                <p>Lançamento, formatura e encontro de empresa.</p>
                <p class="meta">Valor do formato com a equipe</p>
            </div>
        </a>
    </div>
</div>
