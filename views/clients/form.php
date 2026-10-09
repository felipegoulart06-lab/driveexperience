<form method="post" action="/clientes" class="card form-grid">
    <?= csrf_field() ?>
    <div class="field"><label>Nome completo</label><input name="full_name" value="<?= e(field($old, [], 'full_name')) ?>" required><?php if (!empty($errors['full_name'])): ?><small class="error"><?= e($errors['full_name']) ?></small><?php endif; ?></div>
    <div class="field"><label>CPF</label><input name="cpf" value="<?= e(field($old, [], 'cpf')) ?>"><?php if (!empty($errors['cpf'])): ?><small class="error"><?= e($errors['cpf']) ?></small><?php endif; ?></div>
    <div class="field"><label>Telefone</label><input name="phone" value="<?= e(field($old, [], 'phone')) ?>"><?php if (!empty($errors['phone'])): ?><small class="error"><?= e($errors['phone']) ?></small><?php endif; ?></div>
    <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e(field($old, [], 'whatsapp')) ?>"></div>
    <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e(field($old, [], 'email')) ?>"><?php if (!empty($errors['email'])): ?><small class="error"><?= e($errors['email']) ?></small><?php endif; ?></div>
    <div class="field"><label>Data de nascimento</label><input type="date" name="birth_date" value="<?= e(field($old, [], 'birth_date')) ?>"></div>
    <div class="field"><label>Cidade</label><input name="city" value="<?= e(field($old, [], 'city')) ?>"></div>
    <div class="field"><label>Estado</label><input name="state" maxlength="2" value="<?= e(field($old, [], 'state')) ?>"></div>
    <div class="field"><label>Origem</label>
        <select name="origin_id">
            <option value="">Selecione</option>
            <?php foreach ($origins as $origin): ?><option value="<?= (int) $origin['id'] ?>" <?= field($old, [], 'origin_id') == $origin['id'] ? 'selected' : '' ?>><?= e($origin['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field"><label>Plataforma</label>
        <select name="platform_id">
            <option value="">Nenhuma</option>
            <?php foreach ($platforms as $platform): ?><option value="<?= (int) $platform['id'] ?>"><?= e($platform['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if (!empty($errors['platform_id'])): ?><small class="error"><?= e($errors['platform_id']) ?></small><?php endif; ?>
    </div>
    <div class="field"><label>Identificador externo</label><input name="external_id" value="<?= e(field($old, [], 'external_id')) ?>"></div>
    <div class="field full"><label>Observações internas</label><textarea name="notes"><?= e(field($old, [], 'notes')) ?></textarea></div>
    <div class="full"><button type="submit">Salvar cliente</button></div>
</form>
