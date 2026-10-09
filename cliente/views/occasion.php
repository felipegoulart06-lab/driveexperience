<div class="wrap page">
    <p class="kicker"><a href="/experiencias">Experiências</a></p>
    <?php if ($topic === 'casamentos'): ?>
        <h1>Casamentos</h1>
        <p>O carro do cortejo é uma experiência da frota. O pacote de fotos pode já estar no carrinho: ao escolher o veículo, ele entra na mesma compra. O formato do casamento em si é fechado com a equipe e não tem preço de tabela nesta tela.</p>
    <?php else: ?>
        <h1>Eventos</h1>
        <p>Lançamento, formatura e encontro de empresa usam o carro da frota e, se você quiser, um pacote de fotos na mesma compra. O formato do evento é fechado com a equipe e não tem preço de tabela nesta tela.</p>
    <?php endif; ?>
    <div class="kinds">
        <a class="kind" href="/experiencias/veiculos">
            <div class="kind-band kind-navy"><h2>Escolher o carro</h2></div>
            <div class="kind-body"><p>Abre a frota para montar a experiência.</p></div>
        </a>
        <a class="kind" href="/experiencias/fotos">
            <div class="kind-band kind-blue"><h2>Pacote de fotos</h2></div>
            <div class="kind-body"><p>Entra no carrinho antes ou junto com o carro.</p></div>
        </a>
    </div>
</div>
