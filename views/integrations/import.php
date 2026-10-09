<article class="card">
    <p><?= e($batch['filename']) ?> · <?= e($batch['status']) ?></p>
    <form method="post" action="/integracoes/importar/<?= (int) $batch['id'] ?>/previa">
        <?= csrf_field() ?>
        <div class="form-grid">
            <?php foreach ($fields as $key => $label): ?>
                <div class="field"><label><?= e($label) ?></label>
                    <select name="map[<?= e($key) ?>]">
                        <option value="">Não importar</option>
                        <?php foreach ($batch['headers'] as $header): ?><option value="<?= e($header) ?>" <?= ($batch['mapping'][$key] ?? '') === $header ? 'selected' : '' ?>><?= e($header) ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit">Gerar prévia</button>
    </form>
</article>
<?php if ($rows !== []): ?>
<article class="card stack table-wrap">
    <table>
        <thead><tr><th>Linha</th><th>Chave</th><th>Situação</th><th>Mensagem</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?><tr><td><?= (int) $row['row_number'] ?></td><td><?= e((string) $row['external_key']) ?></td><td><?= e($row['status']) ?></td><td><?= e((string) $row['message']) ?></td></tr><?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($batch['status'] === 'previa'): ?>
        <form method="post" action="/integracoes/importar/<?= (int) $batch['id'] ?>/confirmar" class="stack"><?= csrf_field() ?><button type="submit">Confirmar importação</button></form>
    <?php endif; ?>
</article>
<?php endif; ?>
