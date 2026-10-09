<article class="card">
    <p><?= e($quote['full_name']) ?> · válido até <?= e(date_br($quote['valid_until'])) ?> · <?= e($quote['status']) ?></p>
    <ul><?php foreach ($quote['items'] as $item): ?><li><?= e($item['description']) ?> · <?= e(money((int) $item['total_cents'])) ?></li><?php endforeach; ?></ul>
    <p><strong><?= e(money((int) $quote['total_cents'])) ?></strong></p>
    <p><?= e((string) $quote['notes']) ?></p>
    <p class="muted"><?= e((string) $quote['conditions']) ?></p>
    <div class="actions">
        <?php if (\App\Auth\Auth::can('documents.issue')): ?><form method="post" action="/orcamentos/<?= (int) $quote['id'] ?>/pdf"><?= csrf_field() ?><button type="submit">Gerar PDF</button></form><?php endif; ?>
        <?php if (\App\Auth\Auth::can('reservations.manage') && $quote['status'] !== 'convertido'): ?><form method="post" action="/orcamentos/<?= (int) $quote['id'] ?>/converter"><?= csrf_field() ?><button class="ghost" type="submit">Converter em reserva</button></form><?php endif; ?>
    </div>
    <p class="muted">A conversão só conclui se a data, o horário e o veículo estiverem preenchidos e o horário estiver livre.</p>
</article>
