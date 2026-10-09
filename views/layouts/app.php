<?php
$user = \App\Auth\Auth::user();
$current = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$icons = [
    '/' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
    '/reservas' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
    '/agenda' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    '/clientes' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    '/veiculos' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
    '/rastreio' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    '/orcamentos' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8"/>',
    '/produtos' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12M3.3 7l8.7 5 8.7-5"/>',
    '/promocoes-app' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    '/fotos' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    '/financeiro' => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
    '/relatorios' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
    '/documentos' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
    '/integracoes' => '<path d="M12 22v-5M9 8V2M15 8V2M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8z"/>',
    '/usuarios' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2"/>',
    '/configuracoes' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
];
$icon = static function (string $name) use ($icons): string {
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
};
$indexed = [];
foreach (nav_items() as $item) {
    $indexed[$item[0]] = $item;
}
$groups = [
    'Operação' => ['/', '/reservas', '/agenda', '/clientes', '/veiculos', '/rastreio'],
    'Comercial' => ['/orcamentos', '/produtos', '/promocoes-app', '/fotos'],
    'Gestão' => ['/financeiro', '/relatorios', '/documentos'],
    'Sistema' => ['/integracoes', '/usuarios', '/configuracoes'],
];
$headingClass = preg_match('/^(DX|ORC)-/', (string) ($title ?? '')) ? 'code' : '';
?>
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
<div class="shell">
    <aside class="sidebar">
        <div class="brand"><strong>DRIVE XPERIENCE</strong><span>Gestão operacional</span></div>
        <nav class="nav">
            <?php foreach ($groups as $group => $hrefs): ?>
                <?php
                $visible = array_values(array_filter($hrefs, static fn (string $href): bool => isset($indexed[$href]) && \App\Auth\Auth::can($indexed[$href][2])));
                if ($visible === []) {
                    continue;
                }
                ?>
                <div class="nav-label"><?= e($group) ?></div>
                <?php foreach ($visible as $href): ?>
                    <?php [$href, $label] = $indexed[$href]; ?>
                    <a class="<?= $current === $href || ($href !== '/' && str_starts_with($current, $href)) ? 'active' : '' ?>" href="<?= e($href) ?>"><?= $icon($href) ?><?= e($label) ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <strong><?= e($user['name'] ?? '') ?></strong>
            <small><?= e($user['role_name'] ?? '') ?></small>
            <form method="post" action="/sair"><?= csrf_field() ?><button class="ghost" type="submit"><?= $icon('sair') ?>Sair</button></form>
        </div>
    </aside>
    <section class="workspace">
        <?php if (demo_enabled()): ?><div class="banner">Dados de demonstração carregados. Eles não representam clientes reais.</div><?php endif; ?>
        <div class="frame">
            <header class="page-head">
                <button class="menu-button ghost" type="button" data-menu><?= $icon('menu') ?>Menu</button>
                <h1 class="<?= e($headingClass) ?>"><?= e($title) ?></h1>
                <div class="page-tools"></div>
            </header>
            <main class="content">
                <?php if (!empty($flash)): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
                <?= $content ?>
            </main>
        </div>
    </section>
</div>
<div class="message-layer" data-message hidden>
    <button type="button" class="message-backdrop" data-message-close aria-label="Fechar"></button>
    <form class="message-panel" data-message-form>
        <?= csrf_field() ?>
        <header class="message-head">
            <div>
                <h2>Comunicar</h2>
                <p data-message-who></p>
            </div>
            <button class="ghost" type="button" data-message-close>Fechar</button>
        </header>
        <p class="field-help" data-message-note></p>
        <div class="field"><label for="message-opening">Mensagem inicial</label><textarea id="message-opening" name="opening" data-message-opening></textarea></div>
        <div class="message-vars">
            <p>Variáveis da reserva</p>
            <div data-message-vars></div>
        </div>
        <div class="field"><label for="message-closing">Mensagem final</label><textarea id="message-closing" name="closing" data-message-closing></textarea></div>
        <label class="check"><input type="checkbox" data-message-buttons-toggle> Incluir botões no WhatsApp</label>
        <div class="message-buttons" data-message-buttons hidden>
            <p class="field-help">Resposta e link na mesma mensagem podem não aparecer no WhatsApp Web.</p>
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="message-button-row">
                    <input data-button-label maxlength="20" placeholder="Texto" aria-label="Texto do botão <?= $i ?>">
                    <select data-button-kind aria-label="Tipo do botão <?= $i ?>"><option value="resposta">Resposta</option><option value="link">Link</option></select>
                    <input data-button-value placeholder="Identificador ou link" aria-label="Valor do botão <?= $i ?>">
                </div>
            <?php endfor; ?>
        </div>
        <div class="message-preview">
            <p>Pré-visualização</p>
            <pre data-message-preview></pre>
        </div>
        <p class="field-help" data-message-status hidden></p>
        <div class="user-actions">
            <button type="submit" data-message-send>Enviar</button>
            <button class="ghost" type="button" data-message-close>Cancelar</button>
        </div>
    </form>
</div>
<script src="/assets/js/app.js?v=<?= (int) filemtime(base_path('public/assets/js/app.js')) ?>"></script>
</body>
</html>
