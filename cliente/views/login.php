<div class="auth">
    <div class="auth-photo"></div>
    <div class="auth-card">
        <p class="brand">DRIVE XPERIENCE</p>
        <h1><?= $mode === 'cadastrar' ? 'Criar conta' : 'Entrar' ?></h1>
        <?php if ($mode === 'cadastrar'): ?>
            <form method="post" action="/cadastrar">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="referral" value="<?= e((string) ($_GET['indicacao'] ?? '')) ?>">
                <div class="field"><span>Nome completo</span><input name="name" autocomplete="name" required></div>
                <div class="field"><span>E-mail</span><input type="email" name="email" autocomplete="email" required></div>
                <div class="field"><span>Telefone</span><input name="phone" autocomplete="tel" required></div>
                <div class="field"><span>WhatsApp</span><input name="whatsapp" autocomplete="tel"></div>
                <div class="field"><span>Nascimento</span><input type="date" name="birth_date" autocomplete="bday"></div>
                <div class="field"><span>Senha</span><input type="password" name="password" autocomplete="new-password" required></div>
                <div class="field"><span>Confirmar senha</span><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
                <label class="choice"><input type="checkbox" name="terms" value="1" required> Aceito os termos de uso</label>
                <label class="choice"><input type="checkbox" name="marketing" value="1"> Quero receber promoções</label>
                <button class="btn block" type="submit">Criar conta</button>
            </form>
            <p><a href="/entrar">Já tenho conta</a></p>
        <?php else: ?>
            <?php if (env('SEED_DEMO') === '1'): ?><p class="meta">Conta de demonstração<br><span class="mono">marina.costa@demonstracao.local</span><br>Senha DriveApp2026</p><?php endif; ?>
            <form method="post" action="/entrar">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field"><span>E-mail</span><input type="email" name="email" autocomplete="email" required></div>
                <div class="field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" required></div>
                <button class="btn block" type="submit">Entrar</button>
            </form>
            <p><a href="/cadastrar">Criar conta</a></p>
        <?php endif; ?>
    </div>
</div>
