<?php
$sampleFields = $samples[0]['fields'] ?? [];
?>
<p class="toolbar"><a class="ghost button-link" href="/integracoes">Voltar</a></p>
<article class="card stack hook-head">
    <div class="hook-url">
        <span>Link do webhook</span>
        <code id="hook-url"><?= e($url) ?></code>
        <button class="ghost" type="button" data-copy="#hook-url">Copiar</button>
    </div>
    <div class="hook-switches">
        <form method="post" action="/integracoes/webhooks/<?= (int) $hook['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="active" value="<?= (int) $hook['active'] ? '0' : '1' ?>">
            <button class="<?= (int) $hook['active'] ? '' : 'ghost' ?>" type="submit"><?= (int) $hook['active'] ? 'Ativo' : 'Inativo' ?></button>
        </form>
        <form method="post" action="/integracoes/webhooks/<?= (int) $hook['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="test_mode" value="<?= (int) $hook['test_mode'] ? '0' : '1' ?>">
            <button class="<?= (int) $hook['test_mode'] ? '' : 'ghost' ?>" type="submit"><?= (int) $hook['test_mode'] ? 'Modo teste ligado' : 'Modo teste desligado' ?></button>
        </form>
    </div>
    <p class="field-help"><?= (int) $hook['test_mode'] ? 'O modo teste guarda o POST e não cria reserva. O quadro do teste diz se o pedido está pronto. O botão cria a reserva desse teste.' : 'Fora do teste, cada POST cria a reserva no painel. O valor pago entra na reserva.' ?></p>
</article>
<div class="hook-layout" data-hook data-tests="/integracoes/webhooks/<?= (int) $hook['id'] ?>/testes" data-testing="<?= (int) $hook['test_mode'] ?>">
    <aside class="card">
        <h2>Testes recebidos</h2>
        <ul class="hook-tests" data-hook-tests>
            <?php foreach ($samples as $index => $sample): ?>
                <li>
                    <button type="button" data-sample="<?= (int) $sample['id'] ?>"<?= $index === 0 ? ' class="is-selected"' : '' ?>>
                        <strong><?= e($sample['at']) ?></strong>
                        <span><?= $sample['host'] !== '' ? e($sample['host']) : 'Origem não informada' ?> · <?= count($sample['fields']) ?> campos</span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($samples === []): ?><p class="muted" data-hook-empty>Nenhum teste recebido.</p><?php else: ?><p class="muted" data-hook-empty hidden>Nenhum teste recebido.</p><?php endif; ?>
    </aside>
    <form method="post" action="/integracoes/webhooks/<?= (int) $hook['id'] ?>" class="card stack">
        <?= csrf_field() ?>
        <input type="hidden" name="sample_id" value="<?= (int) ($samples[0]['id'] ?? 0) ?>" data-sample-id>
        <?php $preview = $samples[0]['preview'] ?? null; ?>
        <?php if (is_array($preview)): ?><p class="flash <?= ($preview['status'] ?? '') === 'error' ? 'erro' : 'aviso' ?>" data-hook-preview><?= e((string) $preview['text']) ?></p><?php else: ?><p class="flash aviso" data-hook-preview hidden></p><?php endif; ?>
        <h2>Mapeamento</h2>
        <p class="field-help">Escolha um teste à esquerda. Em cada variável, selecione o campo recebido que corresponde a ela.</p>
        <?php foreach ($fields as $group => $items): ?>
            <h3><?= e($group) ?></h3>
            <?php foreach ($items as $key => $label): ?>
                <?php $current = (string) ($mapping[$key] ?? ''); ?>
                <div class="field hook-map">
                    <label for="map-<?= e($key) ?>"><?= e($label) ?></label>
                    <select id="map-<?= e($key) ?>" name="map[<?= e($key) ?>]" data-current="<?= e($current) ?>">
                        <option value="">Não usar</option>
                        <?php if ($current !== '' && !isset($sampleFields[$current])): ?><option value="<?= e($current) ?>" selected><?= e($current) ?></option><?php endif; ?>
                        <?php foreach ($sampleFields as $path => $value): ?>
                            <option value="<?= e($path) ?>"<?= $current === $path ? ' selected' : '' ?>><?= e($path) ?><?= $value !== '' ? ' · ' . e(mb_substr($value, 0, 48)) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="field">
            <label for="allowed-domains">Domínios permitidos</label>
            <textarea id="allowed-domains" name="allowed_domains" placeholder="odrivexperience.com.br"><?= e((string) $hook['allowed_domains']) ?></textarea>
            <p class="field-help">Um domínio por linha. Em branco, aceita qualquer origem. Se o pedido informar uma origem fora da lista, ele é recusado. Pedido sem origem entra, porque o link já identifica o webhook.</p>
        </div>
        <div class="hook-actions">
            <button type="submit" name="action" value="save">Salvar mapeamento</button>
            <button class="ghost" type="submit" name="action" value="import"<?= $samples === [] ? ' disabled' : '' ?>>Criar reserva deste teste</button>
        </div>
    </form>
</div>
