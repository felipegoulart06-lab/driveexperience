<div class="wrap page">
    <div class="shot square"><?php if (!empty($product['image_path'])): ?><img class="cover" src="/assets/img/<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?></div>
    <h1><?= e($product['name']) ?></h1>
    <?php if ((int) $product['is_demo']): ?><span class="badge demo">Demonstração</span><?php endif; ?>
    <p><?= e((string) $product['description']) ?></p>
    <p class="price num"><?= e(money((int) ($product['promo_cents'] ?: $product['price_cents']))) ?></p>
    <p>Estoque: <?= (int) $product['stock'] ?></p>
    <form method="post" action="/carrinho">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <?php if ($product['variants']): ?>
            <div class="field"><span>Variação</span>
                <select name="variant_id" required>
                    <?php foreach ($product['variants'] as $variant): ?>
                        <option value="<?= (int) $variant['id'] ?>" <?= (int) $variant['stock'] < 1 ? 'disabled' : '' ?>><?= e(trim($variant['size'] . ' ' . $variant['color'])) ?> · <?= (int) $variant['stock'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="field"><span>Quantidade</span><input type="number" name="quantity" min="1" value="1" autocomplete="off"></div>
        <button class="btn block" type="submit">Adicionar ao carrinho</button>
    </form>
</div>
