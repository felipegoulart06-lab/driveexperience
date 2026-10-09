<form method="post" action="/conta/senha" class="card">
    <?= csrf_field() ?>
    <div class="field"><label>Senha atual</label><input type="password" name="current_password" required></div>
    <div class="field"><label>Nova senha</label><input type="password" name="password" required></div>
    <div class="field"><label>Confirmação</label><input type="password" name="password_confirmation" required><?php if (!empty($errors['password'])): ?><small class="error"><?= e($errors['password']) ?></small><?php endif; ?></div>
    <button type="submit">Alterar senha</button>
</form>
