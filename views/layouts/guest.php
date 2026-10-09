<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Drive Xperience</title>
    <link rel="stylesheet" href="/assets/css/theme.css?v=<?= (int) filemtime(base_path('public/assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) filemtime(base_path('public/assets/css/app.css')) ?>">
</head>
<body>
<main class="guest">
    <section class="card guest-card">
        <div class="brand"><strong>DRIVE XPERIENCE</strong><div class="muted">Acesso interno</div></div>
        <?php if (!empty($flash)): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <?= $content ?>
    </section>
</main>
</body>
</html>
