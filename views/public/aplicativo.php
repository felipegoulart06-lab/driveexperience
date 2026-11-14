<header class="top">
    <strong>DRIVE XPERIENCE</strong>
    <a href="/indicacao">Indique e ganhe</a>
</header>
<section class="hero" id="baixar">
    <p class="kicker">Play Store</p>
    <h1>O aplicativo da <?= e($company) ?> chega na Play Store.</h1>
    <p class="lead">Por enquanto o botão do WhatsApp abre esta página. Quando a ficha da loja estiver no ar, o mesmo botão passa a abrir a Play Store.</p>
    <div class="actions">
        <?php if ($play !== ''): ?>
            <a href="<?= e($play) ?>">Abrir na Play Store</a>
        <?php else: ?>
            <a href="#recursos">Ver o que entra no app</a>
        <?php endif; ?>
    </div>
</section>
<section class="wrap" id="recursos">
    <div class="app-grid">
        <div>
            <h2>O que o aplicativo reúne</h2>
            <p class="intro">A operação continua no painel. O aplicativo é o lugar do cliente depois da experiência.</p>
            <div class="steps">
                <article class="step"><b>Agenda</b><p>Data, horário e veículo da reserva, no bolso.</p></article>
                <article class="step"><b>Fotos</b><p>Os pacotes Prata, Ouro e Diamante, com o link da entrega.</p></article>
                <article class="step"><b>Indicação</b><p>A mesma tabela de comissões, para indicar a próxima experiência.</p></article>
            </div>
        </div>
        <aside class="phone">
            <p class="kicker">No celular</p>
            <ol>
                <li>Abrir a reserva do dia.</li>
                <li>Ver o status do pagamento e dos documentos.</li>
                <li>Receber as fotos quando a produção entregar.</li>
                <li>Indicar um amigo e acompanhar a comissão.</li>
            </ol>
        </aside>
    </div>
</section>
<p class="foot"><?= e($company) ?> · aplicativo Android, lançamento na Play Store.</p>
