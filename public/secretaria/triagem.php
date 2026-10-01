<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['SECRETARIA', 'ADMIN']);
$db = getDB();

$requestId = $_GET['id'] ?? null;

if (!$requestId) {
    header('Location: dashboard.php');
    exit;
}

// 1. Procurar os detalhes do requerimento e do estudante
$stmt = $db->prepare("
    SELECT r.*, rt.name AS type_name, rt.target_destination, s.student_code, u.name AS student_name, u.email
    FROM requests r
    JOIN request_types rt ON r.request_type_id = rt.id
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Procurar lista de destinos/departamentos disponíveis para encaminhamento
$destinations = $db->query("SELECT * FROM destinations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Processar a decisão da Secretaria
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action_type']; // 'APROVAR' ou 'REJEITAR'

    if ($action === 'APROVAR') {
        // Gerar número de protocolo/referência automático no formato: 1.XXX/ANO (Ex: 1.1968/2026)
        $protocolNumber = '1.' . rand(1000, 9999) . '/' . date('Y');
        $destinationId = (int)$_POST['destination_id'];

        $stmtUpdate = $db->prepare("
            UPDATE requests 
            SET protocol_number = ?, 
                current_destination_id = ?, 
                current_status = 'ENCAMINHADO' 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$protocolNumber, $destinationId, $requestId]);

        header('Location: dashboard.php?msg=encaminhado');
        exit;
    } 
    
    if ($action === 'REJEITAR') {
        $rejectionReason = trim($_POST['rejection_reason']);

        $stmtReject = $db->prepare("
            UPDATE requests 
            SET current_status = 'REJEITADO', 
                rejection_reason = ? 
            WHERE id = ?
        ");
        $stmtReject->execute([$rejectionReason, $requestId]);

        header('Location: dashboard.php?msg=rejeitado');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ISPS DOCS - Triagem de Requerimento</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function toggleDecisionArea() {
            const status = document.getElementById('decision_select').value;
            document.getElementById('approve_section').style.display = (status === 'APROVAR') ? 'block' : 'none';
            document.getElementById('reject_section').style.display = (status === 'REJEITAR') ? 'block' : 'none';
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Secretaria Geral - Triagem e Protocolo</h2>
        
        <div class="card-info" style="background: #f4f6f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p><strong>Estudante:</strong> <?= htmlspecialchars($request['student_name']) ?> (Nº <?= $request['student_code'] ?>)</p>
            <p><strong>Tipo de Pedido:</strong> <?= htmlspecialchars($request['type_name']) ?></p>
            <p><strong>Destino Recomendado:</strong> <?= htmlspecialchars($request['target_destination']) ?></p>
            <p><strong>Data de Submissão:</strong> <?= date('d/m/Y H:i', strtotime($request['submitted_at'])) ?></p>
            <p><strong>Prazo Limite Resposta (SLA 14 dias):</strong> <?= date('d/m/Y', strtotime($request['deadline_date'])) ?></p>
        </div>

        <div class="document-preview" style="border: 1px solid #ccc; padding: 20px; background: #fff; margin-bottom: 20px;">
            <h3>Teor do Requerimento:</h3>
            <p><strong>Assunto:</strong> <?= htmlspecialchars($request['subject']) ?></p>
            <hr>
            <p><?= nl2br(htmlspecialchars($request['description'])) ?></p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>Avaliação do Documento:</label>
                <select name="action_type" id="decision_select" onchange="toggleDecisionArea()" required>
                    <option value="">-- Selecione a Ação --</option>
                    <option value="APROVAR">✅ Documento Correto (Gerar Protocolo e Encaminhar)</option>
                    <option value="REJEITAR">❌ Documento Incorreto/Incompleto (Devolver com Notificação)</option>
                </select>
            </div>

            <!-- Seção Aprovar/Encaminhar -->
            <div id="approve_section" style="display:none; margin-top: 15px;">
                <div class="form-group">
                    <label>Encaminhar para o Destinatário/Divisão:</label>
                    <select name="destination_id">
                        <?php foreach ($destinations as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Seção Rejeitar -->
            <div id="reject_section" style="display:none; margin-top: 15px;">
                <div class="form-group">
                    <label>Motivo da Rejeição (Notificação para o Estudante):</label>
                    <textarea name="rejection_reason" rows="4" placeholder="Ex: Faltou anexar a cópia do documento de identificação ou o formulário está incompleto."></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 20px;">Processar Decisão</button>
        </form>
    </div>
</body>
</html>