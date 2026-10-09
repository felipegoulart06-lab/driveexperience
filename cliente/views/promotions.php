<div class="wrap page">
    <h1>Promoções</h1>
    <?php foreach ($campaigns as $campaign): ?>
        <article class="panel stack">
            <h2><?= e($campaign['name']) ?></h2>
            <p><?= e((string) $campaign['description']) ?></p>
            <?php if ((int) $campaign['is_demo']): ?><span class="badge demo">Demonstração</span><?php endif; ?>
        </article>
    <?php endforeach; ?>
    <h2>Cupons</h2>
    <?php foreach ($coupons as $coupon): ?>
        <p class="mono"><?= e($coupon['code']) ?></p>
        <p><?= e($coupon['description']) ?><?php if ((int) $coupon['is_demo']): ?> Demonstração.<?php endif; ?></p>
    <?php endforeach; ?>
</div>
