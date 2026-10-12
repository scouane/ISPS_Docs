<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Garantir que apenas administradores acedem
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
    <!-- Estilo Corporativo Unificado -->
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --topbar-bg: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary-color: #0b5394;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--main-bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }

        /* Barra Lateral Escura */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #cbd5e1; display: flex; flex-direction: column; border-right: 1px solid #334155; }
        .sidebar-brand { padding: 20px; font-size: 1.1rem; font-weight: bold; color: #fff; background: var(--topbar-bg); display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { list-style: none; padding: 15px 0; overflow-y: auto; flex: 1; }
        .sidebar-menu .menu-item { display: block; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar-menu .menu-item:hover, .sidebar-menu .menu-item.active { background-color: var(--sidebar-hover); color: #fff; border-left-color: #38bdf8; }

        /* Área Principal */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navbar */
        .top-navbar { height: 60px; background-color: var(--topbar-bg); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; }
        .user-info { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; }
        .user-info a { color: #f87171; text-decoration: none; font-weight: 600; }

        /* Conteúdo do Dashboard */
        .dashboard-content { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Widgets Superiores (KPIs) */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .kpi-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .kpi-data { display: flex; flex-direction: column; }
        .kpi-number { font-size: 1.5rem; font-weight: bold; color: var(--text-main); }
        .kpi-label { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; font-weight: 600; }

        /* Grid de Conteúdo */
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
        .card-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
        .panel-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); background: #fff; }
        .panel-header h3 { font-size: 1rem; color: var(--text-main); }

        /* Tabela Enterprise */
        table.enterprise-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.enterprise-table th, table.enterprise-table td { padding: 12px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
        table.enterprise-table th { background: #f8fafc; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        table.enterprise-table tr:hover { background: #f1f5f9; }

        /* Ações Rápidas */
        .quick-actions { display: flex; flex-direction: column; gap: 10px; padding: 20px; }
        .quick-btn { display: block; padding: 12px 15px; background: #f1f5f9; color: var(--text-main); text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: 0.2s; border: 1px solid var(--border-color); }
        .quick-btn:hover { background: #e2e8f0; color: var(--primary-color); }
        
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.submetido { background: #e0f2fe; color: #0369a1; }
        .status-badge.despachado { background: #dcfce7; color: #15803d; }
    </style>
</head>
<body>

    <!-- Barra Lateral Escura -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <!-- Logotipo corrigido para o caminho exato -->
            <img src="../../assets/css/img/logotipo.png" alt="ISPS" style="width: 24px; height: 24px; object-fit: contain;">
            <span>ISPS Docs</span>
        </div>
        <ul class="sidebar-menu" style="list-style: none;">
            <li><a href="dashboard.php" class="menu-item active">📊 Visão Geral</a></li>
            <li><a href="users.php" class="menu-item">👥 Utilizadores</a></li>
            <li><a href="request_types.php" class="menu-item">📄 Tipos de Documentos</a></li>
            <li><a href="destinations.php" class="menu-item">🏛️ Destinos & Unidades</a></li>
            <li><a href="routing_rules.php" class="menu-item">🔄 Regras de Tramitação</a></li>
            <li><a href="../logout.php" class="menu-item" style="color: #f87171;">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="font-weight: 600; font-size: 0.95rem;">Painel de Administração Global</div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['full_name'] ?? 'Administrador') ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo do Dashboard -->
        <div class="dashboard-content">
            
            <div style="background: #fff; padding: 15px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <h2 style="font-size: 1.3rem; color: var(--text-main);">Painel de Controlo</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Gestão global de utilizadores, fluxos de tramitação e estatísticas do sistema.</p>
            </div>

            <!-- Cartões de KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span style="font-size: 2rem;">👥</span>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $totalUsers ?></span>
                        <span class="kpi-label">Utilizadores Ativos</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <span style="font-size: 2rem;">📂</span>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $totalRequests ?></span>
                        <span class="kpi-label">Total de Processos</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <span style="font-size: 2rem;">⏳</span>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $pendingRequests ?></span>
                        <span class="kpi-label">Em Tramitação</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <span style="font-size: 2rem;">✅</span>
                    <div class="kpi-data">
                        <span class="kpi-number"><?= $completedRequests ?></span>
                        <span class="kpi-label">Despachados</span>
                    </div>
                </div>
            </div>

            <!-- Secção de Tabelas e Ações Rápidas -->
            <div class="content-grid">
                <!-- Tabela de Processos Recentes -->
                <div class="card-panel">
                    <div class="panel-header">
                        <h3>Atividade Recente</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="enterprise-table">
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
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Nenhum processo registado até ao momento.</td>
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

        </div>
    </div>
</body>
</html>