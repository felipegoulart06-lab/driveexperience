<div class="card table-wrap">
    <table class="docs-table">
        <thead><tr><th>Documento</th><th>Reserva</th><th>Versão</th><th>Emissão</th><th>Ação</th></tr></thead>
        <tbody>
        <?php foreach ($documents as $document): ?>
            <?php $href = $document['redacted_at'] ? '' : '/documentos/' . $document['public_id']; ?>
            <tr<?= $href !== '' ? ' data-href="' . e($href) . '"' : '' ?>>
                <td>
                    <span class="doc-name">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        <?php if ($href === ''): ?><?= e($document['title']) ?> <span class="badge">Removido</span><?php else: ?><a href="<?= e($href) ?>"><?= e($document['title']) ?></a><?php endif; ?>
                    </span>
                </td>
                <td><?php if ($document['reservation_id'] && $document['code']): ?><a class="code" href="/reservas/<?= (int) $document['reservation_id'] ?>"><?= e((string) $document['code']) ?></a><?php endif; ?></td>
                <td><span class="badge">v<?= (int) $document['version'] ?></span></td>
                <td class="when"><?= e(date_br($document['issued_at'])) ?></td>
                <td><?php if ($href !== ''): ?><a class="ghost doc-open" href="<?= e($href) ?>">Abrir<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg></a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($documents === []): ?><tr><td colspan="5">Nenhum documento emitido.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="5"><?= count($documents) ?> documento(s)</td></tr></tfoot>
    </table>
</div>
<p class="doc-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span>Os arquivos são abertos somente após a autenticação. O endereço usa um identificador aleatório e não revela a reserva.</span>
</p>
