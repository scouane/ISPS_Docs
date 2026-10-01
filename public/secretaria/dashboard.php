<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireRole(['SECRETARIA', 'ADMIN']);

$db = getDB();

// Registos pendentes de triagem/encaminhamento
$stmt = $db->query("
    SELECT 
        r.id,
        r.protocol_number,
        r.subject,
        r.submitted_at,
        r.current_status,
        u.full_name AS student_name,
        t.name AS request_type
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN request_types t ON r.request_type_id = t.id
    ORDER BY r.submitted_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Secretaria Geral</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="dashboard-body">
    <header class="top-navbar">
        <div class="nav-brand">
            <img src="../../assets/img/logo_isps.png" alt="ISPS" class="nav-logo">
            <span>ISPS DOCS <small>| Secretaria Geral</small></span>
        </div>
        <div class="nav-user">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_nome'] ?? 'Secretaria') ?></span>
            <a href="../logout.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">📋 Triagem & Tramitação</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h2>Gestão de Tramitação de Documentos</h2>
                <p>Receba, valide e encaminhe os requerimentos dos estudantes para as respetivas direções.</p>
            </div>

            <div class="card-panel">
                <div class="panel-header">
                    <h3>Processos de Entrada</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Requerente</th>
                                <th>Tipo de Documento</th>
                                <th>Data de Submissão</th>
                                <th>Estado Atual</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="6" class="empty-table">Sem processos registados na Secretaria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
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
                                        <td>
                                            <a href="../estudante/processo.php?id=<?= $req['id'] ?>" class="btn-sm-outline">Analisar & Tratar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>