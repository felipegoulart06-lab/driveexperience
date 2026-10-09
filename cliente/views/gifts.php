<div class="wrap page">
    <h1>Vales-presente</h1>
    <form method="post" action="/vales">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="field"><span>Veículo</span>
            <select name="vehicle_id" required>
                <?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field"><span>Modalidade</span>
            <select name="modality_id" required>
                <?php $mods = \App\Database\Connection::all("SELECT id, name FROM modalities WHERE type = 'experiencia' AND active = 1 ORDER BY sort_order"); ?>
                <?php foreach ($mods as $mod): ?><option value="<?= (int) $mod['id'] ?>"><?= e($mod['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field"><span>Quem recebe</span><input name="recipient" required autocomplete="name"></div>
        <div class="field"><span>Quem presenteia</span><input name="giver" required autocomplete="name" value="<?= e((string) ($user['full_name'] ?? '')) ?>"></div>
        <div class="field"><span>Mensagem</span><textarea name="message" maxlength="280"></textarea></div>
        <p class="meta">O voucher só fica disponível depois que o pagamento for confirmado pelo provedor. Essa confirmação ainda não está ligada.</p>
        <button class="btn block" type="submit">Registrar vale</button>
    </form>
</div>
