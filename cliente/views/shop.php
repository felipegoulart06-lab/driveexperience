<div class="wrap page">
    <h1>Loja</h1>
    <p class="meta">Os produtos marcados como demonstração não são o catálogo oficial. O sistema administrativo ainda não gerencia esta vitrine.</p>
    <div class="grid-2">
        <?php foreach ($products as $product): ?>
            <a class="card" href="/loja/<?= e($product['slug']) ?>">
                <div class="shot square"><?php if (!empty($product['image_path'])): ?><img class="cover" src="/assets/img/<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?></div>
                <h3><?= e($product['name']) ?></h3>
                <p class="offer"><?php if ((int) $product['is_demo']): ?><span class="badge demo">Demonstração</span><?php endif; ?><span class="price num"><?= e(money((int) ($product['promo_cents'] ?: $product['price_cents']))) ?></span></p>
            </a>
        <?php endforeach; ?>
    </div>
</div>
