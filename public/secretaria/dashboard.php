<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['SECRETARIA', 'ADMIN']);
$db = getDB();

// Métricas para os gráficos em anel e cartões
$totalEntrados = $db->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$totalDeferidos = $db->query("SELECT COUNT(*) FROM requests r JOIN dispatches d ON r.id = d.request_id WHERE d.decision = 'DEFERIDO'")->fetchColumn();
$totalIndeferidos = $db->query("SELECT COUNT(*) FROM requests r JOIN dispatches d ON r.id = d.request_id WHERE d.decision = 'INDEFERIDO'")->fetchColumn();
$totalPendentes = $db->query("SELECT COUNT(*) FROM requests WHERE current_status = 'SUBMETIDO' OR current_status = 'EM_ANALISE'")->fetchColumn();

// Filtros opcionais vindos da barra lateral
$filtroStatus = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

// Construção da query com filtros dinâmicos
$sql = "
    SELECT r.id, r.protocol_number, r.subject, r.current_status, r.submitted_at, 
           u.full_name as student_name, s.student_number, rt.name as request_type_name
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN request_types rt ON r.request_type_id = rt.id
    WHERE 1=1
";
$params = [];

if ($filtroStatus) {
    $sql .= " AND r.current_status = ?";
    $params[] = $filtroStatus;
}
if ($filtroTipo) {
    $sql .= " AND rt.id = ?";
    $params[] = $filtroTipo;
}

$sql .= " ORDER BY r.submitted_at DESC LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter tipos de pedidos para o filtro lateral
$tiposPedido = $db->query("SELECT id, name FROM request_types")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Operations Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Estilo Corporativo Inspirado no VCF Operations -->
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
        .sidebar-menu li a { display: block; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background-color: var(--sidebar-hover); color: #fff; border-left-color: #38bdf8; }

        /* Área Principal */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navbar */
        .top-navbar { height: 60px; background-color: var(--topbar-bg); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; }
        .top-navbar .search-bar input { background: #1e293b; border: 1px solid #475569; color: #fff; padding: 6px 15px; border-radius: 4px; width: 300px; font-size: 0.85rem; }
        .user-info { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; }
        .user-info a { color: #f87171; text-decoration: none; font-weight: 600; }

        /* Conteúdo do Dashboard */
        .dashboard-content { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Secção Superior: Widgets / Donut Cards */
        .widgets-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .widget-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }
        .widget-title { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; align-self: flex-start; margin-bottom: 15px; }
        
        /* Gráfico em Anel (CSS Simulado) */
        .donut-chart { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; font-weight: bold; font-size: 1.2rem; color: var(--text-main); }
        .donut-chart::before { content: ""; position: absolute; width: 75px; height: 75px; background: #fff; border-radius: 50%; }
        .donut-number { position: relative; z-index: 2; }
        
        .chart-total { background: conic-gradient(#0284c7 0deg 360deg); }
        .chart-deferido { background: conic-gradient(#10b981 0deg 360deg, #e2e8f0 360deg); }
        .chart-indeferido { background: conic-gradient(#ef4444 0deg 360deg, #e2e8f0 360deg); }

        .widget-legend { margin-top: 15px; font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        /* Layout Inferior: Filtros à Esquerda e Tabela à Direita */
        .workspace-grid { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
        
        /* Painel de Filtros (Estilo VMware) */
        .filter-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; }
        .filter-panel h3 { font-size: 0.95rem; margin-bottom: 15px; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
        .filter-group { margin-bottom: 15px; }
        .filter-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.85rem; background: #fff; }
        .filter-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn-filter { flex: 1; padding: 8px; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer; font-weight: 600; }
        .btn-apply { background: #0f172a; color: #fff; }
        .btn-clear { background: #e2e8f0; color: var(--text-main); text-decoration: none; text-align: center; line-height: 32px; }

        /* Tabela de Dados (Enterprise Grid) */
        .table-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
        .table-header-bar { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .table-header-bar h3 { font-size: 1rem; color: var(--text-main); }
        
        table.enterprise-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.enterprise-table th, table.enterprise-table td { padding: 12px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
        table.enterprise-table th { background: #f8fafc; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        table.enterprise-table tr:hover { background: #f1f5f9; }
        
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.submetido { background: #e0f2fe; color: #0369a1; }
        .status-badge.despachado { background: #dcfce7; color: #15803d; }
        .status-badge.indeferido { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <!-- Barra Lateral Escura -->
    <nav class="sidebar">
        <div class="sidebar-brand">
    <img src="../../assets/css/img/logotipo.png" alt="ISPS" style="width: 28px; height: 28px; object-fit: contain;">
    <span>ISPS Docs</span>
</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">Painel Principal</a></li>
            <li><a href="../secretaria/dashboard.php">Gestão de Processos</a></li>
            <li><a href="exportar_relatorio.php" style="color: #38bdf8;">📥 Exportar Relatório XLS</a></li>
            <li><a href="../logout.php">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="search-bar">
                <input type="text" placeholder="Pesquisar por protocolo, estudante ou assunto...">
            </div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo do Dashboard -->
        <div class="dashboard-content">
            
            <!-- Widgets Superiores (Gráficos em Anel / Donut) -->
            <div class="widgets-container">
                <!-- Widget 1: Total -->
                <div class="widget-card">
                    <span class="widget-title">Total de Requerimentos</span>
                    <div class="donut-chart chart-total">
                        <span class="donut-number"><?= $totalEntrados ?></span>
                    </div>
                    <div class="widget-legend"><span class="dot" style="background: #0284c7;"></span> Registados no sistema</div>
                </div>

                <!-- Widget 2: Deferidos -->
                <div class="widget-card">
                    <span class="widget-title">Processos Deferidos</span>
                    <div class="donut-chart chart-deferido">
                        <span class="donut-number"><?= $totalDeferidos ?></span>
                    </div>
                    <div class="widget-legend"><span class="dot" style="background: #10b981;"></span> Aprovados pela Direção</div>
                </div>

                <!-- Widget 3: Pendentes / Outros -->
                <div class="widget-card">
                    <span class="widget-title">Processos Pendentes</span>
                    <div class="donut-chart chart-indeferido">
                        <span class="donut-number"><?= $totalPendentes ?></span>
                    </div>
                    <div class="widget-legend"><span class="dot" style="background: #ef4444;"></span> Aguardando tramitação</div>
                </div>
            </div>

            <!-- Workspace Inferior: Filtros + Tabela -->
            <div class="workspace-grid">
                
                <!-- Painel de Filtros Lateral -->
                <div class="filter-panel">
                    <h3>Filtros Avançados</h3>
                    <form method="GET">
                        <div class="filter-group">
                            <label for="status">Estado do Processo</label>
                            <select name="status" id="status">
                                <option value="">Todos os Estados</option>
                                <option value="SUBMETIDO" <?= $filtroStatus === 'SUBMETIDO' ? 'selected' : '' ?>>SUBMETIDO</option>
                                <option value="EM_ANALISE" <?= $filtroStatus === 'EM_ANALISE' ? 'selected' : '' ?>>EM ANÁLISE</option>
                                <option value="DESPACHADO" <?= $filtroStatus === 'DESPACHADO' ? 'selected' : '' ?>>DESPACHADO</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="tipo">Tipo de Documento</label>
                            <select name="tipo" id="tipo">
                                <option value="">Todos os Tipos</option>
                                <?php foreach ($tiposPedido as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $filtroTipo == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-filter btn-apply">Aplicar</button>
                            <a href="dashboard.php" class="btn-filter btn-clear">Limpar</a>
                        </div>
                    </form>
                </div>

                <!-- Tabela de Dados Estilo Enterprise -->
                <div class="table-panel">
                    <div class="table-header-bar">
                        <h3>Lista de Requerimentos Recentes</h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Exibindo os últimos registos</span>
                    </div>
                    <table class="enterprise-table">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Estudante</th>
                                <th>Nº Aluno</th>
                                <th>Assunto</th>
                                <th>Estado</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentRequests)): ?>
                                <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">Nenhum processo encontrado com estes filtros.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentRequests as $req): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($req['protocol_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($req['student_name']) ?></td>
                                        <td><?= htmlspecialchars($req['student_number']) ?></td>
                                        <td><?= htmlspecialchars($req['subject']) ?></td>
                                        <td><span class="status-badge <?= strtolower($req['current_status']) ?>"><?= htmlspecialchars($req['current_status']) ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                                        <td>
                                            <!-- Caminho corrigido para abrir a página de tramitação unificada -->
                                            <a href="../estudante/processo.php?id=<?= $req['id'] ?>" style="color: #0284c7; text-decoration: none; font-weight: 600;">Gerir &rarr;</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>

</body>
</html>