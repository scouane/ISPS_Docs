<?php $u = currentUser(); ?>
<nav class="navbar">
    <span class="brand">ISPS Flow</span>
    <?php if ($u && $u['role_code'] === 'ESTUDANTE'): ?>
        <a href="/public/estudante/dashboard.php">Os Meus Processos</a>
        <a href="/public/estudante/novo_requerimento.php">Novo Requerimento</a>
    <?php elseif ($u && $u['role_code'] === 'SECRETARIA'): ?>
        <a href="/public/secretaria/dashboard.php">Triagem</a>
    <?php elseif ($u && $u['role_code'] === 'DIRECTOR'): ?>
        <a href="/public/director/dashboard.php">Processos para Análise</a>
    <?php elseif ($u && $u['role_code'] === 'ADMIN'): ?>
        <a href="/public/admin/dashboard.php">Painel</a>
        <a href="/public/admin/users.php">Utilizadores</a>
        <a href="/public/admin/request_types.php">Tipos de Pedido</a>
        <a href="/public/admin/destinations.php">Destinos</a>
        <a href="/public/admin/routing_rules.php">Regras de Encaminhamento</a>
    <?php endif; ?>
    <?php if ($u): ?>
        <span class="nav-user"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role_name']) ?>)</span>
        <a href="/public/logout.php">Sair</a>
    <?php endif; ?>
</nav>
