<?php
/**
 * Serve um documento (anexo ou comunicação) verificando que o utilizador
 * autenticado tem permissão para o aceder:
 *   - Estudante: apenas documentos dos SEUS próprios processos
 *   - Secretaria / Director / Administrador: qualquer documento
 */

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /public/login.php');
    exit;
}

$user = currentUser();
$pdo = getDB();
$documentId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT doc.*, r.student_id
     FROM documents doc
     JOIN requests r ON r.id = doc.request_id
     WHERE doc.id = :id'
);
$stmt->execute(['id' => $documentId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Documento não encontrado.');
}

if ($user['role_code'] === 'ESTUDANTE' && (int) $doc['student_id'] !== (int) ($user['student_id'] ?? 0)) {
    http_response_code(403);
    die('Não tens permissão para aceder a este documento.');
}

$filePath = UPLOAD_DIR . $doc['file_path'];

if (!is_file($filePath)) {
    http_response_code(404);
    die('Ficheiro não encontrado no servidor.');
}

header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
