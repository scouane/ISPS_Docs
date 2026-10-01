<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Garante acesso apenas a DIRECTOR e ADMIN
$user = requireRole(['DIRECTOR', 'ADMIN']);

$db = getDB();

// Procurar requerimentos que necessitam de despacho/análise da Direção
$stmt = $db->query("
    SELECT 
        r.id,
        r.protocol_number,
        r.subject,
        r.submitted_at,
        r.current_status,
        u.full_name AS student_name,
        t.name AS request_type,
        d.label AS destination_label
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN request_types t ON r.request_type_id = t.id
    LEFT JOIN destinations d ON r.current_destination_id = d.id
    WHERE r.current_status IN ('SUBMETIDO', 'ENCAMINHADO', 'EM_ANALISE')
    ORDER BY r.submitted_at DESC
");
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Gabinete de Direção</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="dashboard-body">
    <header class="top-navbar">
        <div class="nav-brand">
            <img src="../../assets/img/logo_isps.png" alt="ISPS" class="nav-logo">
            <span>ISPS DOCS <small>| Gabinete de Direção</small></span>
        </div>
        <div class="nav-user">
            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Director') ?></span>
            <a href="../logout.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <span class="icon">⚖️</span> Processos para Despacho
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h2>Despacho de Requerimentos</h2>
                <p>Análise decisória e emissão de despachos institucionais.</p>
            </div>

            <div class="card-panel">
                <h3>Aguardam Decisão</h3>

                <?php if (empty($pendingRequests)): ?>
                    <p class="empty-table" style="text-align: center; color: #64748b; padding: 20px;">
                        Não existem processos pendentes de despacho neste momento.
                    </p>
                <?php else: ?>
                    <table class="data-table" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 10px;">Protocolo</th>
                                <th style="padding: 10px;">Requerente</th>
                                <th style="padding: 10px;">Tipo de Documento</th>
                                <th style="padding: 10px;">Data</th>
                                <th style="padding: 10px;">Estado</th>
                                <th style="padding: 10px; text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $req): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px; font-weight: bold;"><?= htmlspecialchars($req['protocol_number']) ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($req['student_name']) ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($req['request_type']) ?></td>
                                    <td style="padding: 10px;"><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                                    <td style="padding: 10px;">
                                        <span class="status-badge <?= strtolower($req['current_status']) ?>">
                                            <?= htmlspecialchars($req['current_status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <a href="../estudante/processo.php?id=<?= $req['id'] ?>" class="btn-action" style="padding: 6px 12px; background: #0b5394; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem;">
                                            Analisar & Despachar
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