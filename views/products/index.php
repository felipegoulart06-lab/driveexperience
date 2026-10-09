<?php
$types = ['experiencia' => 'Experiência', 'foto' => 'Pacote de fotos', 'outro' => 'Outro'];
$editing = null;
foreach ($items as $item) {
    if ((string) ($_GET['editar'] ?? '') === (string) $item['id']) {
        $editing = $item;
    }
}
$creating = \App\Auth\Auth::can('products.manage') && isset($_GET['novo']);
$priceLabel = static function (array $item): string {
    if ($item['type'] === 'experiencia' && (int) $item['base_price_cents'] === 0) {
        return '';
    }
    return money((int) $item['base_price_cents']);
};
?>
<?php if (\App\Auth\Auth::can('products.manage') && !$creating && !$editing): ?>
<div class="toolbar">
    <a class="button-link" href="/produtos?novo=1">Novo produto</a>
</div>
<?php endif; ?>

<?php if ($creating || $editing): ?>
<form method="post" action="<?= $editing ? '/produtos/' . (int) $editing['id'] : '/produtos' ?>" class="card stack product-form">
    <?= csrf_field() ?>
    <?php if ($editing): ?><input type="hidden" name="active" value="0"><?php endif; ?>
    <h2><?= $editing ? 'Editar ' . e($editing['name']) : 'Novo produto' ?></h2>
    <div class="form-grid">
        <div class="field"><label>Nome</label><input name="name" required value="<?= e((string) ($editing['name'] ?? '')) ?>"></div>
        <div class="field"><label>Tipo</label>
            <select name="type">
                <?php foreach ($types as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= ($editing['type'] ?? 'experiencia') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Duração (min)</label><input type="number" name="duration_minutes" min="0" value="<?= (int) ($editing['duration_minutes'] ?? 0) ?>"></div>
        <div class="field"><label>Preço base</label><input name="base_price" required value="<?= e($editing ? number_format(((int) $editing['base_price_cents']) / 100, 2, ',', '.') : '') ?>" placeholder="0,00"></div>
        <div class="field span-2"><label>Descrição</label><textarea name="description" rows="3"><?= e((string) ($editing['description'] ?? '')) ?></textarea></div>
        <?php if ($editing): ?>
            <label class="inline-check"><input type="checkbox" name="active" value="1" <?= (int) $editing['active'] ? 'checked' : '' ?>> Ativo</label>
        <?php endif; ?>
    </div>
    <p class="field-help">Experiência usa o preço cadastrado no veículo. O preço base vale para pacotes de fotos e para os demais produtos.</p>
    <div class="client-actions">
        <button type="submit"><?= $editing ? 'Salvar' : 'Cadastrar' ?></button>
        <a class="ghost sheet-action" href="/produtos">Cancelar</a>
    </div>
</form>
<?php endif; ?>

<div class="card table-wrap stack">
    <table class="products-table">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Duração</th>
                <th class="num">Preço base</th>
                <th>Situação</th>
                <?php if (\App\Auth\Auth::can('products.manage')): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <span class="product-name"><?= e($item['name']) ?></span>
                    <?php if ((string) $item['description'] !== ''): ?><span class="product-desc"><?= e((string) $item['description']) ?></span><?php endif; ?>
                </td>
                <td><?= e($types[$item['type']] ?? $item['type']) ?></td>
                <td class="when"><?= (int) $item['duration_minutes'] > 0 ? (int) $item['duration_minutes'] . ' min' : '—' ?></td>
                <td class="num"><?php $price = $priceLabel($item); if ($price !== ''): ?><?= e($price) ?><?php else: ?><span class="muted">Tabela do veículo</span><?php endif; ?></td>
                <td><?= (int) $item['active'] ? 'Ativo' : 'Inativo' ?></td>
                <?php if (\App\Auth\Auth::can('products.manage')): ?>
                    <td class="row-action"><a class="ghost row-edit" href="/produtos?editar=<?= (int) $item['id'] ?>">Editar</a></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?><tr><td colspan="<?= \App\Auth\Auth::can('products.manage') ? 6 : 5 ?>">Nenhum produto cadastrado.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= \App\Auth\Auth::can('products.manage') ? 6 : 5 ?>"><?= count($items) ?> produto(s)</td></tr></tfoot>
    </table>
</div>
<p class="doc-note">A duração entra na agenda da reserva. O preço de uma experiência continua sendo o da tabela do veículo.</p>
