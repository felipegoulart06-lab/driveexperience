<div class="wrap page">
    <h1>Meus pedidos</h1>
    <div class="list">
        <?php foreach ($orders as $order): ?>
            <div class="row"><div><span class="mono"><?= e($order['code']) ?></span><div class="meta"><?= e($order['kind']) ?> · <?= e(money((int) $order['total_cents'])) ?></div></div><span class="badge wait"><?= e($order['status']) ?></span></div>
        <?php endforeach; ?>
    </div>
    <h2>Reservas no sistema</h2>
    <div class="list">
        <?php foreach ($reservations as $reservation): ?>
            <div class="row"><div><span class="mono"><?= e($reservation['code']) ?></span><div class="meta"><?= e(date_br((string) $reservation['scheduled_date'])) ?> <?= e(substr((string) $reservation['start_time'], 0, 5)) ?></div></div><span class="badge"><?= e($reservation['operational_status']) ?></span></div>
        <?php endforeach; ?>
    </div>
</div>
