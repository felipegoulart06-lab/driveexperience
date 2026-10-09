<?php
$editingId = (int) ($_GET['editar'] ?? 0);
$showPassword = $editingId > 0 && (string) ($_GET['senha'] ?? '') === '1';
$editing = null;
foreach ($users as $user) {
    if ((int) $user['id'] === $editingId) {
        $editing = $user;
    }
}
$profiles = array_values(array_filter($roles, static fn (array $role): bool => $role['slug'] !== 'administrador'));
$selected = (string) ($_GET['perfil'] ?? '');
$currentRole = $profiles[0] ?? null;
foreach ($profiles as $role) {
    if ($role['slug'] === $selected) {
        $currentRole = $role;
    }
}
$granted = [];
foreach ($grants as $grant) {
    $granted[(int) $grant['role_id']][(int) $grant['permission_id']] = true;
}
$moduleNames = [
    'painel' => 'Painel',
    'clientes' => 'Clientes',
    'reservas' => 'Reservas',
    'agenda' => 'Agenda',
    'veiculos' => 'Veículos',
    'rastreio' => 'Rastreio',
    'produtos' => 'Produtos',
    'fotos' => 'Fotos',
    'orcamentos' => 'Orçamentos',
    'financeiro' => 'Financeiro',
    'documentos' => 'Documentos',
    'relatorios' => 'Relatórios',
    'integracoes' => 'Integrações',
    'usuarios' => 'Usuários',
    'configuracoes' => 'Configurações',
];
$grouped = [];
foreach ($permissions as $permission) {
    $grouped[(string) $permission['module']][] = $permission;
}
?>
<article class="card table-wrap">
    <table class="users-table">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= e($user['name']) ?><?php if ((int) $user['is_demo']): ?> <span class="badge st-novo">Demonstração</span><?php endif; ?></td>
                <td><?= e($user['email']) ?></td>
                <td><?= e($user['role_name']) ?></td>
                <td><span class="badge <?= (int) $user['active'] ? 'st-disponivel' : 'st-indisponivel' ?>"><?= (int) $user['active'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td class="row-action"><?php if ($editing && (int) $editing['id'] === (int) $user['id']): ?><a class="ghost" href="/usuarios">Fechar</a><?php else: ?><a class="ghost" href="/usuarios?editar=<?= (int) $user['id'] ?>">Editar</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</article>
<?php if ($editing): ?>
<form method="post" action="/usuarios/<?= (int) $editing['id'] ?>" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="active" value="0">
    <h2 class="full">Editar <?= e($editing['name']) ?></h2>
    <div class="field"><label>Nome</label><input name="name" value="<?= e($editing['name']) ?>" required></div>
    <div class="field"><label>E-mail</label><input name="email" value="<?= e($editing['email']) ?>" inputmode="email" autocomplete="off" required></div>
    <div class="field"><label>Perfil</label>
        <select name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= (int) $editing['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select>
    </div>
    <label class="check"><input type="checkbox" name="active" value="1" <?= (int) $editing['active'] ? 'checked' : '' ?>> Ativo</label>
    <div class="full user-actions">
        <button type="submit">Salvar dados</button>
        <?php if (!$showPassword): ?><a class="ghost sheet-action" href="/usuarios?editar=<?= (int) $editing['id'] ?>&senha=1">Trocar senha</a><?php endif; ?>
    </div>
</form>
<?php if ($showPassword): ?>
<form method="post" action="/usuarios/<?= (int) $editing['id'] ?>/senha" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <h2 class="full">Nova senha de <?= e($editing['name']) ?></h2>
    <p class="full field-help">A senha só muda neste formulário. Ela precisa ter 12 caracteres, com letras e números, e a confirmação igual.</p>
    <div class="field"><label>Nova senha</label><input type="password" name="password" autocomplete="new-password" required></div>
    <div class="field"><label>Confirmar senha</label><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
    <div class="full user-actions">
        <button type="submit" data-confirm="Trocar a senha de <?= e($editing['name']) ?>?">Alterar senha</button>
        <a class="ghost sheet-action" href="/usuarios?editar=<?= (int) $editing['id'] ?>">Cancelar</a>
    </div>
</form>
<?php endif; ?>
<?php endif; ?>
<form method="post" action="/usuarios" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <h2 class="full">Novo usuário</h2>
    <div class="field"><label>Nome</label><input name="name" required></div>
    <div class="field"><label>E-mail</label><input name="email" inputmode="email" autocomplete="off" required></div>
    <div class="field"><label>Perfil</label><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $role['slug'] === 'atendimento' ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Senha</label><input type="password" name="password" autocomplete="new-password" required></div>
    <div class="field"><label>Confirmar senha</label><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
    <p class="full field-help">Mínimo de 12 caracteres, com letras e números.</p>
    <div class="full"><button type="submit">Criar usuário</button></div>
</form>
<?php if ($currentRole): ?>
<section class="card stack">
    <h2>Permissões do perfil</h2>
    <p class="field-help">O administrador mantém acesso completo e não aparece aqui. Salve um perfil por vez.</p>
    <div class="role-tabs">
        <?php foreach ($profiles as $role): ?>
            <a class="<?= $role['slug'] === $currentRole['slug'] ? 'is-active' : '' ?>" href="/usuarios?perfil=<?= e($role['slug']) ?>"><?= e($role['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <p class="muted"><?= e((string) $currentRole['description']) ?></p>
    <form method="post" action="/usuarios/perfis/<?= (int) $currentRole['id'] ?>">
        <?= csrf_field() ?>
        <?php foreach ($moduleNames as $module => $label): ?>
            <?php if (empty($grouped[$module])) continue; ?>
            <div class="perm-group">
                <h3><?= e($label) ?></h3>
                <div class="checks">
                    <?php foreach ($grouped[$module] as $permission): ?>
                        <label class="check"><input type="checkbox" name="permissions[]" value="<?= e($permission['slug']) ?>" <?= !empty($granted[(int) $currentRole['id']][(int) $permission['id']]) ? 'checked' : '' ?>> <?= e($permission['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($grouped as $module => $items): ?>
            <?php if (isset($moduleNames[$module])) continue; ?>
            <div class="perm-group">
                <h3><?= e((string) $module) ?></h3>
                <div class="checks">
                    <?php foreach ($items as $permission): ?>
                        <label class="check"><input type="checkbox" name="permissions[]" value="<?= e($permission['slug']) ?>" <?= !empty($granted[(int) $currentRole['id']][(int) $permission['id']]) ? 'checked' : '' ?>> <?= e($permission['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <button type="submit">Salvar permissões de <?= e($currentRole['name']) ?></button>
    </form>
</section>
<?php endif; ?>
