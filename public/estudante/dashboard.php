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
</head>
<body class="dashboard-body">
    <header class="top-navbar">
        <div class="nav-brand">
            <img src="../../assets/img/logo_isps.png" alt="ISPS" class="nav-logo">
            <span>ISPS DOCS <small>| Portal do Estudante</small></span>
        </div>
        <div class="nav-user">
            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Estudante') ?></span>
            <a href="../logout.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <span class="icon">📊</span> Meus Processos
                </a>
                <a href="novo_requerimento.php" class="menu-item">
                    <span class="icon">➕</span> Novo Requerimento
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header flex-between">
                <div>
                    <h2>Painel do Estudante</h2>
                    <p>Acompanhe o estado dos seus requerimentos e submeta novos pedidos.</p>
                </div>
                <a href="novo_requerimento.php" class="btn-primary" style="padding: 10px 18px; text-decoration: none;">Submeter Requerimento</a>
            </div>

            <!-- Cartões de Resumo -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px;">
                <div class="card-panel" style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 2rem;">📁</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem;"><?= $totalSubmetido ?></h3>
                        <small style="color: #64748b;">Total Submetido</small>
                    </div>
                </div>
                <div class="card-panel" style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 2rem;">⏳</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem;"><?= $totalTramitacao ?></h3>
                        <small style="color: #64748b;">Em Tramitação</small>
                    </div>
                </div>
                <div class="card-panel" style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 2rem;">✅</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem;"><?= $totalDespachados ?></h3>
                        <small style="color: #64748b;">Despachados</small>
                    </div>
                </div>
            </div>

            <!-- Tabela de Processos -->
            <div class="card-panel">
                <h3>Os Meus Requerimentos</h3>

                <?php if (empty($requests)): ?>
                    <p class="empty-table" style="text-align: center; color: #64748b; padding: 20px;">
                        Ainda não submeteu nenhum requerimento.
                    </p>
                <?php else: ?>
                    <table class="data-table" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 10px;">Protocolo</th>
                                <th style="padding: 10px;">Tipo de Documento</th>
                                <th style="padding: 10px;">Assunto</th>
                                <th style="padding: 10px;">Localização Atual</th>
                                <th style="padding: 10px;">Data de Envio</th>
                                <th style="padding: 10px;">Estado</th>
                                <th style="padding: 10px;">Resultado Final</th>
                                <th style="padding: 10px; text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px; font-weight: bold;"><?= htmlspecialchars($req['protocol_number']) ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($req['request_type']) ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($req['subject']) ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($req['current_destination'] ?? 'Secretaria Geral') ?></td>
                                    <td style="padding: 10px;"><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                                    <td style="padding: 10px;">
                                        <span class="status-badge <?= strtolower($req['current_status']) ?>">
                                            <?= htmlspecialchars($req['current_status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; font-weight: bold;">
                                        <?php if ($req['final_decision'] === 'DEFERIDO'): ?>
                                            <span style="color: #16a34a;">DEFERIDO</span>
                                        <?php elseif ($req['final_decision'] === 'INDEFERIDO'): ?>
                                            <span style="color: #dc2626;">INDEFERIDO</span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-weight: normal;">Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <a href="processo.php?id=<?= $req['id'] ?>" class="btn-action" style="padding: 6px 12px; background: #0b5394; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem;">
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>