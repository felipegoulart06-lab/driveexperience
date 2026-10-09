<?php
$pista = require CLIENT_PATH . '/content/pista.php';
$people = $pista['people'];
$peopleTitle = 'Na pista';
$peopleIntro = $pista['intro'];
$peopleCta = true;
?>
<div class="wrap page">
    <p class="kicker"><a href="/experiencias">Experiências</a></p>
    <h1>Veículos</h1>
    <?php require CLIENT_PATH . '/views/partials/people.php'; ?>
    <div class="section-head"><h2>Escolha o carro</h2></div>
    <?php foreach ($vehicles as $vehicle): ?>
        <a class="card stack" href="/experiencias/<?= e($vehicle['slug']) ?>">
            <div class="shot wide"><?php if ($vehicle['image_file_id']): ?><img class="cover" src="/midia/<?= (int) $vehicle['image_file_id'] ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model'] . ', foto de capa') ?>"><?php endif; ?><span class="off">10% off</span></div>
            <h3><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></h3>
            <p><?= (int) $vehicle['capacity'] ?> lugares<?php if ($vehicle['from_cents']): ?> · <span class="price num"><?= e(money((int) $vehicle['from_cents'])) ?></span><?php endif; ?></p>
        </a>
    <?php endforeach; ?>
    <?php if ($vehicles === []): ?><p class="empty">Nenhum veículo ativo no cadastro.</p><?php endif; ?>
</div>
