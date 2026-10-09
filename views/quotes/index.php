<?php
$priceMap = [];
foreach ($prices as $price) {
    if ((int) $price['price_cents'] <= 0) {
        continue;
    }
    $priceMap[(int) $price['vehicle_id']][(int) $price['modality_id']] = money((int) $price['price_cents']);
}
$groups = ['experiencia' => [], 'foto' => []];
foreach ($modalities as $modality) {
    $type = $modality['type'] === 'foto' ? 'foto' : 'experiencia';
    $groups[$type][] = $modality;
}
$groupTitles = ['experiencia' => 'Experiências', 'foto' => 'Pacotes de fotos'];
?>
<div class="card table-wrap">
    <table class="quotes-table">
        <thead><tr><th>Código</th><th>Cliente</th><th>Validade</th><th class="num">Valor</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($quotes as $quote): ?>
            <tr>
                <td><a class="code" href="/orcamentos/<?= (int) $quote['id'] ?>"><?= e($quote['code']) ?></a></td>
                <td><?= e($quote['full_name']) ?></td>
                <td class="when"><?= e(date_br($quote['valid_until'])) ?></td>
                <td class="num"><?= e(money((int) $quote['total_cents'])) ?></td>
                <td><?= e($quote['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($quotes === []): ?><tr><td colspan="5">Nenhum orçamento emitido.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (\App\Auth\Auth::can('quotes.manage')): ?>
<form method="post" action="/orcamentos" class="card stack quote-form" data-quote data-prices="<?= e(json_encode($priceMap, JSON_UNESCAPED_UNICODE)) ?>">
    <?= csrf_field() ?>
    <h2>Novo orçamento</h2>
    <h3 class="quote-section">Dados gerais</h3>
    <div class="quote-grid">
        <div class="field"><label>Cliente</label><select name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= e($client['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Veículo</label><select name="vehicle_id"><option value="">Opcional</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Validade</label><input type="date" name="valid_until"></div>
        <div class="field"><label>Data sugerida</label><input type="date" name="scheduled_date"></div>
        <div class="field"><label>Horário</label><input type="time" name="start_time"></div>
        <div class="field"><label>Participantes</label><input type="number" name="participants" value="1"></div>
    </div>
    <h3 class="quote-section">Itens do orçamento</h3>
    <?php foreach ($groupTitles as $type => $title): ?>
        <?php if ($groups[$type] === []) continue; ?>
        <h4 class="quote-sub"><?= e($title) ?></h4>
        <div class="quote-list">
            <?php foreach ($groups[$type] as $modality): ?>
                <?php
                $label = (string) $modality['name'];
                if ($type === 'foto') {
                    $label = preg_replace('/^Pacote de fotos\s+/u', '', $label) ?? $label;
                }
                $fixed = $type === 'foto' && (int) $modality['base_price_cents'] > 0 ? money((int) $modality['base_price_cents']) : '';
                ?>
                <label class="quote-item" data-modality="<?= (int) $modality['id'] ?>">
                    <input type="checkbox" name="modality_id[]" value="<?= (int) $modality['id'] ?>">
                    <span><?= e($label) ?></span>
                    <span class="quote-price" data-quote-price<?= $fixed !== '' ? ' data-fixed' : '' ?>><?= e($fixed) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <h3 class="quote-section quote-notes">Observações</h3>
    <div class="field"><textarea name="notes" rows="3"></textarea></div>
    <div class="quote-foot"><button type="submit">Emitir orçamento</button></div>
</form>
<?php endif; ?>
