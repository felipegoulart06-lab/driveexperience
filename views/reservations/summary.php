<?php
$skip = ['_csrf', 'intent', 'password', 'client_cnh', 'client_residence'];
$render = function (string $name, mixed $value) use (&$render, $skip): void {
    if (in_array($name, $skip, true)) {
        return;
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $child = is_int($key) ? $name . '[]' : $name . '[' . $key . ']';
            $render($child, $item);
        }
        return;
    }
    echo '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
};
$plans = ['total' => 'Pagamento total', 'slip' => 'Slip de pagamento', 'convenio' => 'Convênio'];
$plan = (string) ($data['payment_plan'] ?? '');
?>
<article class="card review-card">
    <h2>Resumo da reserva</h2>
    <dl class="review-list">
        <div><dt>Cliente</dt><dd><?= e($data['client_name']) ?></dd></div>
        <div><dt>Origem</dt><dd><?= e($data['origin_name']) ?></dd></div>
        <div><dt>Veículo</dt><dd><?= e($data['vehicle_name']) ?></dd></div>
        <div><dt>Quando</dt><dd><?php if ($data['scheduled_date']): ?><?= e(date_br((string) $data['scheduled_date'])) ?> <?= e((string) $data['start_time']) ?> · <?= (int) $data['duration_minutes'] ?> min<?php else: ?>Sem horário<?php endif; ?> · <?= (int) $data['participants'] ?> participante(s)</dd></div>
        <?php if ($data['partner_name'] !== ''): ?>
            <div><dt>Parceria</dt><dd><?= e($data['partner_name']) ?> · comissão <?= e(number_format(((int) $data['commission_bps']) / 100, 2, ',', '.')) ?>% (<?= e(money((int) $data['commission_cents'])) ?>)</dd></div>
        <?php endif; ?>
        <?php if ((int) $data['cnh_file_id'] > 0): ?><div><dt>CNH</dt><dd>Anexada</dd></div><?php endif; ?>
        <?php if ((int) $data['residence_file_id'] > 0): ?><div><dt>Comprovante</dt><dd>Anexado</dd></div><?php endif; ?>
        <?php if ($data['instagram'] !== ''): ?><div><dt>Instagram</dt><dd>@<?= e($data['instagram']) ?></dd></div><?php endif; ?>
    </dl>
    <table class="review-items">
        <thead><tr><th>Item</th><th class="num">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <tr><td><?= e($item['description']) ?></td><td class="num"><?= e(money((int) $item['total_cents'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <dl class="review-list">
        <div><dt>Tabela</dt><dd><?= e(money((int) $data['original_cents'])) ?></dd></div>
        <div><dt>Desconto</dt><dd><?php if (($data['discount_kind'] ?? 'reais') === 'percent'): ?><?= e(number_format(((int) $data['discount_bps']) / 100, 2, ',', '.')) ?>% · <?php endif; ?><?= e(money((int) $data['discount_cents'])) ?></dd></div>
        <div><dt>Taxas</dt><dd><?= e(money((int) $data['fees_cents'])) ?></dd></div>
        <div><dt>Valor final</dt><dd><strong><?= e(money((int) $data['final_cents'])) ?></strong></dd></div>
        <div><dt>Pagamento</dt><dd><?= e($plans[$plan] ?? 'Sem lançamento agora') ?> · <?= e(money((int) $data['payment_cents'])) ?></dd></div>
        <div><dt>Saldo previsto</dt><dd><?= e(money(max(0, (int) $data['final_cents'] - (int) $data['payment_cents']))) ?></dd></div>
    </dl>
    <?php if ($plan === 'convenio'): ?><p class="field-help">Convênio: o valor da reserva foi zerado.</p><?php endif; ?>
    <form method="post" action="/reservas" class="wizard-actions">
        <?= csrf_field() ?>
        <?php foreach ($input as $key => $value) { $render((string) $key, $value); } ?>
        <button class="ghost" type="submit" name="intent" value="edit">Voltar e alterar</button>
        <button type="submit" name="intent" value="save">Confirmar reserva</button>
    </form>
</article>
