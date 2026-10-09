<?php
$slides = [];
foreach ($vehicles as $vehicle) {
    if (!empty($vehicle['image_file_id'])) {
        $slides[] = $vehicle;
    }
}
$slideCount = count($slides);
?>
<div class="fold">
<section class="hero">
    <?php if ($slideCount > 0): ?>
        <div class="hero-slides" aria-hidden="true">
            <?php foreach ($slides as $slide): ?>
                <img src="/midia/<?= (int) $slide['image_file_id'] ?>" alt="">
            <?php endforeach; ?>
        </div>
        <?php if ($slideCount > 1):
            $slot = 5.0;
            $fade = 0.9;
            $duration = $slideCount * $slot;
            $pct = static function (float $seconds) use ($duration): string {
                return rtrim(rtrim(number_format(($seconds / $duration) * 100, 3, '.', ''), '0'), '.');
            };
            $num = static function (float $seconds): string {
                return rtrim(rtrim(number_format($seconds, 3, '.', ''), '0'), '.');
            };
        ?>
        <style>
            .hero-slides img { animation: hero-fade <?= $num($duration) ?>s linear infinite; }
            <?php foreach ($slides as $index => $slide): ?>
            .hero-slides img:nth-child(<?= (int) $index + 1 ?>) { animation-delay: <?= $num($index * $slot - $fade) ?>s; }
            <?php endforeach; ?>
            @keyframes hero-fade {
                0% { opacity: 0; }
                <?= $pct($fade) ?>% { opacity: 1; }
                <?= $pct($slot) ?>% { opacity: 1; }
                <?= $pct($slot + $fade) ?>% { opacity: 0; }
                100% { opacity: 0; }
            }
            @media (prefers-reduced-motion: reduce) {
                .hero-slides img { animation: none; opacity: 0; }
                .hero-slides img:first-child { opacity: 1; }
            }
        </style>
        <?php endif; ?>
    <?php endif; ?>
    <div class="scrim"></div>
    <div class="hero-copy">
        <p class="kicker">Balneário Camboriú</p>
        <h1>10% off para dirigir um supercarro</h1>
        <p class="hero-note">Desconto, oferta e benefício em cada experiência.</p>
        <a class="btn" href="/experiencias">Ver experiências</a>
    </div>
</section>
<section class="hello">
    <p><?= $user ? 'Olá, ' . e(preg_split('/\s+/', (string) $user['full_name'])[0]) . '. Pronto para a próxima experiência?' : 'Entre para ver seus pedidos.' ?></p>
    <?php if (!$user): ?><a class="hello-enter" href="/entrar">Entrar</a><?php endif; ?>
</section>
</div>
<?php
$promo = $monthPromos[0] ?? null;
$offerImage = !empty($promo['image_file_id']) ? '/midia/' . (int) $promo['image_file_id'] : '/assets/img/experiencias/fotos.jpg';
$available = $points ? (int) $points['available'] : null;
$goal = (int) ($pointsGoal ?? 500);
$pct = $available === null ? null : (!empty($pointsReached) || $goal <= 0 ? 100 : min(100, (int) round($available / max(1, $goal) * 100)));
$stories = [
    [
        'label' => 'Experiências',
        'ring' => 'story-exp',
        'image' => '/assets/img/experiencias/fotos.jpg',
        'kicker' => 'Pista',
        'title' => 'Os conversíveis da orla',
        'text' => 'Direção e carona nos carros da frota, em Balneário Camboriú.',
        'action' => 'Ver experiências',
        'href' => '/experiencias',
    ],
    [
        'label' => 'Ofertas',
        'ring' => 'story-offer',
        'image' => $offerImage,
        'kicker' => 'Oferta do mês',
        'title' => $promo['title'] ?? 'Oferta do mês',
        'text' => trim(($promo['summary'] ?? 'A condição da pista está na tabela da frota.') . ' Porsche 911 Cabriolet, Rota DRIVE, de 25 a 40 min.'),
        'action' => 'Quero viver essa experiência',
        'href' => '/experiencias/porsche-911-cabriolet-turbo-s',
    ],
    [
        'label' => 'Pontos',
        'ring' => 'story-points',
        'image' => '/assets/img/sorteios/pontos.jpg',
        'badge' => $available !== null ? (string) $available : '',
        'kicker' => 'Drive Points',
        'title' => 'Seus Drive Points',
        'text' => $available === null ? 'Entre para ver seus pontos, o cashback e as vantagens.' : $available . ' pontos disponíveis',
        'meter' => $pct,
        'meterLabel' => $available === null ? '' : (!empty($pointsReached) ? 'Benefício de ' . $goal . ' pts disponível' : 'Próximo benefício: ' . $goal . ' pts'),
        'extra' => 'Como ganhar pontos: reserve uma experiência ou indique um amigo. A indicação vale ' . (int) ($referralPoints ?? 200) . ' pontos quando ele fizer a experiência.',
        'action' => $available === null ? 'Entrar' : 'Ver benefícios',
        'href' => $available === null ? '/entrar' : '/fidelidade',
    ],
    [
        'label' => 'Presente',
        'ring' => 'story-gift',
        'image' => '/assets/img/sorteios/kit.jpg',
        'kicker' => 'Vale-presente',
        'title' => 'Entregue a experiência',
        'text' => 'Um vale para alguém dirigir ou ir de carona. O valor segue a tabela da frota.',
        'action' => 'Comprar ou enviar',
        'href' => '/vales',
    ],
    [
        'label' => 'Indique',
        'ring' => 'story-invite',
        'image' => '/assets/img/sorteios/indique.jpg',
        'kicker' => 'Indique',
        'title' => 'Chame um amigo',
        'text' => 'Ele faz a experiência e você recebe ' . (int) ($referralPoints ?? 200) . ' Drive Points.',
        'action' => 'Indicar um amigo',
        'href' => '/indique',
    ],
    [
        'label' => 'Eventos',
        'ring' => 'story-event',
        'image' => '/assets/img/experiencias/eventos.jpg',
        'kicker' => 'Eventos',
        'title' => 'Na pista, com a sua turma',
        'text' => 'Lançamento, formatura e encontro de empresa, com o carro da frota.',
        'action' => 'Ver eventos',
        'href' => '/experiencias/eventos',
    ],
];
?>
<nav class="stories" aria-label="Destaques">
    <?php foreach ($stories as $index => $story): ?>
        <button type="button" class="story-open" data-story="<?= (int) $index ?>">
            <span class="story-ring <?= e($story['ring']) ?>">
                <img src="<?= e($story['image']) ?>" alt="">
                <?php if (($story['badge'] ?? '') !== ''): ?><b class="story-badge num"><?= e($story['badge']) ?></b><?php endif; ?>
            </span>
            <?= e($story['label']) ?>
        </button>
    <?php endforeach; ?>
</nav>
<div class="story-screen" hidden>
    <div class="story-bars" aria-hidden="true"></div>
    <button type="button" class="story-close" aria-label="Fechar">Fechar</button>
    <img class="story-photo" alt="">
    <div class="story-shade"></div>
    <div class="story-copy">
        <p class="kicker"></p>
        <h2></h2>
        <p class="story-text"></p>
        <p class="story-extra"></p>
        <div class="story-meter">
            <p class="story-next"></p>
            <div class="story-track"><span></span></div>
        </div>
        <a class="btn story-go" href="/experiencias">Ver</a>
    </div>
</div>
<script type="application/json" id="story-data"><?= json_encode($stories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var data = JSON.parse(document.getElementById('story-data').textContent);
    var screen = document.querySelector('.story-screen');
    var bars = screen.querySelector('.story-bars');
    var photo = screen.querySelector('.story-photo');
    var kicker = screen.querySelector('.kicker');
    var title = screen.querySelector('h2');
    var text = screen.querySelector('.story-text');
    var extra = screen.querySelector('.story-extra');
    var meter = screen.querySelector('.story-meter');
    var nextLabel = screen.querySelector('.story-next');
    var fill = screen.querySelector('.story-track span');
    var go = screen.querySelector('.story-go');
    var index = 0;
    var timer = 0;
    var hold = 5.6;
    data.forEach(function () {
        var mark = document.createElement('i');
        mark.appendChild(document.createElement('b'));
        bars.appendChild(mark);
    });
    function show(next) {
        index = (next + data.length) % data.length;
        var item = data[index];
        photo.src = item.image;
        photo.alt = item.label;
        kicker.textContent = item.kicker || '';
        title.textContent = item.title || '';
        text.textContent = item.text || '';
        extra.textContent = item.extra || '';
        extra.hidden = !item.extra;
        if (item.meter === null || item.meter === undefined || item.meterLabel === '') {
            meter.hidden = true;
        } else {
            meter.hidden = false;
            nextLabel.textContent = item.meterLabel;
            fill.style.width = item.meter + '%';
        }
        go.textContent = item.action;
        go.href = item.href;
        Array.prototype.forEach.call(bars.children, function (bar, i) {
            bar.classList.toggle('done', i < index);
            bar.classList.toggle('now', i === index);
            var bit = bar.firstChild;
            bit.style.transition = 'none';
            bit.style.width = i < index ? '100%' : '0';
        });
        var current = bars.children[index].firstChild;
        requestAnimationFrame(function () {
            current.style.transition = 'width ' + hold + 's linear';
            current.style.width = '100%';
        });
        window.clearTimeout(timer);
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            timer = window.setTimeout(function () { step(1); }, hold * 1000);
        }
    }
    function step(dir) {
        var next = index + dir;
        if (next >= data.length) {
            close();
            return;
        }
        if (next < 0) {
            show(0);
            return;
        }
        show(next);
    }
    function open(start) {
        screen.hidden = false;
        document.body.classList.add('story-open');
        show(start);
        screen.querySelector('.story-close').focus();
    }
    function close() {
        window.clearTimeout(timer);
        screen.hidden = true;
        document.body.classList.remove('story-open');
    }
    document.querySelectorAll('.story-open').forEach(function (button) {
        button.addEventListener('click', function () { open(Number(button.getAttribute('data-story'))); });
    });
    screen.querySelector('.story-close').addEventListener('click', close);
    var startX = 0;
    screen.addEventListener('pointerdown', function (event) {
        if (event.target.closest('.story-go, .story-close')) return;
        startX = event.clientX;
        window.clearTimeout(timer);
    });
    screen.addEventListener('pointerup', function (event) {
        if (event.target.closest('.story-go, .story-close')) return;
        var delta = event.clientX - startX;
        if (delta > 48) step(-1);
        else if (delta < -48) step(1);
        else if (event.clientX < window.innerWidth * 0.35) step(-1);
        else step(1);
    });
    document.addEventListener('keydown', function (event) {
        if (screen.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowRight') step(1);
        if (event.key === 'ArrowLeft') step(-1);
    });
})();
</script>
<div class="wrap">
    <?php if (!empty($monthPromos)): ?>
    <section class="section month-section">
        <div class="section-head"><h2>Promo do mês</h2></div>
        <div class="month-promos<?= count($monthPromos) === 1 ? ' single' : '' ?>">
            <?php foreach ($monthPromos as $promo): ?>
                <a class="month-promo" href="<?= e($promo['href']) ?>">
                    <div class="month-visual">
                        <?php if (!empty($promo['image_file_id'])): ?><img src="/midia/<?= (int) $promo['image_file_id'] ?>" alt=""><?php endif; ?>
                        <p class="month-chip"><span>Até <?= e(date_br(substr((string) $promo['ends_on'], 0, 10))) ?></span><strong><?= e($promo['occasion']) ?></strong></p>
                    </div>
                    <div class="month-copy">
                        <p class="kicker">Promo do mês<?php if ((int) $promo['is_demo']): ?> · Demonstração<?php endif; ?></p>
                        <h3><?= e($promo['title']) ?></h3>
                        <p><?= e($promo['summary']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php
    $pista = require CLIENT_PATH . '/content/pista.php';
    $people = $pista['people'];
    $peopleTitle = 'Na pista';
    $peopleIntro = $pista['intro'];
    $peopleCta = true;
    require CLIENT_PATH . '/views/partials/people.php';
    ?>
    <section class="section offer-intro">
        <p class="kicker">Ofertas</p>
        <h2>Desconto nos carros da experiência</h2>
        <p>Cada carro da pista está com 10% off. A vantagem é dirigir com essa condição, e o benefício fica na conta.</p>
    </section>
    <section class="section">
        <div class="section-head"><h2>Experiências</h2><a href="/experiencias">Ver todos →</a></div>
        <div class="rail">
            <?php foreach ($vehicles as $vehicle): ?>
                <a class="card" href="/experiencias/<?= e($vehicle['slug']) ?>">
                    <div class="shot"><?php if ($vehicle['image_file_id']): ?><img class="cover" src="/midia/<?= (int) $vehicle['image_file_id'] ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>"><?php endif; ?><span class="off">10% off</span></div>
                    <h3><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></h3>
                    <p class="price num"><?php if ($vehicle['from_cents']): ?>a partir de <?= e(money((int) $vehicle['from_cents'])) ?><?php else: ?>Preço no detalhe<?php endif; ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="section">
        <div class="section-head"><h2>Vantagens</h2></div>
        <div class="perks">
            <a class="perk perk-blue" href="/experiencias">
                <span class="num">01</span>
                <h3>Desconto</h3>
                <p>10% off marcado em cada carro da experiência.</p>
            </a>
            <a class="perk perk-amber" href="/experiencias">
                <span class="num">02</span>
                <h3>Oferta</h3>
                <p>A condição aparece na pista, do primeiro carro ao último.</p>
            </a>
            <a class="perk perk-navy" href="/fidelidade">
                <span class="num">03</span>
                <h3>Benefício</h3>
                <p>Drive Points e recompensas continuam na sua conta.</p>
            </a>
            <a class="perk perk-green" href="/indique">
                <span class="num">04</span>
                <h3>Vantagem</h3>
                <p>Indique um amigo e leve a próxima experiência.</p>
            </a>
        </div>
    </section>
    <section class="section">
        <div class="section-head"><h2>Sorteios</h2></div>
        <p class="raffle-note">Exemplos de demonstração. Não são sorteios oficiais.</p>
        <div class="raffles">
            <article class="raffle raffle-navy">
                <div>
                    <p class="kicker">Pista</p>
                    <h3>Uma volta no Camaro</h3>
                    <p>Super Drive no Camaro SS Convertible para quem reservar em outubro.</p>
                </div>
                <p class="when">18/10/2026 · 1 vencedor</p>
            </article>
            <article class="raffle raffle-blue">
                <div>
                    <p class="kicker">Pontos</p>
                    <h3>500 Drive Points</h3>
                    <p>Toda sexta de outubro, entre as contas com visita na semana.</p>
                </div>
                <p class="when">Sextas · 4 sorteios</p>
            </article>
            <article class="raffle raffle-green">
                <div>
                    <p class="kicker">Loja</p>
                    <h3>Kit da pista</h3>
                    <p>Camiseta e boné. Entra quem indicar um amigo até o fim do mês.</p>
                </div>
                <p class="when">31/10/2026 · 3 kits</p>
            </article>
        </div>
    </section>
    <section class="section">
        <div class="section-head"><h2>Promoções</h2><a href="/promocoes">Ver todos →</a></div>
        <?php foreach ($campaigns as $campaign): ?>
            <article class="panel">
                <p class="kicker"><?= $campaign['ends_on'] ? 'Até ' . e(date_br((string) $campaign['ends_on'])) : 'Campanha' ?><?php if ((int) $campaign['is_demo']): ?> · Demonstração<?php endif; ?></p>
                <h3 class="cond"><?= e($campaign['name']) ?></h3>
                <p><?= e((string) $campaign['description']) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
    <section class="section">
        <div class="section-head"><h2>Loja</h2><a href="/loja">Ver todos →</a></div>
        <div class="grid-2">
            <?php foreach ($products as $product): ?>
                <a class="card" href="/loja/<?= e($product['slug']) ?>">
                    <div class="shot square"><?php if (!empty($product['image_path'])): ?><img class="cover" src="/assets/img/<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?></div>
                    <h3><?= e($product['name']) ?></h3>
                    <p class="offer"><?php if ((int) $product['is_demo']): ?><span class="badge demo">Demonstração</span><?php endif; ?><span class="price num"><?= e(money((int) ($product['promo_cents'] ?: $product['price_cents']))) ?></span></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="section">
        <div class="section-head"><h2>Indique e ganhe</h2></div>
        <article class="invite">
            <div class="invite-main">
                <p class="kicker">Para quem indica</p>
                <p class="invite-num num"><?= (int) ($referralPoints ?? 200) ?></p>
                <h3>Drive Points</h3>
                <p>Entram na sua conta quando o amigo fizer a experiência. O cadastro sozinho deixa a indicação pendente.</p>
            </div>
            <div class="invite-steps">
                <p><span>1</span>Compartilhe seu código</p>
                <p><span>2</span>O amigo reserva um carro</p>
                <p><span>3</span>Você recebe os pontos</p>
                <a class="btn" href="/indique">Ver meu código</a>
            </div>
        </article>
    </section>
    <?php if ($activity): ?>
    <section class="section">
        <div class="section-head"><h2>Últimas atividades</h2></div>
        <div class="list">
            <?php foreach ($activity as $item): ?>
                <div class="row"><div><strong><?= e($item['title']) ?></strong><div class="meta"><?= e($item['body']) ?></div></div><span class="mono"><?= e(date_br((string) $item['created_at'])) ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
