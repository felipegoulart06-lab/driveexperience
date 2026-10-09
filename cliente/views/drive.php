<div class="wrap page account">
    <?php if (!$user): ?>
        <h1>Perfil</h1>
        <p>Entre para ver pedidos, vouchers e avisos.</p>
        <a class="btn" href="/entrar">Entrar</a>
    <?php else: ?>
        <header class="account-head">
            <p class="kicker">Perfil</p>
            <h1><?= e($user['full_name']) ?></h1>
            <p><?= e($user['email']) ?></p>
        </header>
        <nav class="account-menu" aria-label="Conta">
            <a href="/pedidos">Meus pedidos</a>
            <a href="/vouchers">Meus vouchers</a>
            <a href="/fidelidade">Drive Points</a>
            <a href="/indique">Indicações</a>
            <a href="/notificacoes">Avisos</a>
        </nav>
        <div class="account-actions">
            <form method="post" action="/sair"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn secondary" type="submit">Sair</button></form>
            <form method="post" action="/conta/excluir"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn secondary danger" type="submit">Pedir exclusão da conta</button></form>
        </div>
    <?php endif; ?>
</div>
