<?php $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']; ?>
<div class="settings" data-settings>
    <div class="settings-tabs" role="tablist">
        <button type="button" data-tab="empresa">Empresa</button>
        <button type="button" data-tab="operacao">Operação</button>
        <button type="button" data-tab="politicas">Políticas</button>
        <button type="button" data-tab="horarios">Horários</button>
        <button type="button" data-tab="modelos">Modelos de documento</button>
    </div>
    <form method="post" action="/configuracoes" data-settings-form>
        <?= csrf_field() ?>
        <div data-panel="empresa">
            <p class="note-info">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>E-mail <?= $email ? 'configurado' : 'não configurado' ?>. WhatsApp <?= $whatsapp ? 'configurado' : 'não configurado' ?>. Sem credenciais, nenhuma mensagem é enviada.</span>
            </p>
            <div class="settings-grid cols-3">
                <div class="field"><label>Nome</label><input name="company_name" value="<?= e((string) ($settings['company_name'] ?? '')) ?>"></div>
                <div class="field"><label>Documento</label><input name="company_document" value="<?= e((string) ($settings['company_document'] ?? '')) ?>"></div>
                <div class="field"><label>Telefone</label><input name="company_phone" value="<?= e((string) ($settings['company_phone'] ?? '')) ?>"></div>
                <div class="field"><label>E-mail</label><input name="company_email" value="<?= e((string) ($settings['company_email'] ?? '')) ?>"></div>
                <div class="field"><label>Site</label><input name="company_website" value="<?= e((string) ($settings['company_website'] ?? '')) ?>"></div>
                <div class="field span-2"><label>Endereço</label><input name="company_address" value="<?= e((string) ($settings['company_address'] ?? '')) ?>"></div>
                <div class="field"><label>Cidade</label><input name="company_city" value="<?= e((string) ($settings['company_city'] ?? '')) ?>"></div>
                <div class="field"><label>Estado</label><input name="company_state" value="<?= e((string) ($settings['company_state'] ?? '')) ?>"></div>
                <div class="field"><label>CEP</label><input name="company_zip" value="<?= e((string) ($settings['company_zip'] ?? '')) ?>"></div>
            </div>
        </div>
        <div data-panel="operacao" hidden>
            <div class="settings-grid cols-4">
                <div class="field"><label>Intervalo entre experiências</label><div class="affix"><input name="buffer_minutes" value="<?= e((string) ($settings['buffer_minutes'] ?? '')) ?>"><span>min</span></div></div>
                <div class="field"><label>Antecedência do alerta</label><div class="affix"><input name="alert_hours" value="<?= e((string) ($settings['alert_hours'] ?? '')) ?>"><span>horas</span></div></div>
                <div class="field"><label>Duração da sessão</label><div class="affix"><input name="session_hours" value="<?= e((string) ($settings['session_hours'] ?? '')) ?>"><span>horas</span></div></div>
                <div class="field"><label>Retenção de documentos</label><div class="affix"><input name="document_retention_months" value="<?= e((string) ($settings['document_retention_months'] ?? '')) ?>"><span>meses</span></div></div>
            </div>
        </div>
        <div data-panel="politicas" hidden>
            <div class="field"><label>Política de cancelamento</label><textarea name="cancellation_policy" rows="4"><?= e((string) ($settings['cancellation_policy'] ?? '')) ?></textarea></div>
            <div class="field"><label>Política de reagendamento</label><textarea name="reschedule_policy" rows="4"><?= e((string) ($settings['reschedule_policy'] ?? '')) ?></textarea></div>
            <div class="field"><label>Condições do orçamento</label><textarea name="quote_conditions" rows="4"><?= e((string) ($settings['quote_conditions'] ?? '')) ?></textarea></div>
        </div>
        <div data-panel="horarios" hidden>
            <div class="table-wrap settings-hours">
                <table>
                    <thead><tr><th>Dia</th><th>Aberto</th><th>Abertura</th><th>Fechamento</th></tr></thead>
                    <tbody>
                    <?php foreach ($hours as $hour): ?>
                        <tr data-hours-row>
                            <td><?= e($days[(int) $hour['weekday']] ?? 'Dia') ?></td>
                            <td><input type="checkbox" name="hours[<?= (int) $hour['weekday'] ?>][active]" value="1" data-hours-toggle <?= (int) $hour['active'] ? 'checked' : '' ?>></td>
                            <td><input type="time" name="hours[<?= (int) $hour['weekday'] ?>][opens]" value="<?= e(substr((string) $hour['opens_at'], 0, 5)) ?>"></td>
                            <td><input type="time" name="hours[<?= (int) $hour['weekday'] ?>][closes]" value="<?= e(substr((string) $hour['closes_at'], 0, 5)) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="settings-bar"><button type="submit">Salvar configurações</button></div>
    </form>
    <div data-panel="modelos" hidden>
        <form method="get" class="settings-model-open">
            <select name="modelo">
                <?php foreach ($templates as $item): ?><option value="<?= e($item['type']) ?>" <?= ($template['type'] ?? '') === $item['type'] ? 'selected' : '' ?>><?= e($item['name']) ?> · v<?= (int) $item['version'] ?></option><?php endforeach; ?>
            </select>
            <button class="ghost" type="submit">Abrir modelo</button>
        </form>
        <?php if ($template): ?>
        <form method="post" action="/configuracoes/modelos" class="settings-code">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($template['type']) ?>">
            <div class="field"><label><?= e($template['name']) ?></label><textarea name="body" rows="16"><?= e($template['body']) ?></textarea></div>
            <p class="muted">Documentos já emitidos permanecem na versão em que foram gerados. O termo precisa de validação jurídica antes do uso definitivo.</p>
            <button type="submit">Atualizar modelo</button>
        </form>
        <?php endif; ?>
    </div>
</div>
