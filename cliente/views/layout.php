<?php
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$active = static function (string $prefix) use ($path): string {
    if ($prefix === '/') {
        return $path === '/' ? 'is-active' : '';
    }
    return str_starts_with($path, $prefix) ? 'is-active' : '';
};
$icon = static function (string $d): string {
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="' . $d . '"/></svg>';
};
$name = trim((string) ($user['full_name'] ?? ''));
$first = $name !== '' ? preg_split('/\s+/', $name)[0] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1B33">
    <title><?= e($title ?? 'Drive Xperience') ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/assets/css/tokens.css?v=<?= (int) @filemtime(CLIENT_PATH . '/public/assets/css/tokens.css') ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(CLIENT_PATH . '/public/assets/css/app.css') ?>">
</head>
<body>
<header class="mobile-head">
    <a class="brand" href="/">DRIVE XPERIENCE</a>
    <span class="head-tools">
        <a href="/notificacoes" aria-label="Notificações">Avisos</a>
        <a href="/carrinho" aria-label="Carrinho">Carrinho</a>
    </span>
</header>
<header class="topbar">
    <a class="brand" href="/">DRIVE XPERIENCE</a>
    <nav>
        <a class="<?= $active('/experiencias') ?>" href="/experiencias">Experiências</a>
        <a class="<?= $active('/loja') ?>" href="/loja">Loja</a>
        <a class="<?= $active('/promocoes') ?>" href="/promocoes">Promoções</a>
        <a class="<?= $active('/vales') ?>" href="/vales">Vales-presente</a>
    </nav>
    <div class="top-tools">
        <a href="/carrinho">Carrinho</a>
        <a href="<?= $user ? '/meu-drive' : '/entrar' ?>"><?= $user ? e($first) : 'Entrar' ?></a>
    </div>
</header>
<main>
<?php if (!empty($flash)): ?><div class="wrap"><p class="flash <?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></p></div><?php endif; ?>
<?= $content ?>
</main>
<nav class="tabbar" aria-label="Principal">
    <a class="<?= $active('/') ?>" href="/"><?= $icon('M4 10.5 12 4l8 6.5V20H4Z') ?>Início</a>
    <a class="<?= $active('/experiencias') ?>" href="/experiencias"><?= $icon('M3 17h18M5 17l2-6h10l2 6M7 11l2-4h6l2 4') ?>Experiências</a>
    <a class="<?= $active('/loja') ?>" href="/loja"><?= $icon('M6 7h12l-1 13H7L6 7Zm3 0V5h6v2') ?>Loja</a>
    <a class="<?= $active('/meu-drive') ?>" href="/meu-drive"><?= $icon('M12 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-6 8a6 6 0 0 1 12 0') ?>Perfil</a>
</nav>
<footer class="foot">
    <div><strong class="brand">DRIVE XPERIENCE</strong><p>Experiências com supercarros em Balneário Camboriú.</p></div>
    <div><p>Experiências</p><p>Loja oficial</p><p>Vales-presente</p></div>
    <div><p>Drive Points</p><p>Indique e ganhe</p><p>Meu Drive</p></div>
    <div><p>odrivexperience.com.br</p></div>
</footer>
<script src="/assets/js/hscroll.js?v=<?= (int) @filemtime(CLIENT_PATH . '/public/assets/js/hscroll.js') ?>"></script>
</body>
</html>
