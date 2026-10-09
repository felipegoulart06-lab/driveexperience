<div class="wrap page">
    <h1>Drive Points</h1>
    <section class="points">
        <p class="kicker">Saldo</p>
        <div class="saldo num"><?= (int) $balance['available'] ?></div>
        <div class="stats">
            <div><span>Acumulados</span><strong class="num"><?= (int) $balance['earned'] ?></strong></div>
            <div><span>Utilizados</span><strong class="num"><?= (int) $balance['spent'] ?></strong></div>
            <div><span>A vencer</span><strong class="num">0</strong></div>
        </div>
    </section>
    <section class="section panel">
        <h2>Acumular pontos</h2>
        <p>Mostre este código à equipe. Ele vale 2 minutos e não credita pontos sozinho.</p>
        <p class="mono token"><?= e($visit['token']) ?></p>
    </section>
    <section class="section">
        <h2>Recompensas</h2>
        <?php foreach ($rewards as $reward): ?>
            <form method="post" action="/fidelidade/resgatar" class="choice">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>">
                <span><?= e($reward['name']) ?><?php if ((int) $reward['is_demo']): ?> <span class="badge demo">Demonstração</span><?php endif; ?><br><small><?= (int) $reward['cost_points'] ?> pts</small></span>
                <button class="btn" type="submit" <?= $balance['available'] < (int) $reward['cost_points'] ? 'disabled' : '' ?>><?= $balance['available'] < (int) $reward['cost_points'] ? 'Saldo insuficiente' : 'Resgatar' ?></button>
            </form>
        <?php endforeach; ?>
    </section>
    <section class="section list">
        <h2>Extrato</h2>
        <?php foreach ($entries as $entry): ?>
            <div class="row"><div><?= e($entry['reason']) ?><div class="mono"><?= e((string) $entry['created_at']) ?></div></div><strong class="num"><?= (int) $entry['delta'] > 0 ? '+' : '' ?><?= (int) $entry['delta'] ?></strong></div>
        <?php endforeach; ?>
        <?php if ($entries === []): ?><p class="empty">Nenhuma movimentação.</p><?php endif; ?>
    </section>
</div>
