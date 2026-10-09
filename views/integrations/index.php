<section class="status-cards">
    <article class="card status-card">
        <h2>E-mail<span class="status-dot<?= $notifications['email'] ? ' is-on' : '' ?>" aria-hidden="true"></span></h2>
        <p><?= $notifications['email'] ? 'SMTP configurado.' : 'Envio não configurado. As mensagens ficam na fila com a situação “não configurado”.' ?></p>
    </article>
    <article class="card status-card">
        <h2>WhatsApp<span class="status-dot<?= $notifications['whatsapp'] ? ' is-on' : '' ?>" aria-hidden="true"></span></h2>
        <p><?= $notifications['whatsapp'] ? 'Conexão configurada no servidor.' : 'Conexão não configurada no servidor. Nenhuma mensagem é enviada.' ?></p>
    </article>
    <article class="card status-card">
        <h2>Conector<span class="status-dot is-on" aria-hidden="true"></span></h2>
        <p>Somente o conector genérico por webhook está pronto. Plataformas sem documentação permanecem pendentes.</p>
    </article>
</section>
<article class="card stack">
    <h2>Webhooks do site</h2>
    <p class="field-help">Cada webhook gera um link para colar no formulário do WordPress. Com o modo teste ligado, o envio aparece na configuração e ainda não cria reserva. Fora do teste, a reserva entra no painel, inclusive quando já foi paga.</p>
    <div class="bleed table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>Link</th><th>Domínios</th><th>Situação</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($webhooks as $hook): ?>
                <tr>
                    <td><?= e($hook['name']) ?></td>
                    <td><span class="platform-path"><?= e($hook['url']) ?></span></td>
                    <td><?= trim((string) $hook['allowed_domains']) !== '' ? e(str_replace("\n", ', ', (string) $hook['allowed_domains'])) : 'Qualquer domínio' ?></td>
                    <td>
                        <span class="badge <?= (int) $hook['active'] ? 'st-configurado' : 'st-pendente' ?>"><?= (int) $hook['active'] ? 'Ativo' : 'Inativo' ?></span>
                        <?php if ((int) $hook['test_mode']): ?><span class="badge st-novo">Teste</span><?php endif; ?>
                    </td>
                    <td class="row-action">
                        <a class="ghost" href="/integracoes/webhooks/<?= (int) $hook['id'] ?>">Configurar</a>
                        <form method="post" action="/integracoes/webhooks/<?= (int) $hook['id'] ?>/remover"><?= csrf_field() ?><button class="btn-danger" type="submit" data-confirm="Remover este webhook?">Remover</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($webhooks === []): ?><tr><td colspan="5">Nenhum webhook criado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
<form method="post" action="/integracoes/webhooks" class="card form-grid">
    <?= csrf_field() ?>
    <div class="full"><h2>Novo webhook</h2></div>
    <div class="field"><label>Nome</label><input name="name" required placeholder="Site Drive Xperience"></div>
    <div class="field"><label>Domínios permitidos</label><textarea name="allowed_domains" placeholder="odrivexperience.com.br"></textarea><p class="field-help">Um domínio por linha. Em branco, aceita qualquer origem.</p></div>
    <div class="full"><button type="submit">Criar webhook</button></div>
</form>
<article class="card stack">
    <h2>Plataformas</h2>
    <div class="bleed table-wrap">
        <table class="platforms-table">
            <thead><tr><th>Nome</th><th>Conector</th><th>Situação</th><th>Última sincronização</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($platforms as $platform): ?>
                <tr>
                    <td>
                        <span class="platform-name"><?= e($platform['name']) ?></span>
                        <span class="platform-path">/api/webhooks/<?= e($platform['slug']) ?></span>
                    </td>
                    <td><?= e($platform['connector'] === 'generico' ? 'Webhook genérico' : ($platform['connector'] === 'pendente' ? 'Pendente' : (string) $platform['connector'])) ?></td>
                    <td><span class="badge st-<?= e((string) $platform['status']) ?>"><?= e((string) $platform['status']) ?></span></td>
                    <td class="when"><?= e(date_br($platform['last_sync_at'])) ?></td>
                    <td><button class="ghost platform-edit" type="button" data-edit-platform="<?= (int) $platform['id'] ?>">Editar</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>
<div data-drawers hidden>
    <button class="drawer-backdrop" type="button" data-drawer-close aria-label="Fechar"></button>
    <aside class="drawer" role="dialog" aria-modal="true">
        <?php foreach ($platforms as $platform): ?>
            <form method="post" action="/integracoes/plataformas/<?= (int) $platform['id'] ?>" data-drawer="<?= (int) $platform['id'] ?>" hidden>
                <?= csrf_field() ?>
                <h2 id="drawer-title-<?= (int) $platform['id'] ?>"><?= e($platform['name']) ?></h2>
                <input type="hidden" name="active" value="0">
                <div class="field"><label>Nome</label><input name="name" value="<?= e($platform['name']) ?>"></div>
                <div class="field"><label>Identificador</label><input name="slug" value="<?= e($platform['slug']) ?>"></div>
                <div class="field"><label>Conector</label><select name="connector"><option value="pendente" <?= $platform['connector'] === 'pendente' ? 'selected' : '' ?>>Pendente</option><option value="generico" <?= $platform['connector'] === 'generico' ? 'selected' : '' ?>>Webhook genérico</option></select></div>
                <div class="field"><label>Novo segredo</label><input type="password" name="webhook_secret"><p class="field-help">Novo segredo, se for trocar</p></div>
                <label class="inline-check"><input type="checkbox" name="trust_payment" value="1" <?= (int) $platform['trust_payment'] ? 'checked' : '' ?>> Confiar no pagamento informado</label>
                <label class="inline-check"><input type="checkbox" name="active" value="1" <?= (int) $platform['active'] ? 'checked' : '' ?>> Ativa</label>
                <div class="field"><label>Observações</label><textarea name="notes" rows="4"><?= e((string) $platform['notes']) ?></textarea></div>
                <div class="drawer-actions">
                    <button type="submit">Salvar</button>
                    <button class="ghost" type="button" data-drawer-close>Cancelar</button>
                </div>
            </form>
        <?php endforeach; ?>
    </aside>
</div>
<form method="post" action="/integracoes/plataformas" class="card stack platform-form">
    <?= csrf_field() ?>
    <h2>Nova plataforma</h2>
    <div class="form-grid">
        <div class="field"><label>Nome</label><input name="name" required></div>
        <div class="field"><label>Identificador</label><input name="slug" placeholder="exemplo-plataforma"></div>
        <div class="field"><label>Conector</label><select name="connector"><option value="pendente">Pendente de configuração</option><option value="generico">Webhook genérico</option></select></div>
        <div class="field"><label>Segredo do webhook</label><input name="webhook_secret"></div>
        <div class="field full"><label>Observações</label><textarea name="notes"></textarea></div>
    </div>
    <div class="photo-sale-foot"><button type="submit">Registrar</button></div>
</form>
<form method="post" action="/integracoes/importar" enctype="multipart/form-data" class="card stack import-form">
    <?= csrf_field() ?>
    <h2>Importar CSV ou XLSX</h2>
    <div class="import-grid">
        <div class="field"><label>Plataforma</label><select name="platform_id"><option value="">Selecionar</option><?php foreach ($platforms as $platform): ?><option value="<?= (int) $platform['id'] ?>"><?= e($platform['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Arquivo</label>
            <div class="file-field">
                <input type="file" name="file" accept=".csv,.xlsx,text/csv" required>
                <label class="file-pick">Escolher arquivo</label>
                <span data-file-name>Nenhum arquivo escolhido</span>
            </div>
        </div>
    </div>
    <div class="photo-sale-foot"><button type="submit">Enviar para mapeamento</button></div>
</form>
<section class="sync-panels stack">
    <article class="card">
        <h2>Importações</h2>
        <div class="bleed table-wrap">
            <table>
                <thead><tr><th>Arquivo</th><th>Situação</th><th>Importadas</th><th>Ignoradas</th><th>Erros</th></tr></thead>
                <tbody>
                <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td><a href="/integracoes/importar/<?= (int) $batch['id'] ?>"><?= e($batch['filename']) ?></a></td>
                        <td><?= e($batch['status']) ?></td>
                        <td class="num"><?= (int) $batch['imported_count'] ?></td>
                        <td class="num"><?= (int) $batch['skipped_count'] ?></td>
                        <td class="num"><?= (int) $batch['error_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($batches === []): ?><tr><td colspan="5">Nenhuma importação registrada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="card">
        <h2>Sincronizações</h2>
        <div class="bleed table-wrap">
            <table>
                <thead><tr><th>Data</th><th>Plataforma</th><th>Situação</th><th>Mensagem</th><th>Ação</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="when"><?= e(date_br($log['created_at'])) ?></td>
                        <td><?= e((string) $log['platform_name']) ?></td>
                        <td><?= e($log['status']) ?></td>
                        <td><?= e((string) $log['message']) ?></td>
                        <td><?php if ($log['status'] === 'erro'): ?><form method="post" action="/integracoes/sincronizacoes/<?= (int) $log['id'] ?>/reprocessar"><?= csrf_field() ?><button class="ghost" type="submit">Reprocessar</button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?><tr><td colspan="5">Nenhuma sincronização registrada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
