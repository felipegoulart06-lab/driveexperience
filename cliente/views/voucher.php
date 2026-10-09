<div class="wrap page">
    <article class="panel">
        <p class="kicker">Vale-presente</p>
        <h1><?= e(trim((string) $voucher['brand'] . ' ' . (string) $voucher['model'])) ?></h1>
        <p><?= e((string) $voucher['modality']) ?></p>
        <p>Para <?= e($voucher['recipient_name']) ?><br>De <?= e($voucher['giver_name']) ?></p>
        <p><?= e((string) $voucher['message']) ?></p>
        <p class="mono"><?= e($voucher['code']) ?></p>
        <span class="badge wait"><?= e($voucher['status']) ?></span>
        <p class="meta">Enquanto o status for aguardando pagamento, este vale não pode ser usado.</p>
    </article>
</div>
