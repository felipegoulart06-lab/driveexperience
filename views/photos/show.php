<?php use App\Domain\Statuses; ?>
<article class="card">
    <p><?= e($item['full_name']) ?> · <?= e(money((int) $item['amount_cents'])) ?></p>
    <p>Contratado em <?= e(date_br($item['contracted_at'])) ?><?php if ($item['code']): ?> · reserva <span class="code"><?= e($item['code']) ?></span><?php endif; ?></p>
    <p>Entrega prevista <?= e(date_br($item['due_date'])) ?> · efetiva <?= e(date_br($item['delivered_at'])) ?></p>
    <?php if ($item['private_link']): ?><p>Link privado: <?= e($item['private_link']) ?></p><?php endif; ?>
    <p><?= e((string) $item['notes']) ?></p>
    <?php if (\App\Auth\Auth::can('documents.issue')): ?>
        <div class="actions">
            <form method="post" action="/fotos/<?= (int) $item['id'] ?>/documentos/ficha_producao"><?= csrf_field() ?><button class="ghost" type="submit">Ficha de produção</button></form>
            <form method="post" action="/fotos/<?= (int) $item['id'] ?>/documentos/comprovante_entrega"><?= csrf_field() ?><button class="ghost" type="submit">Comprovante de entrega</button></form>
        </div>
    <?php endif; ?>
</article>
<?php if (\App\Auth\Auth::can('photos.manage')): ?>
<form method="post" action="/fotos/<?= (int) $item['id'] ?>" class="card stack form-grid">
    <?= csrf_field() ?>
    <div class="field"><label>Situação</label><select name="status"><?php foreach (Statuses::PRODUCTION as $code => $label): ?><option value="<?= e($code) ?>" <?= $item['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Responsável</label><select name="producer_user_id"><option value="">Não definido</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= (int) $item['producer_user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Previsão</label><input type="date" name="due_date" value="<?= e((string) $item['due_date']) ?>"></div>
    <div class="field"><label>Link privado</label><input name="private_link" value="<?= e((string) $item['private_link']) ?>"></div>
    <div class="field full"><label>Observações</label><textarea name="notes"><?= e((string) $item['notes']) ?></textarea></div>
    <div class="full"><button type="submit">Atualizar produção</button></div>
</form>
<?php endif; ?>
