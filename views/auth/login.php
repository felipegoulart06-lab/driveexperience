<form method="post" action="/login">
    <?= csrf_field() ?>
    <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e(field($old, [], 'email')) ?>" required><?php if (!empty($errors['email'])): ?><small class="error"><?= e($errors['email']) ?></small><?php endif; ?></div>
    <div class="field"><label>Senha</label><input type="password" name="password" required></div>
    <button type="submit">Entrar</button>
</form>
