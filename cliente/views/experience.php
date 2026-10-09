<?php
$pista = require CLIENT_PATH . '/content/pista.php';
$sheet = $pista['vehicles'][$vehicle['slug']] ?? null;
$mine = [];
$rest = [];
foreach ($pista['people'] as $shot) {
    if (in_array($vehicle['slug'], $shot['vehicles'], true)) {
        $mine[] = $shot;
    } else {
        $rest[] = $shot;
    }
}
$people = array_merge($mine, $rest);
$routes = [];
foreach ($vehicle['prices'] as $price) {
    $slug = (string) $price['slug'];
    if (isset($pista['routes'][$slug])) {
        $routes[$slug] = $pista['routes'][$slug];
    }
}
?>
<div class="wrap page">
    <div class="shot wide"><?php if ($vehicle['image_file_id']): ?><img class="cover" src="/midia/<?= (int) $vehicle['image_file_id'] ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>"><?php endif; ?><span class="off">10% off</span></div>
    <h1><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></h1>
    <?php if ((int) $vehicle['is_demo']): ?><span class="badge demo">Demonstração</span><?php endif; ?>
    <p><?= e((string) $vehicle['description']) ?></p>
    <?php if ($sheet): ?>
        <div class="specs">
            <?php foreach ($sheet['specs'] as [$label, $value]): ?>
                <div class="spec"><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="list"><div class="row"><span>Lugares</span><strong><?= (int) $vehicle['capacity'] ?></strong></div><div class="row"><span>Local</span><strong>Balneário Camboriú</strong></div></div>
    <?php endif; ?>
    <?php if ($vehicle['photos'] !== []): ?>
        <section class="section car-gallery">
            <div class="section-head"><h2>O carro</h2></div>
            <div class="car-shots">
                <?php foreach ($vehicle['photos'] as $shot): ?>
                    <img src="/midia/<?= (int) $shot['file_id'] ?>" alt="<?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>">
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php
    $peopleTitle = 'Quem já foi';
    $peopleIntro = $mine === [] ? 'Fotos de quem passou pela experiência.' : 'Fotos deste carro e de quem passou pela pista.';
    $peopleCta = true;
    require CLIENT_PATH . '/views/partials/people.php';
    ?>
    <?php if ($routes !== []): ?>
        <section class="section">
            <div class="section-head"><h2>Trajetos</h2></div>
            <div class="route-slider" tabindex="0">
                <?php foreach ($routes as $route): ?>
                    <figure class="route-frame">
                        <img src="/assets/img/pista/<?= e($route['file']) ?>" alt="Mapa <?= e($route['title']) ?> em Balneário Camboriú">
                        <figcaption><?= e($route['title']) ?> · <?= e($route['time']) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php foreach ($routes as $route): ?>
                <article class="route">
                    <h3><?= e($route['title']) ?></h3>
                    <p><?= e($route['tagline']) ?> <span class="num"><?= e($route['time']) ?></span></p>
                    <ul>
                        <?php foreach ($route['stops'] as [$name, $text]): ?>
                            <li><strong><?= e($name) ?>:</strong> <?= e($text) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
            <?php if (count($routes) > 1): ?>
            <script>
                (function () {
                    var box = document.querySelector('.route-slider');
                    if (!box || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    var timer = setInterval(function () {
                        var card = box.querySelector('.route-frame');
                        if (!card) return;
                        var step = card.getBoundingClientRect().width + 10;
                        var end = box.scrollWidth - box.clientWidth - 4;
                        if (box.scrollLeft >= end) box.scrollTo({ left: 0, behavior: 'smooth' });
                        else box.scrollBy({ left: step, behavior: 'smooth' });
                    }, 4500);
                    box.addEventListener('pointerdown', function () { clearInterval(timer); }, { once: true });
                })();
            </script>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <form method="post" action="/experiencias/<?= e($vehicle['slug']) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <h2>Modalidade</h2>
        <?php foreach ($vehicle['prices'] as $price): ?>
            <?php
            $blurb = (string) $price['description'];
            if (isset($pista['routes'][$price['slug']])) {
                $route = $pista['routes'][$price['slug']];
                $blurb = $route['tagline'] . ' ' . $route['time'] . '.';
            }
            ?>
            <label class="choice">
                <span><input type="radio" name="modality_id" value="<?= (int) $price['id'] ?>" required> <?= e($price['name']) ?><br><small><?= e($blurb) ?></small></span>
                <strong class="price num"><?= e(money((int) $price['price_cents'])) ?></strong>
            </label>
        <?php endforeach; ?>
        <?php if ($vehicle['prices'] === []): ?><p class="empty">Este veículo ainda não tem preço de experiência no sistema administrativo.</p><?php endif; ?>
        <div class="field"><span>Data, se já quiser agendar</span><input type="date" name="date" autocomplete="off"></div>
        <div class="field"><span>Horário</span><input type="time" name="time" autocomplete="off"></div>
        <p class="meta">Sem data, a experiência fica para agendar depois. Com data, a agenda real é consultada. Se já houver um pacote de fotos no carrinho, esta experiência entra na mesma compra. O pagamento não é confirmado nesta tela.</p>
        <button class="btn block" type="submit" <?= $vehicle['prices'] === [] ? 'disabled' : '' ?>>Adicionar à compra</button>
    </form>
    <?php if ($sheet): ?>
        <section class="section">
            <div class="section-head"><h2>Ficha do carro</h2></div>
            <img class="sheet" src="/assets/img/pista/<?= e($sheet['sheet']) ?>" alt="Ficha <?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?>">
        </section>
    <?php endif; ?>
</div>
