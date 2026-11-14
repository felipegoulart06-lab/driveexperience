<?php
$days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$edit = (string) ($_GET['editar'] ?? '');
$allowedEdit = ['empresa', 'operacao', 'politicas', 'horarios', 'modelos'];
if ($edit !== '' && !in_array($edit, $allowedEdit, true)) {
    $edit = '';
}
if ($edit === '' && isset($_GET['modelo'])) {
    $edit = 'modelos';
}
$editing = $edit !== '';
$logoId = (int) ($settings['company_logo_file_id'] ?? 0);
$signId = (int) ($settings['company_signature_file_id'] ?? 0);
$tab = (string) ($_GET['aba'] ?? '');
$activeTab = $editing ? $edit : (in_array($tab, $allowedEdit, true) ? $tab : 'empresa');
?>
<div class="settings" data-settings data-active-tab="<?= e($activeTab) ?>"<?= $editing ? ' data-editing="1"' : '' ?>>
    <div class="settings-tabs" role="tablist">
        <button type="button" data-tab="empresa">Empresa</button>
        <button type="button" data-tab="operacao">Operação</button>
        <button type="button" data-tab="politicas">Políticas</button>
        <button type="button" data-tab="horarios">Horário de funcionamento</button>
        <button type="button" data-tab="modelos">Modelos de documento</button>
    </div>

    <?php if (!$editing): ?>
        <div data-panel="empresa">
            <div class="toolbar">
                <h2>Dados da empresa</h2>
                <a class="button-link" href="/configuracoes?editar=empresa">Editar</a>
            </div>
            <p class="note-info">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>E-mail <?= $email ? 'configurado' : 'não configurado' ?>. WhatsApp <?= $whatsapp ? 'configurado' : 'não configurado' ?>. Logo e assinatura entram no cabeçalho e no rodapé dos PDFs da reserva.</span>
            </p>
            <div class="settings-preview">
                <div class="settings-brand-preview">
                    <?php if ($logoId > 0): ?>
                        <img src="/configuracoes/logo" alt="Logo da empresa">
                    <?php else: ?>
                        <p class="muted">Nenhuma logo cadastrada.</p>
                    <?php endif; ?>
                    <?php if ($signId > 0): ?>
                        <img class="settings-sign-preview" src="/configuracoes/assinatura" alt="Assinatura digital">
                    <?php else: ?>
                        <p class="muted">Nenhuma assinatura digital cadastrada.</p>
                    <?php endif; ?>
                </div>
                <article class="wa-card" data-wa data-wa-status="<?= e((string) ($wa['status'] ?? 'desconectado')) ?>" data-wa-2fa="<?= !empty($wa['two_factor']) ? '1' : '0' ?>">
                    <?= csrf_field() ?>
                    <div class="toolbar">
                        <h2>WhatsApp</h2>
                        <span class="wa-state" data-wa-label><?= e((string) ($wa['label'] ?? 'Desconectado')) ?></span>
                    </div>
                    <p class="field-help" data-wa-help><?= !empty($wa['two_factor']) ? 'Informe o código do 2FA para gerar o QR Code. É o mesmo código que entra na plataforma. O aparelho aparece como DRIVEXPERIENCE TESTE.' : 'Ative o 2FA. O mesmo código passa a ser obrigatório para entrar na plataforma.' ?></p>
                    <div class="wa-setup" data-wa-setup<?= !empty($wa['two_factor']) ? ' hidden' : '' ?>>
                        <button type="button" data-wa-2fa-start>Ativar 2FA</button>
                        <img class="wa-qr" data-wa-2fa-qr alt="QR Code do 2FA" hidden>
                        <p class="wa-secret" data-wa-2fa-secret hidden></p>
                        <label class="wa-otp" data-wa-2fa-confirm-field hidden>Código do aplicativo<input data-wa-2fa-code inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>
                        <button type="button" data-wa-2fa-confirm hidden>Confirmar 2FA</button>
                    </div>
                    <p class="wa-profile" data-wa-profile<?= empty($wa['profile']) ? ' hidden' : '' ?>><?= e((string) ($wa['profile'] ?? '')) ?></p>
                    <img class="wa-qr" data-wa-qr alt="QR Code do WhatsApp"<?php if (empty($wa['two_factor']) || !empty($wa['connected']) || empty($wa['qr'])): ?> hidden<?php else: ?> src="<?= e((string) $wa['qr']) ?>"<?php endif; ?>>
                    <label class="wa-otp" data-wa-code-field<?= empty($wa['two_factor']) || !empty($wa['connected']) ? ' hidden' : '' ?>>Código 2FA<input data-wa-code inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>
                    <div class="wa-actions">
                        <button type="button" data-wa-connect<?= empty($wa['two_factor']) || !empty($wa['connected']) ? ' hidden' : '' ?>>Gerar QR Code</button>
                        <button type="button" class="wa-disconnect" data-wa-disconnect<?= (($wa['status'] ?? '') === 'desconectado') ? ' hidden' : '' ?>>Excluir instância</button>
                    </div>
                    <form class="wa-receipt" method="post" action="/configuracoes" data-wa-receipt<?= ($wa['status'] ?? '') === 'conectado' ? '' : ' hidden' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="whatsapp">
                        <label class="check"><input type="checkbox" name="wa_send_receipt" value="1"<?= (string) ($settings['wa_send_receipt'] ?? '1') !== '0' ? ' checked' : '' ?>> Enviar comprovante de agendamento</label>
                        <p>Quando a reserva fica confirmada, o cliente recebe um card com os dados e o PDF do comprovante de agendamento. Os três cards de avaliação, aplicativo e indicação saem só no encerramento.</p>
                        <button class="button" type="submit">Salvar disparo</button>
                    </form>
                    <p class="error" data-wa-error hidden></p>
                </article>
                <dl class="settings-facts">
                    <div><dt>Nome</dt><dd><?= e((string) ($settings['company_name'] ?? '—')) ?></dd></div>
                    <div><dt>CNPJ / documento</dt><dd><?= e((string) ($settings['company_document'] ?? '—') ?: '—') ?></dd></div>
                    <div><dt>Telefone</dt><dd><?= e((string) ($settings['company_phone'] ?? '—') ?: '—') ?></dd></div>
                    <div><dt>E-mail</dt><dd><?= e((string) ($settings['company_email'] ?? '—') ?: '—') ?></dd></div>
                    <div><dt>Site</dt><dd><?= e((string) ($settings['company_website'] ?? '—') ?: '—') ?></dd></div>
                    <div class="span-2"><dt>Endereço</dt><dd><?= e(trim(implode(' · ', array_filter([(string) ($settings['company_address'] ?? ''), (string) ($settings['company_city'] ?? ''), (string) ($settings['company_state'] ?? ''), (string) ($settings['company_zip'] ?? '')])))) ?: '—' ?></dd></div>
                </dl>
            </div>
        </div>
        <div data-panel="operacao" hidden>
            <div class="toolbar">
                <h2>Operação</h2>
                <a class="button-link" href="/configuracoes?editar=operacao">Editar</a>
            </div>
            <dl class="settings-facts cols-4">
                <div><dt>Intervalo entre experiências</dt><dd><?= e((string) ($settings['buffer_minutes'] ?? '—')) ?> min</dd></div>
                <div><dt>Antecedência do alerta</dt><dd><?= e((string) ($settings['alert_hours'] ?? '—')) ?> horas</dd></div>
                <div><dt>Duração da sessão</dt><dd><?= e((string) ($settings['session_hours'] ?? '—')) ?> horas</dd></div>
                <div><dt>Retenção de documentos</dt><dd><?= e((string) ($settings['document_retention_months'] ?? '—')) ?> meses</dd></div>
            </dl>
        </div>
        <div data-panel="politicas" hidden>
            <div class="toolbar">
                <h2>Políticas</h2>
                <a class="button-link" href="/configuracoes?editar=politicas">Editar</a>
            </div>
            <article class="card stack">
                <h3>Cancelamento</h3>
                <p><?= nl2br(e((string) ($settings['cancellation_policy'] ?? '—'))) ?></p>
                <h3>Reagendamento</h3>
                <p><?= nl2br(e((string) ($settings['reschedule_policy'] ?? '—'))) ?></p>
                <h3>Condições do orçamento</h3>
                <p><?= nl2br(e((string) ($settings['quote_conditions'] ?? '—'))) ?></p>
            </article>
        </div>
        <div data-panel="horarios" hidden>
            <div class="toolbar">
                <h2>Horário de funcionamento</h2>
                <a class="button-link" href="/configuracoes?editar=horarios">Editar</a>
            </div>
            <p class="field-help">A agenda usa estes horários para limitar a disponibilidade de cada dia.</p>
            <div class="table-wrap settings-hours">
                <table>
                    <thead><tr><th>Dia</th><th>Situação</th><th>Abertura</th><th>Fechamento</th></tr></thead>
                    <tbody>
                    <?php foreach ($hours as $hour): ?>
                        <tr>
                            <td><?= e($days[(int) $hour['weekday']] ?? 'Dia') ?></td>
                            <td><?= (int) $hour['active'] ? 'Aberto' : 'Fechado' ?></td>
                            <td class="when"><?= (int) $hour['active'] ? e(substr((string) $hour['opens_at'], 0, 5)) : '—' ?></td>
                            <td class="when"><?= (int) $hour['active'] ? e(substr((string) $hour['closes_at'], 0, 5)) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div data-panel="modelos" hidden>
            <div class="toolbar">
                <h2>Modelos de documento</h2>
                <a class="button-link" href="/configuracoes?editar=modelos">Editar modelos</a>
            </div>
            <div class="card table-wrap">
                <table>
                    <thead><tr><th>Modelo</th><th>Versão</th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?></td>
                            <td>v<?= (int) $item['version'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="field-help">Documentos já emitidos permanecem na versão em que foram gerados.</p>
        </div>
    <?php else: ?>
        <?php if ($edit !== 'modelos'): ?>
        <form method="post" action="/configuracoes" enctype="multipart/form-data" data-settings-form>
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="<?= e($edit) ?>">
            <div data-panel="empresa"<?= $edit !== 'empresa' ? ' hidden' : '' ?>>
                <div class="toolbar">
                    <h2>Editar empresa</h2>
                    <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
                </div>
                <p class="note-info">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <span>Esses dados e as imagens (JPG ou PNG) aparecem no cabeçalho e na assinatura dos PDFs da reserva.</span>
                </p>
                <div class="settings-grid cols-3">
                    <div class="field"><label>Nome</label><input name="company_name" value="<?= e((string) ($settings['company_name'] ?? '')) ?>" required></div>
                    <div class="field"><label>CNPJ / documento</label><input name="company_document" value="<?= e((string) ($settings['company_document'] ?? '')) ?>"></div>
                    <div class="field"><label>Telefone</label><input name="company_phone" value="<?= e((string) ($settings['company_phone'] ?? '')) ?>"></div>
                    <div class="field"><label>E-mail</label><input name="company_email" value="<?= e((string) ($settings['company_email'] ?? '')) ?>"></div>
                    <div class="field"><label>Site</label><input name="company_website" value="<?= e((string) ($settings['company_website'] ?? '')) ?>"></div>
                    <div class="field span-2"><label>Link de avaliação no Google</label><input name="google_review_url" value="<?= e((string) ($settings['google_review_url'] ?? '')) ?>" placeholder="https://"><p class="field-help">Botão do primeiro card. Em branco, a busca usa o nome e a cidade da empresa.</p></div>
                    <div class="field span-2"><label>Link da Play Store</label><input name="play_store_url" value="<?= e((string) ($settings['play_store_url'] ?? '')) ?>" placeholder="https://play.google.com/store/apps/details?id="><p class="field-help">Botão do segundo card. Em branco, abre a página do aplicativo neste sistema.</p></div>
                    <div class="field span-2"><label>Endereço</label><input name="company_address" value="<?= e((string) ($settings['company_address'] ?? '')) ?>"></div>
                    <div class="field"><label>Cidade</label><input name="company_city" value="<?= e((string) ($settings['company_city'] ?? '')) ?>"></div>
                    <div class="field"><label>Estado</label><input name="company_state" value="<?= e((string) ($settings['company_state'] ?? '')) ?>" maxlength="2"></div>
                    <div class="field"><label>CEP</label><input name="company_zip" value="<?= e((string) ($settings['company_zip'] ?? '')) ?>"></div>
                    <div class="field">
                        <label>Logo (JPG ou PNG)</label>
                        <input type="file" name="company_logo" accept="image/jpeg,image/png,.jpg,.jpeg,.png">
                        <?php if ($logoId > 0): ?><p class="field-help">Atual: <a href="/configuracoes/logo" target="_blank" rel="noopener">ver logo</a>. Sem arquivo novo, a atual permanece.</p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Assinatura digital (JPG ou PNG)</label>
                        <input type="file" name="company_signature" accept="image/jpeg,image/png,.jpg,.jpeg,.png">
                        <?php if ($signId > 0): ?><p class="field-help">Atual: <a href="/configuracoes/assinatura" target="_blank" rel="noopener">ver assinatura</a>. Sem arquivo novo, a atual permanece.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div data-panel="operacao"<?= $edit !== 'operacao' ? ' hidden' : '' ?>>
                <div class="toolbar">
                    <h2>Editar operação</h2>
                    <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
                </div>
                <div class="settings-grid cols-4">
                    <div class="field"><label>Intervalo entre experiências</label><div class="affix"><input name="buffer_minutes" value="<?= e((string) ($settings['buffer_minutes'] ?? '')) ?>"><span>min</span></div></div>
                    <div class="field"><label>Antecedência do alerta</label><div class="affix"><input name="alert_hours" value="<?= e((string) ($settings['alert_hours'] ?? '')) ?>"><span>horas</span></div></div>
                    <div class="field"><label>Duração da sessão</label><div class="affix"><input name="session_hours" value="<?= e((string) ($settings['session_hours'] ?? '')) ?>"><span>horas</span></div></div>
                    <div class="field"><label>Retenção de documentos</label><div class="affix"><input name="document_retention_months" value="<?= e((string) ($settings['document_retention_months'] ?? '')) ?>"><span>meses</span></div></div>
                </div>
            </div>
            <div data-panel="politicas"<?= $edit !== 'politicas' ? ' hidden' : '' ?>>
                <div class="toolbar">
                    <h2>Editar políticas</h2>
                    <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
                </div>
                <div class="field"><label>Política de cancelamento</label><textarea name="cancellation_policy" rows="4"><?= e((string) ($settings['cancellation_policy'] ?? '')) ?></textarea></div>
                <div class="field"><label>Política de reagendamento</label><textarea name="reschedule_policy" rows="4"><?= e((string) ($settings['reschedule_policy'] ?? '')) ?></textarea></div>
                <div class="field"><label>Condições do orçamento</label><textarea name="quote_conditions" rows="4"><?= e((string) ($settings['quote_conditions'] ?? '')) ?></textarea></div>
            </div>
            <div data-panel="horarios"<?= $edit !== 'horarios' ? ' hidden' : '' ?>>
                <div class="toolbar">
                    <h2>Editar horário de funcionamento</h2>
                    <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
                </div>
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
            <div class="settings-bar">
                <button type="submit">Salvar</button>
                <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
            </div>
        </form>
        <?php else: ?>
        <div data-panel="modelos">
            <div class="toolbar">
                <h2>Editar modelos</h2>
                <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
            </div>
            <form method="get" class="settings-model-open" action="/configuracoes">
                <input type="hidden" name="editar" value="modelos">
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
                <div class="client-actions">
                    <button type="submit">Atualizar modelo</button>
                    <a class="ghost sheet-action" href="/configuracoes">Cancelar</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
