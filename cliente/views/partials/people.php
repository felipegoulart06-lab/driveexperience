<?php
/** @var list<array{file:string,alt:string,caption:string}> $people */
/** @var string $peopleTitle */
/** @var string $peopleIntro */
/** @var bool $peopleCta */
$peopleCta = $peopleCta ?? false;
?>
<section class="section people-section">
    <div class="section-head"><h2><?= e($peopleTitle) ?></h2></div>
    <?php if ($peopleIntro !== ''): ?><p class="people-intro"><?= e($peopleIntro) ?></p><?php endif; ?>
    <div class="people">
        <?php foreach ($people as $shot): ?>
            <figure>
                <img src="/assets/img/pista/<?= e($shot['file']) ?>" alt="<?= e($shot['alt']) ?>">
                <figcaption><?= e($shot['caption']) ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <?php if ($peopleCta): ?><a class="btn people-cta" href="/experiencias/fotos">Selecionar pacote de fotos</a><?php endif; ?>
</section>
