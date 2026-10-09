<?php $canEdit = \App\Auth\Auth::can('products.manage'); ?>
<p class="field-help">Até 3 promoções do mês no aplicativo. O usual é uma data comemorativa do ano: a imagem e o texto são os que você publicar aqui.</p>
<?php foreach ($items as $item): ?>
<?php if ($canEdit): ?>
<form method="post" action="/promocoes-app/<?= (int) $item['id'] ?>" enctype="multipart/form-data" class="card stack">
    <?= csrf_field() ?>
    <h2><?= e($item['title']) ?><?php if ((int) $item['is_demo']): ?> · Demonstração<?php endif; ?></h2>
    <?php if ($item['image_file_id']): ?><p><img class="promo-shot" src="/promocoes-app/<?= (int) $item['id'] ?>/imagem" alt=""></p><?php endif; ?>
    <div class="field"><label>Data comemorativa</label><input name="occasion" value="<?= e($item['occasion']) ?>" required maxlength="80"></div>
    <div class="field"><label>Título</label><input name="title" value="<?= e($item['title']) ?>" required maxlength="80"></div>
    <div class="field"><label>Texto</label><textarea name="summary" rows="3" required maxlength="240"><?= e($item['summary']) ?></textarea></div>
    <div class="field"><label>Início</label><input type="date" name="starts_on" value="<?= e(substr((string) $item['starts_on'], 0, 10)) ?>" required></div>
    <div class="field"><label>Fim</label><input type="date" name="ends_on" value="<?= e(substr((string) $item['ends_on'], 0, 10)) ?>" required></div>
    <div class="field"><label>Link interno</label><input name="href" value="<?= e($item['href']) ?>" required></div>
    <div class="field"><label>Imagem</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><p class="field-help">JPG, PNG ou WebP, até 4 MB. Sem arquivo novo, a imagem atual permanece.</p></div>
    <label class="choice"><input type="checkbox" name="active" value="1"<?= (int) $item['active'] ? ' checked' : '' ?>> Publicada no aplicativo</label>
    <button type="submit">Salvar</button>
</form>
<form method="post" action="/promocoes-app/<?= (int) $item['id'] ?>/excluir">
    <?= csrf_field() ?>
    <button type="submit" class="btn-danger">Retirar</button>
</form>
<?php else: ?>
<article class="card stack">
    <h2><?= e($item['title']) ?></h2>
    <p><?= e($item['occasion']) ?> · <?= e(date_br(substr((string) $item['starts_on'], 0, 10))) ?> a <?= e(date_br(substr((string) $item['ends_on'], 0, 10))) ?></p>
    <p><?= e($item['summary']) ?></p>
</article>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($items === []): ?><p class="empty">Nenhuma promoção do mês cadastrada.</p><?php endif; ?>
<?php if ($canEdit && count($items) < 3): ?>
<form method="post" action="/promocoes-app" enctype="multipart/form-data" class="card stack">
    <?= csrf_field() ?>
    <h2>Nova promoção do mês</h2>
    <div class="field"><label>Data comemorativa</label><input name="occasion" required maxlength="80" placeholder="Dia das Crianças"></div>
    <div class="field"><label>Título</label><input name="title" required maxlength="80"></div>
    <div class="field"><label>Texto</label><textarea name="summary" rows="3" required maxlength="240"></textarea></div>
    <div class="field"><label>Início</label><input type="date" name="starts_on" required></div>
    <div class="field"><label>Fim</label><input type="date" name="ends_on" required></div>
    <div class="field"><label>Link interno</label><input name="href" value="/experiencias" required></div>
    <div class="field"><label>Imagem</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
    <label class="choice"><input type="checkbox" name="active" value="1" checked> Publicada no aplicativo</label>
    <button type="submit">Publicar</button>
</form>
<?php elseif ($canEdit): ?>
<p class="field-help">As 3 vagas do mês estão ocupadas. Retire uma para publicar outra.</p>
<?php endif; ?>
