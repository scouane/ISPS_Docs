<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['ESTUDANTE', 'ADMIN']);
$db = getDB();

// Procurar o ID do estudante associado ao utilizador com sessão iniciada
$stmtStudent = $db->prepare("SELECT id FROM students WHERE user_id = ?");
$stmtStudent->execute([$user['id']]);
$studentId = $stmtStudent->fetchColumn();

// Listar todos os requerimentos submetidos pelo estudante
$stmtRequests = $db->prepare("
    SELECT r.*, rt.name AS type_name, d.decision, d.generated_dispatch_pdf
    FROM requests r
    JOIN request_types rt ON r.request_type_id = rt.id
    LEFT JOIN dispatches d ON d.request_id = r.id
    WHERE r.student_id = ?
    ORDER BY r.submitted_at DESC
");
$stmtRequests->execute([$studentId]);
$requests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ISPS DOCS - Meus Requerimentos</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85em; }
        .badge-submetido { background: #e2e3e5; color: #383d41; }
        .badge-encaminhado { background: #cce5ff; color: #004085; }
        .badge-despachado { background: #d4edda; color: #155724; }
        .badge-rejeitado { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Os Meus Requerimentos</h2>
            <a href="novo_requerimento.php" class="btn-primary">+ Criar Novo Pedido</a>
        </div>

        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f4f6f9;">
                    <th>Nº Ref.</th>
                    <th>Tipo de Pedido</th>
                    <th>Assunto</th>
                    <th>Data Submissão</th>
                    <th>Prazo Resposta</th>
                    <th>Estado</th>
                    <th>Ações / Documento</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Nenhum requerimento submetido até ao momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= $req['protocol_number'] ? htmlspecialchars($req['protocol_number']) : '<em>Em atribuição</em>' ?></td>
                            <td><?= htmlspecialchars($req['type_name']) ?></td>
                            <td><?= htmlspecialchars($req['subject']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($req['submitted_at'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($req['deadline_date'])) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($req['current_status']) ?>">
                                    <?= htmlspecialchars($req['current_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($req['current_status'] === 'DESPACHADO'): ?>
                                    <a href="../../<?= htmlspecialchars($req['generated_dispatch_pdf']) ?>" target="_blank" class="btn-sm btn-success">
                                        📥 Baixar Despacho (PDF)
                                    </a>
                                <?php elseif ($req['current_status'] === 'REJEITADO'): ?>
                                    <button onclick="alert('Motivo da Rejeição: <?= addslashes($req['rejection_reason']) ?>')" class="btn-sm btn-danger">
                                        ⚠️ Ver Motivo
                                    </button>
                                <?php else: ?>
                                    <span style="color: #6c757d;">Em processamento</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>