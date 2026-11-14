<?php use App\Domain\Statuses; ?>
<?php
$names = ['ficha_producao' => 'Ficha de produção', 'comprovante_entrega' => 'Comprovante de entrega'];
$due = trim((string) ($item['due_date'] ?? ''));
$delivered = trim((string) ($item['delivered_at'] ?? ''));
?>
<article class="card">
    <h2>Produção das fotos</h2>
    <p class="field-help">Esta tela acompanha a edição e a entrega do pacote. A venda e o pagamento ficam na reserva.</p>
    <dl class="review-list">
        <div><dt>Cliente</dt><dd><?= e($item['full_name']) ?></dd></div>
        <div><dt>Pacote</dt><dd><?= e($item['package_name'] ?: 'Pacote') ?> · <?= e(money((int) $item['amount_cents'])) ?></dd></div>
        <div><dt>Contratado</dt><dd><?= e(date_br($item['contracted_at'])) ?></dd></div>
        <div><dt>Reserva</dt><dd><?php if ((int) ($item['reservation_id'] ?? 0) > 0 && $item['code']): ?><a class="code" href="/reservas/<?= (int) $item['reservation_id'] ?>"><?= e($item['code']) ?></a><?php else: ?>Venda avulsa<?php endif; ?></dd></div>
        <div><dt>Situação</dt><dd><?= e(Statuses::label('production', (string) $item['status'])) ?></dd></div>
        <div><dt>Previsão</dt><dd><?= $due !== '' ? e(date_br($due)) : 'Ainda sem data' ?></dd></div>
        <div><dt>Entrega</dt><dd><?= $delivered !== '' ? e(date_br($delivered)) : 'Ainda não entregue' ?></dd></div>
    </dl>
    <?php if (\App\Auth\Auth::can('documents.issue')): ?>
        <h3>Documentos</h3>
        <p class="field-help">A ficha é o pedido para quem edita. O comprovante registra a entrega ao cliente. O PDF abre na hora.</p>
        <div class="actions">
            <form method="post" action="/fotos/<?= (int) $item['id'] ?>/documentos/ficha_producao"><?= csrf_field() ?><button class="ghost" type="submit">Abrir ficha de produção</button></form>
            <form method="post" action="/fotos/<?= (int) $item['id'] ?>/documentos/comprovante_entrega"><?= csrf_field() ?><button class="ghost" type="submit">Abrir comprovante de entrega</button></form>
        </div>
        <?php if ($documents !== []): ?>
            <dl class="review-list">
                <?php foreach ($documents as $document): ?>
                    <div>
                        <dt>v<?= (int) $document['version'] ?></dt>
                        <dd><a href="/documentos/<?= e($document['public_id']) ?>"><?= e($names[$document['type']] ?? 'Documento') ?></a> · <?= e(date_br($document['issued_at'])) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    <?php endif; ?>
</article>
<?php if (\App\Auth\Auth::can('photos.manage')): ?>
<form method="post" action="/fotos/<?= (int) $item['id'] ?>" class="card stack form-grid">
    <?= csrf_field() ?>
    <h2 class="full">Atualizar a produção</h2>
    <div class="field"><label>Situação</label><select name="status"><?php foreach (Statuses::PRODUCTION as $code => $label): ?><option value="<?= e($code) ?>" <?= $item['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Quem produz</label><select name="producer_user_id"><option value="">Não definido</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= (int) $item['producer_user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Previsão de entrega</label><input type="date" name="due_date" value="<?= e(substr($due, 0, 10)) ?>"></div>
    <div class="field"><label>Link das fotos</label><input name="private_link" value="<?= e((string) $item['private_link']) ?>" placeholder="https://"></div>
    <div class="field full"><label>Observações</label><textarea name="notes" rows="3" placeholder="Anotação interna"><?= e((string) $item['notes']) ?></textarea></div>
    <div class="full"><button type="submit">Salvar</button></div>
</form>
<?php endif; ?>
