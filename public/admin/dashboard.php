<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Garantir que apenas administradores acedem (espera um array)
requireRole(['ADMIN']);

$db = getDB();

// Métricas do Sistema ajustadas para as tabelas do schema.sql
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
$totalRequests = $db->query("SELECT COUNT(*) FROM requests")->fetchColumn() ?: 0;
$pendingRequests = $db->query("SELECT COUNT(*) FROM requests WHERE current_status IN ('SUBMETIDO', 'EM_TRIAGEM', 'ENCAMINHADO', 'EM_ANALISE')")->fetchColumn() ?: 0;
$completedRequests = $db->query("SELECT COUNT(*) FROM requests WHERE current_status = 'DESPACHADO'")->fetchColumn() ?: 0;

// Últimos Requerimentos Submetidos
$recentStmt = $db->query("
    SELECT 
        r.id, 
        r.protocol_number, 
        r.submitted_at, 
        r.current_status, 
        u.full_name as student_name, 
        t.name as request_type
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN request_types t ON r.request_type_id = t.id
    ORDER BY r.submitted_at DESC
    LIMIT 5
");
$recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Painel do Administrador</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="dashboard-body">
    <!-- Navegação Superior -->
    <header class="top-navbar">
        <div class="nav-brand">
            <img src="../../assets/img/logo_isps.png" alt="ISPS" class="nav-logo">
            <span>ISPS DOCS <small>| Administração</small></span>
        </div>
        <div class="nav-user">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['full_name'] ?? 'Administrador') ?></span>
            <a href="../logout.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="dashboard-layout">
        <!-- Sidebar de Navegação -->
        <aside class="sidebar">
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">📊 Visão Geral</a>
                <a href="users.php" class="menu-item">👥 Utilizadores</a>
                <a href="request_types.php" class="menu-item">📄 Tipos de Documentos</a>
                <a href="destinations.php" class="menu-item">🏛️ Destinos & Unidades</a>
                <a href="routing_rules.php" class="menu-item">🔄 Regras de Tramitação</a>
            </nav>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="main-content">
            <div class="page-header">
                <h2>Painel de Controlo</h2>
                <p>Gestão global de utilizadores, fluxos de tramitação e estatísticas do sistema.</p>
            </div>

            <!-- Cartões de KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon users">👥</div>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $totalUsers ?></span>
                        <span class="kpi-label">Utilizadores Ativos</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon total">📂</div>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $totalRequests ?></span>
                        <span class="kpi-label">Total de Processos</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon pending">⏳</div>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $pendingRequests ?></span>
                        <span class="kpi-label">Em Tramitação</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon completed">✅</div>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $completedRequests ?></span>
                        <span class="kpi-label">Despachados / Concluídos</span>
                    </div>
                </div>
            </div>

            <!-- Secção de Tabelas e Ações Rápidas -->
            <div class="content-grid">
                <!-- Tabela de Processos Recentes -->
                <div class="card-panel">
                    <div class="panel-header">
                        <h3>Actividade Recente</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Requerente</th>
                                    <th>Tipo</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRequests)): ?>
                                    <tr>
                                        <td colspan="5" class="empty-table">Nenhum processo registado até ao momento.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentRequests as $req): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($req['protocol_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($req['student_name']) ?></td>
                                            <td><?= htmlspecialchars($req['request_type']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                                            <td>
                                                <span class="status-badge <?= strtolower($req['current_status']) ?>">
                                                    <?= htmlspecialchars($req['current_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Painel de Ações Rápidas -->
                <div class="card-panel">
                    <div class="panel-header">
                        <h3>Ações Rápidas</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="users.php?action=new" class="quick-btn">
                            <span>➕ Criar Novo Utilizador</span>
                        </a>
                        <a href="request_types.php?action=new" class="quick-btn">
                            <span>📝 Adicionar Tipo de Documento</span>
                        </a>
                        <a href="routing_rules.php" class="quick-btn">
                            <span>⚙️ Configurar Rota de Fluxo</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>