<?php if (!empty($error)): ?><div class="flash erro"><?= e($error) ?></div><?php endif; ?>
<p class="muted">A instalação cria o banco, os cadastros iniciais e o primeiro administrador. A senha não fica gravada no código.</p>
<form method="post" action="/instalar">
    <div class="field"><label>Token de instalação</label><input type="password" name="token" required></div>
    <div class="field"><label>Nome</label><input type="text" name="admin_name" value="Administrador"></div>
    <div class="field"><label>E-mail</label><input type="email" name="admin_email" required></div>
    <div class="field"><label>Senha</label><input type="password" name="admin_password" required></div>
    <?php if (env('APP_ENV') !== 'production'): ?><label class="check"><input type="checkbox" name="demo" value="1"> Carregar dados de demonstração</label><?php endif; ?>
    <button type="submit">Instalar</button>
</form>
