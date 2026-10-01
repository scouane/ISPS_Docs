<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['DIRECTOR', 'ADMIN']);
$db = getDB();

$requestId = $_GET['id'] ?? null;

if (!$requestId) {
    header('Location: dashboard.php');
    exit;
}

// 1. Carregar dados do requerimento, estudante e calcular dias restantes do SLA
$stmt = $db->prepare("
    SELECT r.*, rt.name AS type_name, s.student_code, u.name AS student_name,
           DATEDIFF(r.deadline_date, NOW()) AS days_left
    FROM requests r
    JOIN request_types rt ON r.request_type_id = rt.id
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Processar a emissão do Despacho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision']; // 'DEFERIDO' ou 'INDEFERIDO'
    $parecerText = trim($_POST['parecer_text']);
    
    // Upload / Tratamento da Assinatura Virtual
    $signaturePath = null;
    if (!empty($_FILES['signature']['name'])) {
        $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $signaturePath = 'uploads/signatures/sig_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['signature']['tmp_name'], __DIR__ . '/../../' . $signaturePath);
    }

    // Registar o despacho na BD
    $stmtDispatch = $db->prepare("
        INSERT INTO dispatches (request_id, director_id, decision, parecer_text, signature_path, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtDispatch->execute([$requestId, $user['id'], $decision, $parecerText, $signaturePath]);

    // Atualizar o estado do requerimento para DESPACHADO
    $stmtUpdate = $db->prepare("UPDATE requests SET current_status = 'DESPACHADO' WHERE id = ?");
    $stmtUpdate->execute([$requestId]);

    header('Location: dashboard.php?msg=despachado');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ISPS DOCS - Analisar e Despachar</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Análise do Destinatário & Despacho</h2>

        <!-- Alerta de Prazo Limite (SLA) -->
        <?php if ($request['days_left'] <= 3 && $request['days_left'] >= 0): ?>
            <div class="alert-warning" style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ffeeba;">
                ⚠️ <strong>ATENÇÃO AO PRAZO:</strong> Restam apenas <strong><?= $request['days_left'] ?> dia(s)</strong> para responder dentro do prazo regulamentar de 2 semanas!
            </div>
        <?php elseif ($request['days_left'] < 0): ?>
            <div class="alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                🔴 <strong>PRAZO EXCEDIDO:</strong> Este pedido ultrapassou o limite máximo de 14 dias para resposta.
            </div>
        <?php endif; ?>

        <div class="card-info" style="background: #f4f6f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p><strong>Nº de Ref. / Protocolo:</strong> <?= htmlspecialchars($request['protocol_number']) ?></p>
            <p><strong>Requerente:</strong> <?= htmlspecialchars($request['student_name']) ?> (Nº <?= $request['student_code'] ?>)</p>
            <p><strong>Assunto:</strong> <?= htmlspecialchars($request['subject']) ?></p>
        </div>

        <div class="document-body" style="border: 1px solid #ddd; padding: 15px; background: #fff; margin-bottom: 20px;">
            <h4>Exposição do Requerente:</h4>
            <p><?= nl2br(htmlspecialchars($request['description'])) ?></p>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Decisão do Despacho:</label>
                <select name="decision" required>
                    <option value="DEFERIDO">✅ DEFERIDO (Autorizado)</option>
                    <option value="INDEFERIDO">❌ INDEFERIDO (Não Autorizado)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nota Exarada / Parecer Justificado:</label>
                <textarea name="parecer_text" rows="5" required placeholder="Ex: Autorizo nos termos do regulamento académico em vigor..."></textarea>
            </div>

            <div class="form-group">
                <label>Assinatura Virtual / Carimbo Digital (PNG/JPG):</label>
                <input type="file" name="signature" accept="image/*" required>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 15px;">Emitir Despacho Oficial</button>
        </form>
    </div>
</body>
</html>