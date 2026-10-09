<div class="toolbar filter-bar">
    <form method="get" action="/clientes">
        <label class="search-field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome, telefone, CPF ou e-mail">
        </label>
        <label class="inline-check"><input type="checkbox" name="archived" value="1" <?= isset($_GET['archived']) ? 'checked' : '' ?>> Incluir arquivados</label>
        <button class="ghost" type="submit">Buscar</button>
    </form>
    <?php if (\App\Auth\Auth::can('clients.manage')): ?><a class="button-link" href="/clientes/novo">Novo cliente</a><?php endif; ?>
</div>
<div class="card table-wrap">
    <table class="clients-table">
        <thead><tr><th>Nome</th><th>Contato</th><th>Origem</th><th>Cadastro</th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $client): ?>
            <tr data-href="/clientes/<?= (int) $client['id'] ?>">
                <td><a href="/clientes/<?= (int) $client['id'] ?>"><?= e($client['full_name']) ?></a><?php if ($client['archived_at']): ?> <span class="badge">Arquivado</span><?php endif; ?></td>
                <td class="contact"><?php if ((string) $client['phone'] !== ''): ?><span class="contact-phone"><?= e(phone_display((string) $client['phone'])) ?></span><?php endif; ?><?php if ((string) $client['email'] !== ''): ?><span class="contact-email"><?= e((string) $client['email']) ?></span><?php endif; ?></td>
                <td><?= e((string) $client['origin_name']) ?></td>
                <td class="when"><?= e(date_br($client['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?><tr><td colspan="4">Nenhum cliente encontrado.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4"><?= (int) $result['total'] ?> cliente(s)</td></tr></tfoot>
    </table>
</div>
<?php if ($result['pages'] > 1): ?>
    <div class="pager">
        <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
            <a class="button-link ghost" href="/clientes?q=<?= urlencode($q) ?>&page=<?= $page ?>"><?= $page ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
