<?php use App\Domain\Statuses; ?>
<form method="post" action="<?= empty($vehicle['id']) ? '/veiculos' : '/veiculos/' . (int) $vehicle['id'] ?>" class="card form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="active" value="0">
    <div class="field"><label>Marca</label><input name="brand" value="<?= e(field($old, $vehicle, 'brand')) ?>" required></div>
    <div class="field"><label>Modelo</label><input name="model" value="<?= e(field($old, $vehicle, 'model')) ?>" required></div>
    <div class="field"><label>Ano</label><input name="model_year" value="<?= e(field($old, $vehicle, 'model_year')) ?>"></div>
    <?php if (\App\Auth\Auth::can('vehicles.sensitive')): ?><div class="field"><label>Placa</label><input name="plate" value="<?= e(field($old, $vehicle, 'plate')) ?>"></div><?php endif; ?>
    <div class="field"><label>Capacidade</label><input type="number" name="capacity" min="1" value="<?= e(field($old, $vehicle, 'capacity', '2')) ?>"></div>
    <div class="field"><label>Situação cadastrada</label>
        <select name="base_status">
            <?php foreach (['disponivel' => 'Disponível', 'em_manutencao' => 'Em manutenção', 'indisponivel' => 'Indisponível'] as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= field($old, $vehicle, 'base_status', 'disponivel') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field full"><label>Descrição</label><textarea name="description"><?= e(field($old, $vehicle, 'description')) ?></textarea></div>
    <div class="field full"><label>Observações</label><textarea name="notes"><?= e(field($old, $vehicle, 'notes')) ?></textarea></div>
    <label class="check full"><input type="checkbox" name="active" value="1" <?= (string) field($old, $vehicle, 'active', '1') === '1' ? 'checked' : '' ?>> Ativo para reservas</label>
    <div class="full"><h2>Preços por modalidade</h2></div>
    <?php foreach ($prices as $price): ?>
        <div class="field"><label><?= e($price['name']) ?></label><input name="price[<?= (int) $price['id'] ?>]" value="<?= isset($price['price_cents']) && $price['price_cents'] !== null ? e(number_format(((int) $price['price_cents']) / 100, 2, ',', '.')) : '' ?>" placeholder="Sem preço"></div>
    <?php endforeach; ?>
    <div class="full"><button type="submit">Salvar veículo</button></div>
</form>
