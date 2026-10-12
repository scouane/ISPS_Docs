<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['ESTUDANTE', 'ADMIN']);
$db = getDB();

$studentId = $user['student_id'] ?? null;

$requests = [];
$totalSubmetido = 0;
$totalTramitacao = 0;
$totalDespachados = 0;

if ($studentId) {
    // Consulta para listar os requerimentos do estudante com o estado e a decisão final
    $query = "
        SELECT 
            r.id,
            r.protocol_number,
            r.subject,
            r.submitted_at,
            r.current_status,
            t.name AS request_type,
            d.label AS current_destination,
            disp.decision AS final_decision
        FROM requests r
        LEFT JOIN request_types t ON r.request_type_id = t.id
        LEFT JOIN destinations d ON r.current_destination_id = d.id
        LEFT JOIN (
            SELECT request_id, decision 
            FROM dispatches 
            WHERE id IN (SELECT MAX(id) FROM dispatches GROUP BY request_id)
        ) disp ON disp.request_id = r.id
        WHERE r.student_id = ?
        ORDER BY r.submitted_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$studentId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contadores para os cartões do topo
    foreach ($requests as $req) {
        $totalSubmetido++;
        if (in_array($req['current_status'], ['SUBMETIDO', 'ENCAMINHADO', 'EM_ANALISE'])) {
            $totalTramitacao++;
        } elseif (in_array($req['current_status'], ['DESPACHADO', 'CONCLUIDO'])) {
            $totalDespachados++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Portal do Estudante</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Estilo Corporativo Unificado (Igual à Secretaria) -->
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
        .sidebar-menu li a, .sidebar-menu .menu-item { display: block; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar-menu .menu-item:hover, .sidebar-menu .menu-item.active { background-color: var(--sidebar-hover); color: #fff; border-left-color: #38bdf8; }

        /* Área Principal */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navbar */
        .top-navbar { height: 60px; background-color: var(--topbar-bg); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; }
        .user-info { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; }
        .user-info a { color: #f87171; text-decoration: none; font-weight: 600; }

        /* Conteúdo do Dashboard */
        .dashboard-content { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Widgets Superiores */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-card h3 { font-size: 1.5rem; font-weight: bold; color: var(--text-main); margin-bottom: 2px; }
        .stat-card small { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; font-weight: 600; }

        /* Tabela de Dados Enterprise */
        .table-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
        .table-header-bar { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .table-header-bar h3 { font-size: 1rem; color: var(--text-main); }
        
        table.enterprise-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.enterprise-table th, table.enterprise-table td { padding: 12px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
        table.enterprise-table th { background: #f8fafc; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        table.enterprise-table tr:hover { background: #f1f5f9; }
        
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.submetido { background: #e0f2fe; color: #0369a1; }
        .status-badge.em_analise { background: #fef9c3; color: #854d0e; }
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
            <li><a href="dashboard.php" class="menu-item active">📊 Meus Processos</a></li>
            <li><a href="novo_requerimento.php" class="menu-item">➕ Novo Requerimento</a></li>
            <li><a href="../logout.php" style="color: #f87171;">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="font-weight: 600; font-size: 0.95rem;">Portal Académico do Estudante</div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($user['full_name'] ?? 'Estudante') ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo do Dashboard -->
        <div class="dashboard-content">
            
            <div style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div>
                    <h2 style="font-size: 1.3rem; color: var(--text-main);">Painel do Estudante</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Acompanhe o estado dos seus requerimentos e submeta novos pedidos.</p>
                </div>
                <a href="novo_requerimento.php" style="background: #0b5394; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">+ Submeter Requerimento</a>
            </div>

            <!-- Cartões de Resumo -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span style="font-size: 2rem;">📁</span>
                    <div>
                        <h3><?= $totalSubmetido ?></h3>
                        <small>Total Submetido</small>
                    </div>
                </div>
                <div class="stat-card">
                    <span style="font-size: 2rem;">⏳</span>
                    <div>
                        <h3><?= $totalTramitacao ?></h3>
                        <small>Em Tramitação</small>
                    </div>
                </div>
                <div class="stat-card">
                    <span style="font-size: 2rem;">✅</span>
                    <div>
                        <h3><?= $totalDespachados ?></h3>
                        <small>Despachados</small>
                    </div>
                </div>
            </div>

            <!-- Tabela de Processos -->
            <div class="table-panel">
                <div class="table-header-bar">
                    <h3>Os Meus Requerimentos</h3>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Histórico de solicitações</span>
                </div>

                <?php if (empty($requests)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 40px;">Ainda não submeteu nenhum requerimento.</p>
                <?php else: ?>
                    <table class="enterprise-table">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Tipo de Documento</th>
                                <th>Assunto</th>
                                <th>Localização Atual</th>
                                <th>Data de Envio</th>
                                <th>Estado</th>
                                <th>Resultado Final</th>
                                <th style="text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($req['protocol_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($req['request_type']) ?></td>
                                    <td><?= htmlspecialchars($req['subject']) ?></td>
                                    <td><?= htmlspecialchars($req['current_destination'] ?? 'Secretaria Geral') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower($req['current_status']) ?>">
                                            <?= htmlspecialchars($req['current_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($req['final_decision'] === 'DEFERIDO'): ?>
                                            <span style="color: #16a34a; font-weight: bold;">DEFERIDO</span>
                                        <?php elseif ($req['final_decision'] === 'INDEFERIDO'): ?>
                                            <span style="color: #dc2626; font-weight: bold;">INDEFERIDO</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="processo.php?id=<?= $req['id'] ?>" style="color: #0284c7; text-decoration: none; font-weight: 600;">Ver Detalhes &rarr;</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </div>
</body>
</html>