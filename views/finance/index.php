<form method="get" class="toolbar filter-bar finance-period">
    <span>Período</span>
    <label class="field-inline"><span>de</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <span>a</span>
    <label class="field-inline"><span>até</span><input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="ghost" type="submit">Filtrar</button>
</form>
<section class="kpi-strip">
    <div class="kpi"><span>Vendido</span><strong><?= e(money($summary['sold'])) ?></strong></div>
    <div class="kpi"><span>Recebido</span><strong><?= e(money($summary['received'])) ?></strong></div>
    <div class="kpi is-alert"><span>Pendente</span><strong><?= e(money($summary['pending'])) ?></strong></div>
    <div class="kpi is-danger"><span>Despesas</span><strong><?= e(money($summary['expenses'])) ?></strong><em>caixa <?= e(money($summary['cash_result'])) ?></em></div>
</section>
<section class="finance-layout stack">
    <article class="card">
        <h2>Pagamentos</h2>
        <?php if ($payments === []): ?>
            <p class="muted">Nenhum pagamento neste período.</p>
        <?php else: ?>
            <div class="bleed">
                <table class="finance-payments">
                    <thead><tr><th>Data</th><th>Cliente</th><th>Reserva</th><th>Forma</th><th>Valor</th><th>Situação</th><th>Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="when"><?= e(date_br($payment['paid_at'])) ?></td>
                            <td><?= e($payment['full_name']) ?></td>
                            <td><?php if ($payment['reservation_id'] && $payment['code']): ?><a class="code" href="/reservas/<?= (int) $payment['reservation_id'] ?>"><?= e((string) $payment['code']) ?></a><?php endif; ?></td>
                            <td><?= e((string) $payment['method_name']) ?></td>
                            <td class="num"><?= e(money((int) $payment['amount_cents'])) ?></td>
                            <td><span class="badge pay-<?= e($payment['status']) ?>"><?= e(ucfirst((string) $payment['status'])) ?></span></td>
                            <td>
                                <?php if (\App\Auth\Auth::can('finance.manage') && $payment['status'] === 'confirmado'): ?>
                                    <form method="post" action="/financeiro/pagamentos/<?= (int) $payment['id'] ?>/estornar"><?= csrf_field() ?><button class="ghost finance-void" type="submit" data-confirm="Estornar este pagamento?">Estornar</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
    <div class="finance-side">
        <article class="card">
            <h2>Despesas</h2>
            <?php if ($expenses === []): ?>
                <p class="muted">Nenhuma despesa neste período.</p>
            <?php else: ?>
                <div class="bleed">
                    <table>
                        <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td class="when"><?= e(date_br($expense['expense_date'])) ?></td>
                                <td><?= e($expense['description']) ?></td>
                                <td><?= e((string) $expense['category']) ?></td>
                                <td class="num"><?= e(money((int) $expense['amount_cents'])) ?></td>
                                <td>
                                    <?php if (\App\Auth\Auth::can('finance.manage')): ?>
                                        <form method="post" action="/financeiro/despesas/<?= (int) $expense['id'] ?>/excluir"><?= csrf_field() ?><button class="ghost icon-btn" type="submit" aria-label="Excluir"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php if (\App\Auth\Auth::can('finance.manage')): ?>
        <form method="post" action="/financeiro/despesas" class="card expense-form">
            <?= csrf_field() ?>
            <h2>Lançar despesa</h2>
            <div class="expense-grid">
                <div class="field span-2"><label>Descrição</label><input name="description" required></div>
                <div class="field"><label>Valor</label><div class="affix affix-prefix"><span>R$</span><input name="amount" required inputmode="decimal"></div></div>
                <div class="field"><label>Data</label><input type="date" name="expense_date" value="<?= e(today()) ?>"></div>
                <div class="field"><label>Categoria</label><select name="category_id"><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Forma</label><select name="method_id"><option value="">Não informada</option><?php foreach ($methods as $method): ?><option value="<?= (int) $method['id'] ?>"><?= e($method['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="photo-sale-foot"><button type="submit">Lançar despesa</button></div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php if ($fees !== [] && \App\Auth\Auth::can('finance.manage')): ?>
<article class="card stack">
    <h2>Taxas de plataforma ainda não lançadas como despesa</h2>
    <?php foreach ($fees as $fee): ?>
        <form method="post" action="/financeiro/reservas/<?= (int) $fee['id'] ?>/taxa" class="actions"><?= csrf_field() ?><span><span class="code"><?= e($fee['code']) ?></span> · <?= e(money((int) $fee['platform_fee_cents'])) ?></span><button class="ghost" type="submit">Lançar taxa</button></form>
    <?php endforeach; ?>
</article>
<?php endif; ?>
