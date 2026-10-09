<div class="wrap page">
    <h1>Meus vouchers</h1>
    <div class="list">
        <?php foreach ($vouchers as $voucher): ?>
            <a href="/vouchers/<?= e($voucher['code']) ?>"><span></span><span><strong class="mono"><?= e($voucher['code']) ?></strong><br><?= e($voucher['recipient_name']) ?></span><span class="badge wait"><?= e($voucher['status']) ?></span></a>
        <?php endforeach; ?>
    </div>
    <?php if ($vouchers === []): ?><p class="empty">Nenhum vale.</p><?php endif; ?>
</div>
