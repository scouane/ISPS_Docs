<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/gerar_despacho_pdf.php';

$user = requireRole(['SECRETARIA', 'ADMIN']);
$db = getDB();

$requestId = $_GET['id'] ?? null;

if ($requestId) {
    // 1. Gerar o documento PDF oficial codificado em normas ISPS
    $pdfPath = gerarDespachoPDF($requestId);

    if ($pdfPath) {
        // 2. Registar o caminho do ficheiro PDF final no despacho
        $stmtUpdate = $db->prepare("
            UPDATE dispatches 
            SET generated_dispatch_pdf = ? 
            WHERE request_id = ?
        ");
        $stmtUpdate->execute([$pdfPath, $requestId]);

        // 3. Notificar o estudante da disponibilidade do documento
        $stmtGetStudent = $db->prepare("SELECT student_id FROM requests WHERE id = ?");
        $stmtGetStudent->execute([$requestId]);
        $studentId = $stmtGetStudent->fetchColumn();

        $stmtNotify = $db->prepare("
            INSERT INTO notifications (user_id, message, created_at)
            SELECT user_id, 'O seu requerimento foi despachado! Já pode descarregar o PDF no seu painel.', NOW()
            FROM students WHERE id = ?
        ");
        $stmtNotify->execute([$studentId]);

        header('Location: dashboard.php?status=concluido');
        exit;
    }
}
?>