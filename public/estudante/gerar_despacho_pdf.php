<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/fpdf.php';
require_once __DIR__ . '/../../lib/qrlib.php';

$user = requireRole(['ESTUDANTE', 'SECRETARIA', 'DIRECTOR', 'ADMIN']);
$processId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = getDB();

// 1. Buscar os dados completos do requerimento, estudante e curso
$stmt = $db->prepare("
    SELECT r.*, rt.name as request_type_name, 
           s.student_number, c.name as course_name, 
           u.full_name as student_name, u.email
    FROM requests r
    JOIN request_types rt ON r.request_type_id = rt.id
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE r.id = ?
");
$stmt->execute([$processId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    die("Requerimento não encontrado.");
}

// 2. Validação de Segurança para Estudante
if ($user['role_code'] === 'ESTUDANTE') {
    $stmtStudentCheck = $db->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmtStudentCheck->execute([$user['id']]);
    $studentRecord = $stmtStudentCheck->fetch(PDO::FETCH_ASSOC);

    if (!$studentRecord || $req['student_id'] != $studentRecord['id']) {
        die("Acesso não autorizado.");
    }
}

// 3. Buscar o despacho mais recente emitido pelo Director
$stmtDisp = $db->prepare("
    SELECT decision, decision_text, dispatched_at 
    FROM dispatches 
    WHERE request_id = ? 
    ORDER BY dispatched_at DESC 
    LIMIT 1
");
$stmtDisp->execute([$processId]);
$dispatch = $stmtDisp->fetch(PDO::FETCH_ASSOC);

$decisionStatus = $dispatch ? $dispatch['decision'] : 'PENDENTE';
$decisionText = $dispatch ? $dispatch['decision_text'] : 'O processo ainda não possui despacho final emitido.';

// --- CONFIGURAÇÃO DO QR CODE ---
$verificacaoUrl = "https://teu-projeto-no-railway.up.railway.app/verificar_despacho.php?protocolo=" . $req['protocol_number'];
$tempQrPath = __DIR__ . '/../../temp/qrcode_' . $req['protocol_number'] . '.png';

if (!file_exists(__DIR__ . '/../../temp')) {
    mkdir(__DIR__ . '/../../temp', 0777, true);
}

QRcode::png($verificacaoUrl, $tempQrPath, QR_ECLEVEL_L, 4, 2);

// --- GERAÇÃO DO PDF FORMATADO ---
class PDF extends FPDF {
    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Times', '', 8);
        $this->Cell(0, 4, utf8_decode("ISPS | Campus Principal: Bairro Catondo Vila de Songo, Distrito de Cahora Bassa, Tel: 258-875253322, Fax:"), 0, 1, 'C');
        $this->Cell(0, 4, utf8_decode("258-25282337/8, Correio electrónico: secretariado@ispsongo.ac.mz, Página oficial: www.ispsongo.ac.mz"), 0, 1, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(25, 20, 25);

// Inserir QR Code no canto superior direito
$pdf->Image($tempQrPath, 160, 18, 25);
$pdf->SetXY(150, 44);
$pdf->SetFont('Times', 'I', 7);
$pdf->MultiCell(45, 3, utf8_decode("Escaneie o código para verificar a autenticidade"), 0, 'C');

$pdf->SetY(20);

// Cabeçalho Institucional
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 5, utf8_decode("Instituto Superior Politécnico de Songo"), 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode("DIVISÃO DE ENGENHARIA"), 0, 1, 'C');
$pdf->SetFont('Times', 'I', 9);
$pdf->Cell(0, 5, utf8_decode("CURSO DE LICENCIATURA EM ENGENHARIA ELECTRÓNICA E DE TELECOMUNICAÇÕES"), 0, 1, 'C');
$pdf->Ln(6);

// Título do Documento
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, utf8_decode("COMUNICAÇÃO DE DESPACHO"), 0, 1, 'C');
$pdf->Ln(8);

// Destinatário
$pdf->SetFont('Times', '', 10);
$pdf->Cell(0, 5, utf8_decode("Exmo. (a) Senhor (a)"), 0, 1, 'L');
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode($req['student_name']), 0, 1, 'L');
$pdf->Ln(8);

// Referência e Data limpa em formato numérico padrão
$dataSubmissao = date('d/m/Y', strtotime($req['submitted_at']));
$refText = "Nossa Ref.: " . $req['protocol_number'] . "/2026";
$pdf->Cell(0, 5, utf8_decode($refText), 0, 1, 'L');
$pdf->Ln(4);

// Corpo do Texto
$corpoTexto = "Apresentada a vossa solicitação nº " . $req['protocol_number'] . ", datada de " . $dataSubmissao . ", onde solicita autorização para " . strtolower($req['request_type_name']) . ", cumpre-nos transcrever o teor do despacho exarado pelo Exmo. Senhor Director do curso de Engenharia Electrónica e de Telecomunicações do ISPS.";
$pdf->MultiCell(0, 5, utf8_decode($corpoTexto));
$pdf->Ln(6);

// Assunto e Fundamentação
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode("Assunto: " . $req['subject']), 0, 1, 'L');
$pdf->SetFont('Times', '', 10);
$pdf->MultiCell(0, 5, utf8_decode("Fundamentação / Descrição:\n" . $req['description']));
$pdf->Ln(6);

// Decisão e Justificação/Motivos do Despacho
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode("Despacho: " . $decisionStatus), 0, 1, 'L');

if ($decisionStatus === 'INDEFERIDO' || !empty($decisionText)) {
    $pdf->SetFont('Times', '', 10);
    $pdf->MultiCell(0, 5, utf8_decode("Motivo / Parecer:\n" . $decisionText));
}
$pdf->Ln(12);

// Bloco de Assinatura
$pdf->SetFont('Times', '', 10);
$pdf->Cell(0, 5, utf8_decode("O Director (a)"), 0, 1, 'L');
$pdf->Ln(10);

$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode("Mestre. Engº. Veronica Wamusse"), 0, 1, 'L');
$pdf->SetFont('Times', '', 9);
$pdf->Cell(0, 5, utf8_decode("(Assistente Universitário)"), 0, 1, 'L');

// Saída do PDF
$pdf->Output('I', 'Despacho_' . $req['protocol_number'] . '.pdf');

if (file_exists($tempQrPath)) {
    unlink($tempQrPath);
}
exit;