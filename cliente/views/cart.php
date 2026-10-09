<div class="wrap page">
    <h1>Carrinho</h1>
    <?php $subtotal = 0; ?>
    <div class="list">
        <?php foreach ($lines as $line): ?>
            <?php $subtotal += (int) $line['total_cents']; ?>
            <div class="row">
                <div>
                    <strong><?= e($line['title']) ?></strong>
                    <div class="meta"><?= e($line['meta']) ?></div>
                    <form method="post" action="/carrinho/remover">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="key" value="<?= e($line['key']) ?>">
                        <button class="text-btn" type="submit">Tirar</button>
                    </form>
                </div>
                <span class="num"><?= e(money((int) $line['total_cents'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($lines === []): ?><p class="empty">O carrinho está vazio.</p><?php else: ?>
        <form method="post" action="/checkout">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="field"><span>Cupom</span><input name="coupon" autocomplete="off" placeholder="Código"></div>
            <p class="price num">Total <?= e(money($subtotal)) ?></p>
            <p class="meta">Fotos, carro e loja desta lista fecham juntos. A compra só muda para paga quando um provedor confirmar, e esse provedor não está configurado.</p>
            <button class="btn block" type="submit">Fechar pedido</button>
        </form>
    <?php endif; ?>
</div>
