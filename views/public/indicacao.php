<?php
$digits = preg_replace('/\D+/', '', $phone) ?? '';
$talk = $digits !== '' ? 'https://wa.me/' . $digits . '?text=' . rawurlencode('Quero indicar um cliente para a Drive Xperience.') : '#indicar';
?>
<header class="top">
    <strong>DRIVE XPERIENCE</strong>
    <a href="#comissoes">Ver comissões</a>
</header>
<section class="hero">
    <p class="kicker"><?= e($city !== '' ? $city : 'Balneário Camboriú') ?></p>
    <h1>Indique a experiência. A comissão fica com quem trouxe o cliente.</h1>
    <p class="lead">Cada produto e serviço da <?= e($company) ?> tem um tipo de comissão. O valor nasce do preço fechado da reserva, depois que o atendimento termina e o pagamento está confirmado.</p>
    <div class="actions">
        <a href="#comissoes">Ver a tabela</a>
        <a class="quiet" href="#indicar">Como indicar</a>
    </div>
</section>
<section class="wrap" id="comissoes">
    <h2>Três tipos de comissão</h2>
    <p class="intro">A porcentagem muda conforme o que foi contratado. Experiência de direção, carona e produção de fotos não dividem a mesma regra.</p>
    <div class="kinds">
        <article class="kind">
            <span>Direção</span>
            <h3>10% da experiência</h3>
            <p>Super Drive, Drive Xperience e Rota da Fama. A base é o valor da experiência no veículo escolhido, sem somar o pacote de fotos.</p>
        </article>
        <article class="kind">
            <span>Carona</span>
            <h3>8% do passeio</h3>
            <p>Carona e Carona da Fama. Vale quando a pessoa indicada vai como passageira, com a condução da equipe.</p>
        </article>
        <article class="kind">
            <span>Fotos</span>
            <h3>12% ou 15% do pacote</h3>
            <p>Prata e Ouro pagam 12%. Diamante paga 15%. A comissão incide só sobre o pacote, mesmo quando ele entra na mesma reserva da experiência.</p>
        </article>
    </div>
</section>
<section class="wrap" style="padding-top: 0;">
    <h2>Produto por produto</h2>
    <p class="intro">O exemplo usa o menor preço ativo da frota. Se o veículo fechado for outro, a comissão acompanha o valor daquela reserva.</p>
    <table class="sheet">
        <thead>
            <tr><th>Produto</th><th>Tipo</th><th>Comissão</th><th>Exemplo</th></tr>
        </thead>
        <tbody>
        <?php foreach ($offers as $offer): ?>
            <tr>
                <td>
                    <strong><?= e($offer['name']) ?></strong>
                    <span class="muted"><?= e($offer['description']) ?></span>
                </td>
                <td><?= e($offer['kind']) ?></td>
                <td class="rate"><?= e($offer['percent']) ?>%</td>
                <td class="rate"><?= $offer['example_cents'] > 0 ? e(money($offer['example_cents'])) : 'Sobre o valor fechado' ?><?php if ($offer['base_cents'] > 0): ?><span class="muted">em <?= e(money($offer['base_cents'])) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($offers === []): ?>
            <tr><td colspan="4">Os produtos ainda não estão publicados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<section class="wrap" id="indicar" style="padding-top: 0;">
    <h2>Como a indicação vira comissão</h2>
    <div class="steps">
        <article class="step"><b>01</b><p>A pessoa indicada diz, na reserva, o nome de quem indicou.</p></article>
        <article class="step"><b>02</b><p>A equipe registra o indicador. Experiência e fotos, se houver as duas, geram comissões separadas.</p></article>
        <article class="step"><b>03</b><p>O pagamento sai depois do atendimento encerrado e do valor confirmado. Reserva cancelada não gera comissão.</p></article>
    </div>
    <h2 style="margin-top: 40px;">Parceiro comercial</h2>
    <p class="intro">Quem indica com frequência pode ser cadastrado como parceiro. Nesse caso a porcentagem é a combinada no cadastro e entra na própria reserva, no lugar da tabela acima. Não se soma com o Indique e Ganhe da mesma venda.</p>
    <ul class="rules">
        <li>Uma reserva tem um indicador. Dois nomes na mesma venda não dividem a comissão.</li>
        <li>O cliente que já estava na base e volta sozinho não conta como indicação nova.</li>
        <li>Desconto concedido na reserva reduz a base. A comissão incide sobre o valor final daquele item.</li>
        <li>Fotos contratadas depois, em outra venda, geram uma comissão nova só do pacote.</li>
    </ul>
</section>
<section class="band">
    <h2>Quer indicar alguém agora</h2>
    <p>Fale com a equipe e diga o nome de quem vai viver a experiência. A comissão fica vinculada à reserva.</p>
    <a href="<?= e($talk) ?>">Falar no WhatsApp</a>
</section>
<p class="foot"><?= e($company) ?> · programa Indique e Ganhe. As porcentagens desta página são as vigentes para novas reservas.</p>
