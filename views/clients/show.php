<?php use App\Domain\Statuses; ?>
<?php
$canEdit = \App\Auth\Auth::can('clients.manage') && !$client['anonymized_at'];
$editing = $canEdit && (($errors ?? []) !== [] || (string) ($_GET['editar'] ?? '') === '1');
$fact = static function (string $label, string $value): void {
    echo '<div><dt>' . e($label) . '</dt><dd>' . ($value !== '' ? e($value) : '—') . '</dd></div>';
};
?>
<article class="card">
    <h2>Cadastro</h2>
    <dl class="client-facts">
        <?php $fact('CPF', mask_cpf($client['cpf'], $fullDocument)); ?>
        <?php $fact('Telefone', phone_display((string) $client['phone'])); ?>
        <?php $fact('WhatsApp', phone_display((string) $client['whatsapp'])); ?>
        <?php $fact('E-mail', (string) $client['email']); ?>
        <?php $fact('Cidade', trim((string) $client['city'] . (((string) $client['state'] !== '') ? ' / ' . $client['state'] : ''))); ?>
        <?php $fact('Nascimento', $client['birth_date'] ? date_br($client['birth_date']) : ''); ?>
        <?php $fact('Origem', trim((string) $client['origin_name'] . ($client['platform_name'] ? ' · ' . $client['platform_name'] : ''))); ?>
        <?php $fact('Cadastro', date_br($client['created_at'])); ?>
        <?php if (!empty($client['instagram'])): $fact('Instagram', '@' . $client['instagram']); endif; ?>
    </dl>
    <?php if ((string) $client['notes'] !== ''): ?><p class="client-notes"><?= e((string) $client['notes']) ?></p><?php endif; ?>
    <div class="client-actions">
        <?php if ($canEdit && !$editing): ?><a class="ghost sheet-action" href="/clientes/<?= (int) $client['id'] ?>?editar=1">Editar</a><?php endif; ?>
        <?php if ($editing): ?><a class="ghost sheet-action" href="/clientes/<?= (int) $client['id'] ?>">Fechar edição</a><?php endif; ?>
        <?php if (\App\Auth\Auth::can('clients.sensitive')): ?><a class="ghost sheet-action" href="/clientes/<?= (int) $client['id'] ?>/exportar">Exportar dados</a><?php endif; ?>
    </div>
</article>

<?php if ($editing): ?>
<form method="post" action="/clientes/<?= (int) $client['id'] ?>" class="card stack client-edit">
    <?= csrf_field() ?>
    <h2>Atualizar cadastro</h2>
    <div class="form-grid">
        <div class="field"><label>Nome</label><input name="full_name" value="<?= e(field($old, $client, 'full_name')) ?>"></div>
        <?php if ($fullDocument): ?><div class="field"><label>CPF</label><input name="cpf" data-cpf inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= e(field($old, $client, 'cpf')) ?>"></div><?php endif; ?>
        <input type="hidden" name="origin_id" value="<?= (int) $client['origin_id'] ?>">
        <input type="hidden" name="platform_id" value="<?= (int) $client['platform_id'] ?>">
        <input type="hidden" name="external_id" value="<?= e((string) $client['external_id']) ?>">
        <div class="field"><label>Telefone</label><input name="phone" value="<?= e(field($old, $client, 'phone')) ?>"></div>
        <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e(field($old, $client, 'whatsapp')) ?>"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" placeholder="reserva@email.com" value="<?= e(field($old, $client, 'email')) ?>"></div>
        <div class="field"><label>Nascimento</label><input type="date" name="birth_date" value="<?= e(field($old, $client, 'birth_date')) ?>"></div>
        <div class="field"><label>Cidade</label><input name="city" value="<?= e(field($old, $client, 'city')) ?>"></div>
        <div class="field"><label>Estado</label><input name="state" maxlength="2" value="<?= e(field($old, $client, 'state')) ?>"></div>
        <div class="field span-2"><label>Observações</label><textarea name="notes" rows="3"><?= e(field($old, $client, 'notes')) ?></textarea></div>
    </div>
    <div class="client-actions"><button type="submit">Salvar</button></div>
</form>
<?php endif; ?>

<div class="client-panels">
    <article class="card">
        <h2>Reservas</h2>
        <div class="bleed">
            <table class="client-history">
                <thead><tr><th>Código</th><th>Quando</th><th>Veículo</th><th>Situação</th></tr></thead>
                <tbody>
                <?php foreach ($history['reservations'] as $reservation): ?>
                    <tr>
                        <td><a class="code" href="/reservas/<?= (int) $reservation['id'] ?>"><?= e($reservation['code']) ?></a></td>
                        <td class="when"><?= e(date_br((string) $reservation['scheduled_date'])) ?></td>
                        <td><?= e(trim(($reservation['brand'] ?? '') . ' ' . ($reservation['model'] ?? ''))) ?></td>
                        <td><span class="badge st-<?= e($reservation['operational_status']) ?>"><?= e(Statuses::label('operational', $reservation['operational_status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($history['reservations'] === []): ?><tr><td colspan="4">Nenhuma reserva.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="card">
        <h2>Pagamentos</h2>
        <div class="bleed">
            <table class="client-history">
                <thead><tr><th>Data</th><th class="num">Valor</th><th>Forma</th><th>Situação</th></tr></thead>
                <tbody>
                <?php foreach ($history['payments'] as $payment): ?>
                    <tr>
                        <td class="when"><?= e(date_br($payment['paid_at'])) ?></td>
                        <td class="num"><?= e(money((int) $payment['amount_cents'])) ?></td>
                        <td><?= e((string) $payment['method_name']) ?></td>
                        <td><?= e((string) $payment['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($history['payments'] === []): ?><tr><td colspan="4">Nenhum pagamento.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>

<div class="client-panels">
    <article class="card">
        <h2>Documentos</h2>
        <ul class="client-log">
            <?php foreach ($history['documents'] as $document): ?>
                <li><a href="/documentos/<?= e($document['public_id']) ?>"><?= e($document['title']) ?></a> <span class="muted">v<?= (int) $document['version'] ?></span></li>
            <?php endforeach; ?>
            <?php if ($history['documents'] === []): ?><li class="muted">Nenhum documento.</li><?php endif; ?>
        </ul>
        <?php if ($history['productions'] !== []): ?>
            <h2 class="section-rule">Produções</h2>
            <ul class="client-log">
                <?php foreach ($history['productions'] as $production): ?>
                    <li><a href="/fotos/<?= (int) $production['id'] ?>">Produção #<?= (int) $production['id'] ?></a> <span class="muted"><?= e(Statuses::label('production', $production['status'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <article class="card">
        <h2>Contatos</h2>
        <ul class="client-log">
            <?php foreach ($history['contacts'] as $contact): ?>
                <li>
                    <span class="when"><?= e(date_br($contact['contacted_at'])) ?></span>
                    <?= e($contact['channel']) ?><?php if ((string) $contact['user_name'] !== ''): ?> · <?= e((string) $contact['user_name']) ?><?php endif; ?>
                    <span class="contact-email"><?= e($contact['notes']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if ($history['contacts'] === []): ?><li class="muted">Nenhum contato registrado.</li><?php endif; ?>
        </ul>
        <?php if ($editing): ?>
        <form method="post" action="/clientes/<?= (int) $client['id'] ?>/contatos" class="client-contact">
            <?= csrf_field() ?>
            <div class="field"><label>Canal</label><select name="channel"><option>WhatsApp</option><option>Telefone</option><option>E-mail</option><option>Presencial</option><option>Interno</option></select></div>
            <div class="field"><label>Registro</label><textarea name="notes" rows="3"></textarea><?php if (!empty($errors['notes'])): ?><small class="error"><?= e($errors['notes']) ?></small><?php endif; ?></div>
            <button type="submit">Registrar contato</button>
        </form>
        <?php endif; ?>
    </article>
</div>

<?php if ($editing && !$client['archived_at']): ?>
<form method="post" action="/clientes/<?= (int) $client['id'] ?>/arquivar" class="stack"><?= csrf_field() ?><button class="ghost" data-confirm="Arquivar este cliente?" type="submit">Arquivar</button></form>
<?php endif; ?>
<?php if ($editing && \App\Auth\Auth::can('clients.anonymize') && !$client['anonymized_at']): ?>
<form method="post" action="/clientes/<?= (int) $client['id'] ?>/anonimizar" class="card stack">
    <?= csrf_field() ?>
    <h2>Excluir dados pessoais</h2>
    <p class="field-help">Remove nome, documento, contatos e o conteúdo dos PDFs deste cliente. Lançamentos financeiros permanecem sem identificação.</p>
    <div class="field"><label>Digite REMOVER</label><input name="confirm"></div>
    <?php if (!empty($errors['confirm'])): ?><small class="error"><?= e($errors['confirm']) ?></small><?php endif; ?>
    <button class="btn-danger" type="submit">Excluir dados pessoais</button>
</form>
<?php endif; ?>
