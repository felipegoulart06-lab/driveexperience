<?php
$pista = require CLIENT_PATH . '/content/pista.php';
$people = $pista['people'];
$peopleTitle = 'Fotos da pista';
$peopleIntro = $pista['intro'];
?>
<div class="wrap page">
    <p class="kicker"><a href="/experiencias">Experiências</a></p>
    <h1>Fotos</h1>
    <p>O pacote entra no carrinho. Se depois você escolher um carro, a experiência entra na mesma compra.</p>
    <?php require CLIENT_PATH . '/views/partials/people.php'; ?>
    <?php foreach ($photos as $photo): ?>
        <article class="panel stack">
            <h2><?= e($photo['name']) ?></h2>
            <p><?= e((string) $photo['description']) ?></p>
            <p class="price num"><?= e(money((int) $photo['base_price_cents'])) ?></p>
            <details class="pack-details">
                <summary>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 8h.01"/></svg>
                    <span>Ver detalhes</span>
                </summary>
                <div>
                    <p><?= e((string) $photo['description']) ?> O pacote entra no carrinho por este valor. Se você escolher um carro depois, a experiência entra na mesma compra.</p>
                </div>
            </details>
            <form method="post" action="/carrinho">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="kind" value="foto">
                <input type="hidden" name="modality_id" value="<?= (int) $photo['id'] ?>">
                <button class="btn" type="submit">Adicionar à compra</button>
            </form>
        </article>
    <?php endforeach; ?>
    <?php if ($photos === []): ?><p class="empty">Nenhum pacote de fotos ativo no cadastro.</p><?php endif; ?>
</div>
