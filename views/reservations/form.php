<?php use App\Domain\Statuses; ?>
<?php
$mode = field($old, [], 'client_mode', 'new') === 'existing' ? 'existing' : 'new';
$partnerMode = field($old, [], 'partner_mode', 'none');
if (!in_array($partnerMode, ['none', 'existing', 'new'], true)) {
    $partnerMode = 'none';
}
$paymentPlan = field($old, [], 'payment_plan');
$dialCodes = ['55' => 'BR +55', '54' => 'AR +54', '598' => 'UY +598', '595' => 'PY +595', '56' => 'CL +56', '1' => 'US +1', '351' => 'PT +351', '34' => 'ES +34', '33' => 'FR +33', '49' => 'DE +49', '39' => 'IT +39', '44' => 'GB +44'];
$dial = static function (string $name, string $value) use ($dialCodes, $old): void {
    $current = field($old, [], $name, $value);
    if ($current === '') {
        $current = '55';
    }
    echo '<select name="' . e($name) . '">';
    foreach ($dialCodes as $code => $label) {
        echo '<option value="' . e((string) $code) . '"' . ($current === (string) $code ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select>';
};
$catalog = ['prices' => [], 'fixed' => [], 'durations' => [], 'types' => [], 'names' => []];
foreach ($modalities as $modality) {
    $id = (string) $modality['id'];
    $catalog['durations'][$id] = (int) $modality['duration_minutes'];
    $catalog['types'][$id] = (string) $modality['type'];
    $catalog['names'][$id] = (string) $modality['name'];
    if ($modality['type'] !== 'experiencia' && (int) $modality['base_price_cents'] > 0) {
        $catalog['fixed'][$id] = ['cents' => (int) $modality['base_price_cents'], 'label' => money((int) $modality['base_price_cents'])];
    }
}
foreach ($prices as $vehicleId => $map) {
    foreach ($map as $modalityId => $cents) {
        $catalog['prices'][(string) $vehicleId][(string) $modalityId] = ['cents' => (int) $cents, 'label' => money((int) $cents)];
    }
}
$selectedModalities = array_map('strval', (array) ($old['modality_id'] ?? []));
?>
<?php if ($errors !== []): ?>
    <div class="flash erro"><?php foreach ($errors as $key => $message): ?><?php if ($key !== '_duplicates'): ?><div><?= e((string) $message) ?></div><?php endif; ?><?php endforeach; ?></div>
<?php endif; ?>
<?php $duplicates = json_decode((string) ($errors['_duplicates'] ?? '[]'), true) ?: []; ?>
<form method="post" action="/reservas" enctype="multipart/form-data" novalidate data-wizard data-catalog="<?= e(json_encode($catalog, JSON_UNESCAPED_UNICODE)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="cnh_file_id" value="<?= e(field($old, [], 'cnh_file_id')) ?>">
    <input type="hidden" name="residence_file_id" value="<?= e(field($old, [], 'residence_file_id')) ?>">
    <div class="steps">
        <?php foreach (['Cliente', 'Origem', 'Veículo', 'Experiência', 'Agenda', 'Valores', 'Revisão'] as $index => $label): ?>
            <span data-step-dot><?= ($index + 1) ?> <?= e($label) ?></span>
        <?php endforeach; ?>
    </div>

    <section class="card" data-step>
        <h2>Cliente</h2>
        <div class="choice-row">
            <label class="choice"><input type="radio" name="client_mode" value="existing" <?= $mode === 'existing' ? 'checked' : '' ?>> Cliente já cadastrado</label>
            <label class="choice"><input type="radio" name="client_mode" value="new" <?= $mode !== 'existing' ? 'checked' : '' ?>> Cadastrar agora</label>
        </div>
        <div data-client-panel="existing" <?= $mode === 'existing' ? '' : 'hidden' ?>>
            <div class="field">
                <label>Cliente</label>
                <select name="client_id">
                    <option value="">Selecione</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= field($old, [], 'client_id') == $client['id'] ? 'selected' : '' ?>><?= e($client['full_name']) ?><?php if ($client['phone']): ?> · <?= e(phone_display((string) $client['phone'])) ?><?php endif; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($clients === []): ?><p class="field-help">Nenhum cliente ativo. Cadastre em Clientes ou use Cadastrar agora.</p><?php else: ?><p class="field-help">A lista traz quem já foi cadastrado em Clientes.</p><?php endif; ?>
            </div>
        </div>
        <div data-client-panel="new" <?= $mode === 'existing' ? 'hidden' : '' ?>>
            <div class="form-grid">
                <div class="field"><label>Nome</label><input name="client_name" data-person-name value="<?= e(field($old, [], 'client_name')) ?>" autocomplete="name"></div>
                <div class="field"><label>CPF</label><input name="client_cpf" data-cpf inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= e(field($old, [], 'client_cpf')) ?>"></div>
                <div class="field"><label>Telefone</label><div class="dial"><?php $dial('client_phone_code', '55'); ?><input name="client_phone" data-phone inputmode="tel" placeholder="(00) 0 0000 - 0000" value="<?= e(field($old, [], 'client_phone')) ?>"></div></div>
                <div class="field"><label>WhatsApp</label><div class="dial"><?php $dial('client_whatsapp_code', '55'); ?><input name="client_whatsapp" data-phone inputmode="tel" placeholder="(00) 0 0000 - 0000" value="<?= e(field($old, [], 'client_whatsapp')) ?>"></div></div>
                <div class="field"><label>E-mail</label><input type="email" name="client_email" placeholder="reserva@email.com" value="<?= e(field($old, [], 'client_email')) ?>"></div>
                <div class="field"><label>Instagram</label><input name="client_instagram" placeholder="@usuario" value="<?= e(field($old, [], 'client_instagram')) ?>"><p class="field-help">Opcional.</p></div>
                <div class="field"><label>Nascimento</label><input type="date" name="client_birth" value="<?= e(field($old, [], 'client_birth')) ?>"></div>
                <div class="field"><label>Cidade</label><input name="client_city" value="<?= e(field($old, [], 'client_city')) ?>"></div>
                <div class="field"><label>Estado</label><input name="client_state" maxlength="2" value="<?= e(field($old, [], 'client_state')) ?>"></div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>CNH de quem faz a reserva</label>
                    <div class="file-field"><input type="file" name="client_cnh" accept="image/jpeg,image/png,image/webp,application/pdf"><button type="button" class="file-pick">Escolher arquivo</button><span data-file-name><?= field($old, [], 'cnh_file_id') !== '' ? 'CNH já anexada' : 'Nenhum arquivo escolhido' ?></span></div>
                </div>
                <div class="field">
                    <label>Comprovante de residência</label>
                    <div class="file-field"><input type="file" name="client_residence" accept="image/jpeg,image/png,image/webp,application/pdf"><button type="button" class="file-pick">Escolher arquivo</button><span data-file-name><?= field($old, [], 'residence_file_id') !== '' ? 'Comprovante já anexado' : 'Nenhum arquivo escolhido' ?></span></div>
                    <p class="field-help">Opcional.</p>
                </div>
            </div>
        </div>
        <?php if ($duplicates !== []): ?>
            <div class="flash aviso">Existe cadastro compatível. Vincule ou confirme um novo.</div>
            <?php foreach ($duplicates as $duplicate): ?>
                <label class="check"><input type="radio" name="link_client_id" value="<?= (int) $duplicate['id'] ?>"> <?= e($duplicate['full_name']) ?> · <?= e((string) $duplicate['phone']) ?> · <?= e((string) $duplicate['cpf']) ?></label>
            <?php endforeach; ?>
            <label class="check"><input type="radio" name="confirm_duplicate" value="link"> Vincular ao cadastro escolhido</label>
            <label class="check"><input type="radio" name="confirm_duplicate" value="create"> Criar um novo cadastro mesmo assim</label>
        <?php endif; ?>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Origem da venda</h2>
        <div class="form-grid">
            <div class="field"><label>Origem</label>
                <select name="origin_id" data-origin>
                    <option value="">Selecione</option>
                    <?php foreach ($origins as $origin): ?><option value="<?= (int) $origin['id'] ?>" data-platform="<?= (int) $origin['requires_platform'] ?>" <?= field($old, [], 'origin_id') == $origin['id'] ? 'selected' : '' ?>><?= e($origin['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div data-platform-fields <?= field($old, [], 'origin_id') !== '' ? '' : 'hidden' ?>>
            <p class="field-help">Plataforma é o canal de fora que manda a venda para cá, como um site parceiro ou um webhook. Não é a pessoa que recebe comissão. Esse campo só entra quando a origem é Plataforma externa.</p>
            <div class="field"><label>Plataforma</label><select name="platform_id"><option value="">Nenhuma</option><?php foreach ($platforms as $platform): ?><option value="<?= (int) $platform['id'] ?>" <?= field($old, [], 'platform_id') == $platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?> (<?= e($platform['status']) ?>)</option><?php endforeach; ?></select></div>
            <div class="form-grid">
                <div class="field"><label>Código da transação</label><input name="external_transaction_id" value="<?= e(field($old, [], 'external_transaction_id')) ?>"></div>
                <div class="field"><label>Código da reserva externa</label><input name="external_reservation_code" value="<?= e(field($old, [], 'external_reservation_code')) ?>"></div>
                <div class="field"><label>Valor bruto informado</label><input name="platform_gross" value="<?= e(field($old, [], 'platform_gross', '0,00')) ?>"></div>
                <div class="field"><label>Taxa da plataforma</label><input name="platform_fee" value="<?= e(field($old, [], 'platform_fee', '0,00')) ?>"></div>
                <div class="field"><label>Valor líquido</label><input name="platform_net" value="<?= e(field($old, [], 'platform_net', '0,00')) ?>"></div>
            </div>
            <label class="check"><input type="checkbox" name="register_platform_payment" value="1" <?= field($old, [], 'register_platform_payment') === '1' ? 'checked' : '' ?>> Registrar agora o valor líquido como pagamento recebido</label>
            <p class="field-help">O valor informado pela plataforma não é tratado como dinheiro recebido, a menos que esta opção seja marcada.</p>
        </div>
        <h2 class="section-rule">Parceria</h2>
        <p class="field-help">Parceiro é quem indicou a venda e recebe comissão. O cadastro fica disponível nas próximas reservas.</p>
        <div class="choice-row">
            <label class="choice"><input type="radio" name="partner_mode" value="none" <?= $partnerMode === 'none' ? 'checked' : '' ?>> Sem parceria</label>
            <label class="choice"><input type="radio" name="partner_mode" value="existing" <?= $partnerMode === 'existing' ? 'checked' : '' ?>> Parceiro cadastrado</label>
            <label class="choice"><input type="radio" name="partner_mode" value="new" <?= $partnerMode === 'new' ? 'checked' : '' ?>> Cadastrar agora</label>
        </div>
        <div data-partner-panel="existing" <?= $partnerMode === 'existing' ? '' : 'hidden' ?>>
            <div class="field"><label>Parceiro</label>
                <select name="partner_id">
                    <option value="">Selecione</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?= (int) $partner['id'] ?>" data-bps="<?= (int) $partner['commission_bps'] ?>" <?= field($old, [], 'partner_id') == $partner['id'] ? 'selected' : '' ?>><?= e($partner['name']) ?> · <?= e(number_format(((int) $partner['commission_bps']) / 100, 2, ',', '.')) ?>%</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($partners === []): ?><p class="field-help">Nenhum parceiro ainda. Use Cadastrar agora.</p><?php endif; ?>
            </div>
        </div>
        <div data-partner-panel="new" <?= $partnerMode === 'new' ? '' : 'hidden' ?>>
            <div class="form-grid">
                <div class="field"><label>Nome</label><input name="partner_name" data-person-name value="<?= e(field($old, [], 'partner_name')) ?>"></div>
                <div class="field"><label>Comissão (%)</label><input name="partner_commission" inputmode="decimal" placeholder="10" value="<?= e(field($old, [], 'partner_commission')) ?>"></div>
                <div class="field"><label>Telefone</label><div class="dial"><?php $dial('partner_phone_code', '55'); ?><input name="partner_phone" data-phone inputmode="tel" placeholder="(00) 0 0000 - 0000" value="<?= e(field($old, [], 'partner_phone')) ?>"></div></div>
                <div class="field"><label>E-mail</label><input type="email" name="partner_email" placeholder="reserva@email.com" value="<?= e(field($old, [], 'partner_email')) ?>"></div>
                <div class="field"><label>Instagram</label><input name="partner_instagram" placeholder="@usuario" value="<?= e(field($old, [], 'partner_instagram')) ?>"><p class="field-help">Opcional.</p></div>
            </div>
        </div>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Veículo</h2>
        <div class="field"><label>Veículo</label>
            <select name="vehicle_id"><option value="">Sem veículo (somente produto)</option>
                <?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= field($old, [], 'vehicle_id') == $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?> · <?= e(Statuses::label('vehicle', $vehicle['base_status'])) ?> · <?= (int) $vehicle['capacity'] ?> lugares</option><?php endforeach; ?>
            </select>
        </div>
        <p class="field-help">O preço da experiência é o que está na tabela deste veículo. Pacote de fotos usa o preço do produto.</p>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Experiência contratada</h2>
        <div class="checks">
            <?php foreach ($modalities as $modality): ?>
                <label class="booking-option" data-modality="<?= (int) $modality['id'] ?>">
                    <input type="checkbox" name="modality_id[]" value="<?= (int) $modality['id'] ?>" <?= in_array((string) $modality['id'], $selectedModalities, true) ? 'checked' : '' ?>>
                    <span><?= e($modality['name']) ?></span>
                    <span data-modality-price><?= $modality['type'] === 'experiencia' ? '' : e(money((int) $modality['base_price_cents'])) ?></span>
                    <small data-modality-meta><?= (int) $modality['duration_minutes'] > 0 ? (int) $modality['duration_minutes'] . ' min' : '' ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="sale-total">Valor da tabela <strong data-sale-total>R$ 0,00</strong></p>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Agendamento</h2>
        <div class="form-grid">
            <div class="field"><label>Data</label><input type="date" name="scheduled_date" value="<?= e(field($old, [], 'scheduled_date')) ?>"></div>
            <div class="field"><label>Horário</label><input type="time" name="start_time" value="<?= e(field($old, [], 'start_time')) ?>"></div>
            <div class="field"><label>Duração em minutos</label><input type="number" name="duration_minutes" readonly value="<?= e(field($old, [], 'duration_minutes')) ?>"><p class="field-help">Vem do tempo cadastrado em Produtos e experiências. Com mais de uma experiência, vale a maior.</p></div>
            <div class="field"><label>Participantes</label><input type="number" name="participants" min="1" value="<?= e(field($old, [], 'participants', '1')) ?>"></div>
            <div class="field"><label>Responsável</label><select name="assigned_user_id"><option value="">Não definido</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= field($old, [], 'assigned_user_id') == $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Situação inicial</label><select name="operational_status"><?php foreach (['novo', 'aguardando_dados', 'aguardando_confirmacao', 'confirmado', 'em_andamento'] as $code): ?><option value="<?= e($code) ?>" <?= field($old, [], 'operational_status', 'aguardando_confirmacao') === $code ? 'selected' : '' ?>><?= e(Statuses::label('operational', $code)) ?></option><?php endforeach; ?></select><p class="field-help">Cliente na loja e a experiência começa agora: Em andamento. Cliente na loja com horário marcado para depois: Confirmado.</p></div>
        </div>
        <div class="actions"><button class="ghost" type="button" data-availability>Verificar disponibilidade</button><span data-availability-result></span></div>
        <label class="check"><input type="checkbox" name="outside_hours" value="1" <?= field($old, [], 'outside_hours') === '1' ? 'checked' : '' ?>> Permitir horário fora do expediente</label>
        <div class="field"><label>Observações</label><textarea name="notes" rows="3"><?= e(field($old, [], 'notes')) ?></textarea></div>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Valores e pagamento</h2>
        <p class="sale-total">Valor da reserva <strong data-sale-total>R$ 0,00</strong></p>
        <div class="form-grid">
            <div class="field"><label>Desconto</label>
                <?php $discountKind = field($old, [], 'discount_kind', 'reais') === 'percent' ? 'percent' : 'reais'; ?>
                <div class="dial discount-kind">
                    <select name="discount_kind">
                        <option value="reais" <?= $discountKind === 'reais' ? 'selected' : '' ?>>Reais</option>
                        <option value="percent" <?= $discountKind === 'percent' ? 'selected' : '' ?>>Porcentagem</option>
                    </select>
                    <input name="discount" value="<?= e(field($old, [], 'discount', $discountKind === 'percent' ? '' : '0,00')) ?>" placeholder="<?= $discountKind === 'percent' ? '0' : '0,00' ?>">
                </div>
                <p class="field-help" data-discount-note>Reais tira um valor fixo. Porcentagem incide sobre o valor da tabela.</p>
            </div>
            <div class="field"><label>Taxas adicionais</label><input name="additional_fees" data-money value="<?= e(field($old, [], 'additional_fees', '0,00')) ?>"></div>
            <div class="field"><label>Pagamento</label>
                <select name="payment_plan" data-payment-plan>
                    <option value="">Selecione</option>
                    <option value="total" <?= $paymentPlan === 'total' ? 'selected' : '' ?>>Pagamento total</option>
                    <option value="slip" <?= $paymentPlan === 'slip' ? 'selected' : '' ?>>Slip de pagamento</option>
                    <option value="convenio" <?= $paymentPlan === 'convenio' ? 'selected' : '' ?>>Convênio</option>
                </select>
                <p class="field-help">Total quita a reserva. Slip registra só a parte paga agora. Convênio zera o valor.</p>
            </div>
        </div>
        <div class="form-grid" data-pay-extra <?= in_array($paymentPlan, ['total', 'slip'], true) ? '' : 'hidden' ?>>
            <div class="field" data-pay-amount <?= $paymentPlan === 'slip' ? '' : 'hidden' ?>><label>Valor pago agora</label><input name="payment_amount" data-money value="<?= e(field($old, [], 'payment_amount', '0,00')) ?>"></div>
            <div class="field"><label>Forma de pagamento</label><select name="payment_method_id"><option value="">Selecione</option><?php foreach ($methods as $method): ?><option value="<?= (int) $method['id'] ?>" <?= field($old, [], 'payment_method_id') == $method['id'] ? 'selected' : '' ?>><?= e($method['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Parcelas</label><input type="number" name="payment_installments" min="1" value="<?= e(field($old, [], 'payment_installments', '1')) ?>"></div>
            <div class="field"><label>Identificador da transação</label><input name="payment_external_id" value="<?= e(field($old, [], 'payment_external_id')) ?>"></div>
            <div class="field"><label>Data do pagamento</label><input type="date" name="payment_date" value="<?= e(field($old, [], 'payment_date', today())) ?>"></div>
        </div>
        <p class="field-help" data-convenio-note <?= $paymentPlan === 'convenio' ? '' : 'hidden' ?>>Convênio deixa o valor final em R$ 0,00. Nada é lançado como recebido.</p>
        <div class="field"><label>Observação do pagamento</label><textarea name="payment_notes" rows="3"><?= e(field($old, [], 'payment_notes')) ?></textarea></div>
        <p class="field-help" data-step-error></p>
    </section>

    <section class="card" data-step>
        <h2>Revisão</h2>
        <div class="review-list" data-review></div>
        <p class="field-help">Confira os dados. O próximo passo grava a reserva só depois que você confirmar o resumo.</p>
        <p class="field-help" data-step-error></p>
    </section>
    <div class="wizard-actions">
        <button class="ghost" type="button" data-prev>Voltar</button>
        <button class="ghost" type="button" data-next>Continuar</button>
        <button type="submit" data-finish hidden>Ver resumo</button>
    </div>
</form>
