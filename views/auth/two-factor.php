<form method="post" action="/login/2fa" class="login-2fa">
    <?= csrf_field() ?>
    <?php if (!empty($enroll)): ?>
        <p class="field-help">O 2FA é obrigatório para o admin master e para os funcionários. Escaneie no aplicativo autenticador. Este mesmo código entra na plataforma e libera o WhatsApp.</p>
        <img class="wa-qr" src="<?= e((string) $qr) ?>" alt="QR Code do 2FA">
        <p class="wa-secret"><?= e((string) $secret) ?></p>
    <?php else: ?>
        <p class="field-help">Informe o código do autenticador. É o mesmo 2FA do WhatsApp e vale para admin master e funcionários.</p>
    <?php endif; ?>
    <div class="field">
        <label>Código</label>
        <input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required pattern="[0-9]{6}">
        <?php if (!empty($errors['code'])): ?><small class="error"><?= e($errors['code']) ?></small><?php endif; ?>
        <?php if (!empty($errors['email'])): ?><small class="error"><?= e($errors['email']) ?></small><?php endif; ?>
    </div>
    <button type="submit">Confirmar</button>
</form>
<p class="field-help"><a href="/login?cancelar=1">Voltar ao login</a></p>
