<?php
require_once __DIR__ . '/simple_pdf.php';

/**
 * Gera o PDF da Comunicação de Despacho para um processo já despachado,
 * grava o ficheiro em UPLOAD_DIR, regista-o na tabela `documents` e
 * devolve o novo document_id.
 */
function generateCommunicationPdf(PDO $pdo, int $requestId, int $dispatchId, int $uploaderUserId): int
{
    $stmt = $pdo->prepare(
        'SELECT r.protocol_number, r.subject, rt.name AS request_type_name,
                s.student_number, su.full_name AS student_name, c.name AS course_name, c.code AS course_code,
                disp.decision, disp.decision_text, disp.dispatched_at,
                du.full_name AS director_name
         FROM requests r
         JOIN request_types rt ON rt.id = r.request_type_id
         JOIN students s ON s.id = r.student_id
         JOIN users su ON su.id = s.user_id
         JOIN courses c ON c.id = s.course_id
         JOIN dispatches disp ON disp.id = :dispatch_id
         JOIN users du ON du.id = disp.dispatched_by
         WHERE r.id = :request_id'
    );
    $stmt->execute(['dispatch_id' => $dispatchId, 'request_id' => $requestId]);
    $d = $stmt->fetch();

    if (!$d) {
        throw new RuntimeException('Dados insuficientes para gerar a comunicação de despacho.');
    }

    $rotulosDecisao = [
        'DEFERIDO'      => 'DEFERIDO',
        'INDEFERIDO'    => 'INDEFERIDO',
        'PENDENTE_INFO' => 'PENDENTE DE INFORMAÇÃO ADICIONAL',
        'ENCAMINHADO'   => 'ENCAMINHADO',
    ];

    $pdf = new SimplePdf();
    $y = 790;

    $pdf->text(50, $y, 'INSTITUTO SUPERIOR POLITÉCNICO DE SONGO', 13, true); $y -= 18;
    $pdf->text(50, $y, 'Comunicação de Despacho', 12, true); $y -= 30;

    $campo = function (string $label, string $valor) use ($pdf, &$y) {
        $pdf->text(50, $y, $label, 10, true);
        $pdf->text(200, $y, $valor, 10, false);
        $y -= 20;
    };

    $campo('Nº de Solicitação / Protocolo:', $d['protocol_number']);
    $campo('Estudante:', $d['student_name'] . '  (' . $d['student_number'] . ')');
    $campo('Curso:', $d['course_code'] . ' — ' . $d['course_name']);
    $campo('Tipo de Pedido:', $d['request_type_name']);
    $campo('Assunto:', $d['subject']);
    $campo('Decisão:', $rotulosDecisao[$d['decision']] ?? $d['decision']);
    $campo('Data do Despacho:', date('d/m/Y', strtotime($d['dispatched_at'])));

    $y -= 10;
    $pdf->text(50, $y, 'Fundamentação:', 10, true); $y -= 18;
    $y = $pdf->paragraph(50, $y, $d['decision_text'], 10, false, 95, 15);

    $y -= 40;
    $pdf->text(50, $y, '_____________________________________', 10, false); $y -= 16;
    $pdf->text(50, $y, $d['director_name'], 10, false); $y -= 14;
    $pdf->text(50, $y, 'Responsável pela decisão', 9, false);

    $bytes = $pdf->render();

    $safeProtocol = preg_replace('/[^A-Za-z0-9_-]/', '_', $d['protocol_number']);
    $fileName = 'comunicacao_' . $safeProtocol . '_' . uniqid() . '.pdf';
    $originalName = 'Comunicacao_Despacho_' . $safeProtocol . '.pdf';

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    file_put_contents(UPLOAD_DIR . $fileName, $bytes);

    $stmt = $pdo->prepare(
        'INSERT INTO documents (request_id, doc_type, file_path, original_filename, mime_type, file_size, uploaded_by)
         VALUES (:rid, :type, :path, :orig, :mime, :size, :uid)'
    );
    $stmt->execute([
        'rid'  => $requestId,
        'type' => 'COMUNICACAO',
        'path' => $fileName,
        'orig' => $originalName,
        'mime' => 'application/pdf',
        'size' => strlen($bytes),
        'uid'  => $uploaderUserId,
    ]);

    return (int) $pdo->lastInsertId();
}
