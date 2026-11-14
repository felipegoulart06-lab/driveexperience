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
<?php $creating = isset($_GET['novo']) && !$editing; ?>
<?php if (!$creating && !$editing): ?>
<div class="toolbar">
    <a class="button-link" href="/usuarios?novo=1">Novo usuário</a>
</div>
<?php endif; ?>
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
        <a class="ghost sheet-action" href="/usuarios">Cancelar</a>
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
<?php if ($creating): ?>
<form method="post" action="/usuarios" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <h2 class="full">Novo usuário</h2>
    <div class="field"><label>Nome</label><input name="name" required></div>
    <div class="field"><label>E-mail</label><input name="email" inputmode="email" autocomplete="off" required></div>
    <div class="field"><label>Perfil</label><select name="role_id" data-role-pick><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $role['slug'] === 'atendimento' ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Senha</label><input type="password" name="password" autocomplete="new-password" required></div>
    <div class="field"><label>Confirmar senha</label><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
    <p class="full field-help">Mínimo de 12 caracteres, com letras e números. Este funcionário também é o instrutor: ele entra no sistema pelo celular e conduz a experiência.</p>
    <div class="full client-actions">
        <button type="submit">Criar usuário</button>
        <a class="ghost sheet-action" href="/usuarios">Cancelar</a>
    </div>
</form>
<?php endif; ?>
<?php
$short = [
    'dashboard.view' => 'Ver o painel',
    'clients.view' => 'Ver clientes',
    'clients.manage' => 'Cadastrar e editar clientes',
    'clients.sensitive' => 'Ver CPF e dados sensíveis',
    'clients.anonymize' => 'Apagar dados pessoais',
    'reservations.view' => 'Ver reservas',
    'reservations.manage' => 'Criar e alterar reservas',
    'reservations.cancel' => 'Cancelar reservas',
    'agenda.view' => 'Ver a agenda',
    'agenda.manage' => 'Bloquear horários',
    'vehicles.view' => 'Ver veículos',
    'vehicles.manage' => 'Cadastrar e editar veículos',
    'vehicles.sensitive' => 'Ver a placa',
    'tracking.view' => 'Ver o mapa',
    'tracking.manage' => 'Cadastrar tags',
    'products.view' => 'Ver produtos',
    'products.manage' => 'Cadastrar e editar produtos',
    'photos.view' => 'Ver pacotes de fotos',
    'photos.manage' => 'Vender e alterar fotos',
    'finance.view' => 'Ver o financeiro',
    'finance.manage' => 'Lançar pagamentos e despesas',
    'documents.view' => 'Ver documentos',
    'documents.issue' => 'Emitir PDF',
    'reports.view' => 'Ver relatórios',
    'reports.finance' => 'Ver relatórios financeiros',
    'quotes.view' => 'Ver orçamentos',
    'quotes.manage' => 'Emitir orçamentos',
    'integrations.manage' => 'Configurar integrações',
    'users.manage' => 'Criar usuários',
];
?>
<?php if ($creating): ?>
<section class="card stack" data-role-perms>
    <h2>O que este perfil pode fazer</h2>
    <p class="field-help">O administrador vê e altera tudo, inclusive os dados da empresa. O perfil escolhido acima define o acesso deste funcionário.</p>
    <?php foreach ($profiles as $role): ?>
        <ul class="perm-summary" data-role-panel="<?= (int) $role['id'] ?>"<?= $role['slug'] === 'atendimento' ? '' : ' hidden' ?>>
            <?php foreach ($moduleNames as $module => $label): ?>
                <?php
                if ($module === 'configuracoes' || empty($grouped[$module])) {
                    continue;
                }
                $allowed = [];
                foreach ($grouped[$module] as $permission) {
                    if (!empty($granted[(int) $role['id']][(int) $permission['id']])) {
                        $allowed[] = $short[$permission['slug']] ?? (string) $permission['name'];
                    }
                }
                ?>
                <li>
                    <strong><?= e($label) ?></strong>
                    <span><?= $allowed === [] ? 'Sem acesso' : e(implode(' · ', $allowed)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</section>
<?php endif; ?>
