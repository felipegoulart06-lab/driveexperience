<div class="wrap page">
    <section class="points">
        <h1>Indique e ganhe</h1>
        <p>A recompensa só entra depois da condição da campanha. Cadastro sozinho deixa a indicação pendente.</p>
        <p class="mono"><?= e($code) ?></p>
    </section>
    <p>Link: <?= e((isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/cadastrar?indicacao=' . $code) ?></p>
    <a class="btn secondary" href="https://wa.me/?text=<?= rawurlencode('Venha para a Drive Xperience. Use o código ' . $code) ?>">WhatsApp</a>
    <div class="stats">
        <div><span>Indicados</span><strong><?= count($events) ?></strong></div>
        <div><span>Pendentes</span><strong><?= count(array_filter($events, static fn ($row) => $row['status'] === 'pendente')) ?></strong></div>
        <div><span>Aprovados</span><strong><?= count(array_filter($events, static fn ($row) => $row['status'] === 'aprovado')) ?></strong></div>
    </div>
</div>
