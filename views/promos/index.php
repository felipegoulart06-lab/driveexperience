<?php
$canEdit = \App\Auth\Auth::can('products.manage');
$editingId = (int) ($_GET['editar'] ?? 0);
$creating = $canEdit && isset($_GET['novo']) && count($items) < 3;
$editing = null;
foreach ($items as $item) {
    if ((int) $item['id'] === $editingId) {
        $editing = $item;
        break;
    }
}
?>
<p class="field-help">Até 3 promoções do mês no aplicativo. O usual é uma data comemorativa do ano: a imagem e o texto são os que você publicar aqui.</p>

<?php if ($canEdit && !$creating && !$editing): ?>
<div class="toolbar">
    <?php if (count($items) < 3): ?>
        <a class="button-link" href="/promocoes-app?novo=1">Nova promoção</a>
    <?php else: ?>
        <p class="field-help">As 3 vagas do mês estão ocupadas. Retire uma para publicar outra.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($creating): ?>
<form method="post" action="/promocoes-app" enctype="multipart/form-data" class="card promo-form">
    <?= csrf_field() ?>
    <div class="promo-form-head"><h2>Nova promoção do mês</h2></div>
    <div class="promo-layout">
        <div class="promo-media">
            <div class="promo-empty">A imagem aparece aqui</div>
            <label class="promo-file">Imagem<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <p class="field-help">JPG, PNG ou WebP, até 4 MB.</p>
        </div>
        <div class="form-grid promo-fields">
            <div class="field"><label>Data comemorativa</label><input name="occasion" required maxlength="80" placeholder="Dia das Crianças"></div>
            <div class="field"><label>Título</label><input name="title" required maxlength="80"></div>
            <div class="field"><label>Início</label><input type="date" name="starts_on" required></div>
            <div class="field"><label>Fim</label><input type="date" name="ends_on" required></div>
            <div class="field promo-span"><label>Texto no aplicativo</label><textarea name="summary" rows="3" required maxlength="240"></textarea></div>
            <div class="field promo-span"><label>Link interno</label><input name="href" value="/experiencias" required></div>
        </div>
    </div>
    <div class="promo-form-foot">
        <button type="submit">Publicar</button>
        <a class="ghost sheet-action" href="/promocoes-app">Cancelar</a>
        <label class="choice"><input type="checkbox" name="active" value="1" checked> Publicada no aplicativo</label>
    </div>
</form>
<?php endif; ?>

<?php if ($editing && $canEdit): ?>
<form method="post" action="/promocoes-app/<?= (int) $editing['id'] ?>" enctype="multipart/form-data" class="card promo-form">
    <?= csrf_field() ?>
    <div class="promo-form-head">
        <h2>Editar <?= e($editing['title']) ?></h2>
        <?php if ((int) $editing['is_demo']): ?><span class="badge st-novo">Demonstração</span><?php endif; ?>
    </div>
    <div class="promo-layout">
        <div class="promo-media">
            <?php if ($editing['image_file_id']): ?>
                <img class="promo-shot" src="/promocoes-app/<?= (int) $editing['id'] ?>/imagem" alt="">
            <?php else: ?>
                <div class="promo-empty">Sem imagem</div>
            <?php endif; ?>
            <label class="promo-file">Trocar imagem<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <p class="field-help">JPG, PNG ou WebP, até 4 MB. Sem arquivo novo, a imagem atual permanece.</p>
        </div>
        <div class="form-grid promo-fields">
            <div class="field"><label>Data comemorativa</label><input name="occasion" value="<?= e($editing['occasion']) ?>" required maxlength="80"></div>
            <div class="field"><label>Título</label><input name="title" value="<?= e($editing['title']) ?>" required maxlength="80"></div>
            <div class="field"><label>Início</label><input type="date" name="starts_on" value="<?= e(substr((string) $editing['starts_on'], 0, 10)) ?>" required></div>
            <div class="field"><label>Fim</label><input type="date" name="ends_on" value="<?= e(substr((string) $editing['ends_on'], 0, 10)) ?>" required></div>
            <div class="field promo-span"><label>Texto no aplicativo</label><textarea name="summary" rows="3" required maxlength="240"><?= e($editing['summary']) ?></textarea></div>
            <div class="field promo-span"><label>Link interno</label><input name="href" value="<?= e($editing['href']) ?>" required></div>
        </div>
    </div>
    <div class="promo-form-foot">
        <button type="submit">Salvar</button>
        <a class="ghost sheet-action" href="/promocoes-app">Cancelar</a>
        <label class="choice"><input type="checkbox" name="active" value="1"<?= (int) $editing['active'] ? ' checked' : '' ?>> Publicada no aplicativo</label>
        <button type="submit" class="btn-danger promo-remove" form="promo-remove-<?= (int) $editing['id'] ?>" data-confirm="Retirar esta promoção do aplicativo?">Retirar promoção</button>
    </div>
</form>
<form id="promo-remove-<?= (int) $editing['id'] ?>" method="post" action="/promocoes-app/<?= (int) $editing['id'] ?>/excluir"><?= csrf_field() ?></form>
<?php endif; ?>

<div class="card table-wrap">
    <table>
        <thead>
            <tr>
                <th>Promoção</th>
                <th>Ocasião</th>
                <th>Período</th>
                <th>Situação</th>
                <?php if ($canEdit): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <strong><?= e($item['title']) ?></strong>
                    <?php if ((int) $item['is_demo']): ?> <span class="badge st-novo">Demonstração</span><?php endif; ?>
                    <div class="muted"><?= e(mb_strimwidth((string) $item['summary'], 0, 90, '…')) ?></div>
                </td>
                <td><?= e($item['occasion']) ?></td>
                <td class="when"><?= e(date_br(substr((string) $item['starts_on'], 0, 10))) ?> – <?= e(date_br(substr((string) $item['ends_on'], 0, 10))) ?></td>
                <td><span class="badge <?= (int) $item['active'] ? 'st-disponivel' : 'st-indisponivel' ?>"><?= (int) $item['active'] ? 'Publicada' : 'Oculta' ?></span></td>
                <?php if ($canEdit): ?>
                    <td class="row-action">
                        <?php if ($editing && (int) $editing['id'] === (int) $item['id']): ?>
                            <a class="ghost" href="/promocoes-app">Fechar</a>
                        <?php else: ?>
                            <a class="ghost" href="/promocoes-app?editar=<?= (int) $item['id'] ?>">Editar</a>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
            <tr><td colspan="<?= $canEdit ? 5 : 4 ?>">Nenhuma promoção do mês cadastrada.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $canEdit ? 5 : 4 ?>"><?= count($items) ?> / 3 promoção(ões)</td></tr></tfoot>
    </table>
</div>
