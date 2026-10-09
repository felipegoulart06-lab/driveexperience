<?php use App\Domain\Statuses; ?>
<div class="card table-wrap">
    <table class="photos-table">
        <thead><tr><th>Pacote</th><th>Cliente</th><th>Reserva</th><th>Valor</th><th>Situação</th><th>Entrega</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/fotos/<?= (int) $item['id'] ?>"><?= e($item['package_name'] ?: 'Pacote') ?></a></td>
                <td><?= e($item['full_name']) ?></td>
                <td><?php if ((string) $item['code'] !== ''): ?><span class="code"><?= e((string) $item['code']) ?></span><?php endif; ?></td>
                <td class="num"><?= e(money((int) $item['amount_cents'])) ?></td>
                <td><span class="badge st-<?= e($item['status']) ?>"><?= e(Statuses::label('production', $item['status'])) ?></span></td>
                <td class="when"><?= e(date_br($item['due_date'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?><tr><td colspan="6">Nenhuma produção de fotos registrada.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="6"><?= count($items) ?> pacote(s)</td></tr></tfoot>
    </table>
</div>
<?php if (\App\Auth\Auth::can('photos.manage')): ?>
<form method="post" action="/fotos" class="card stack photo-sale">
    <?= csrf_field() ?>
    <h2>Vender pacote avulso</h2>
    <div class="photo-sale-grid">
        <div class="field"><label>Cliente</label><select name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= e($client['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Pacote</label><select name="modality_id"><?php foreach ($packages as $package): ?><option value="<?= (int) $package['id'] ?>"><?= e($package['name']) ?> · <?= e(money((int) $package['base_price_cents'])) ?></option><?php endforeach; ?></select></div>
        <div class="field">
            <label>Valor</label>
            <div class="affix affix-prefix">
                <span>R$</span>
                <input name="amount" placeholder="Usa o preço do pacote se vazio" inputmode="decimal">
            </div>
            <p class="field-help">Usa o preço do pacote se vazio</p>
        </div>
        <div class="field"><label>Previsão de entrega</label><input type="date" name="due_date"></div>
    </div>
    <div class="field"><label>Observações</label><textarea name="notes" rows="3"></textarea></div>
    <div class="photo-sale-foot"><button type="submit">Contratar</button></div>
</form>
<?php endif; ?>
