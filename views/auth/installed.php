<p>O sistema foi instalado.</p>
<?php if (!empty($result['created'])): ?>
    <p>Guarde esta senha agora. Ela não será mostrada outra vez.</p>
    <p><strong><?= e($result['email']) ?></strong><br><?= e((string) $result['password']) ?></p>
<?php else: ?>
    <p>Já existia um administrador. Nenhum usuário novo foi criado.</p>
<?php endif; ?>
<p><a href="/login">Ir para o login</a></p>
<p class="muted">Remova a variável INSTALL_TOKEN depois de concluir.</p>
