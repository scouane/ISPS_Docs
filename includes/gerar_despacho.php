<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // FPDF ou TCPDF

function gerarDespachoPDF($requestId) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, d.decision, d.parecer_text, d.signature_path, d.created_at AS dispatch_date,
               s.student_code, u.name AS student_name, c.name AS course_name
        FROM requests r
        JOIN dispatches d ON d.request_id = r.id
        JOIN students s ON r.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN courses c ON s.course_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$requestId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return false;

    // Configuração de margens oficiais do ISPS (Times New Roman 12, Margens 3-2-3-3 cm)[cite: 2, 3]
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(30, 30, 20); // Esquerda 30mm, Superior 30mm, Direita 20mm[cite: 2, 3]

    // Cabeçalho Institucional
    $pdf->SetFont('Times', 'B', 12);[cite: 2, 3]
    $pdf->Cell(0, 6, utf8_decode("INSTITUTO SUPERIOR POLITÉCNICO DE SONGO"), 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode("DIVISÃO DE ENGENHARIA"), 0, 1, 'C');[cite: 1]
    $pdf->Ln(5);

    // Número de Referência / Comunicação[cite: 1]
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(0, 6, utf8_decode("Nossa Ref. " . $data['protocol_number'] . "/ISPS/DE/024.1/" . date('Y')), 0, 1, 'R');[cite: 1]
    $pdf->Ln(10);

    // Endereçamento[cite: 1]
    $pdf->SetFont('Times', 'B', 12);[cite: 2, 3]
    $pdf->Cell(0, 6, utf8_decode("Exmo.(a) Senhor(a): " . $data['student_name']), 0, 1, 'L');[cite: 1]
    $pdf->SetFont('Times', '', 12);[cite: 2, 3]
    $pdf->Cell(0, 6, utf8_decode("Estudante do Curso de " . $data['course_name']), 0, 1, 'L');[cite: 1]
    $pdf->Cell(0, 6, utf8_decode("Nº de Estudante: " . $data['student_code']), 0, 1, 'L');[cite: 1]
    $pdf->Ln(10);

    // Assunto[cite: 1]
    $pdf->SetFont('Times', 'B', 12);[cite: 2, 3]
    $pdf->Cell(0, 6, utf8_decode("Assunto: Comunicação de Despacho - " . $data['subject']), 0, 1, 'L');[cite: 1]
    $pdf->Ln(5);

    // Texto de Notificação[cite: 1]
    $pdf->SetFont('Times', '', 12);[cite: 2, 3]
    $corpo = "Para os devidos efeitos, comunica-se a V. Excia. que sobre o seu requerimento submetido em " . 
             date('d/m/Y', strtotime($data['submitted_at'])) . ", recaiu o seguinte despacho exarado pelo órgão competente:\n\n" .[cite: 1]
             "\"" . $data['parecer_text'] . "\"\n\n" .
             "Decisão Final: " . strtoupper($data['decision']) . ".";[cite: 1]

    $pdf->MultiCell(0, 6, utf8_decode($corpo));
    $pdf->Ln(15);

    // Data e Assinatura Digital[cite: 1]
    $pdf->Cell(0, 6, utf8_decode("Songo, " . date('d \d\e F \d\e Y', strtotime($data['dispatch_date']))), 0, 1, 'R');[cite: 1]
    $pdf->Ln(10);

    if (!empty($data['signature_path']) && file_exists(__DIR__ . '/../' . $data['signature_path'])) {
        $pdf->Image(__DIR__ . '/../' . $data['signature_path'], 120, $pdf->GetY(), 50);
        $pdf->Ln(20);
    }

    $pdf->SetFont('Times', 'B', 12);[cite: 2, 3]
    $pdf->Cell(0, 6, utf8_decode("A Secretaria Geral"), 0, 1, 'R');[cite: 1]

    // Guardar e retornar o caminho do PDF
    $pdfDirectory = __DIR__ . '/../uploads/despachos/';
    if (!file_exists($pdfDirectory)) {
        mkdir($pdfDirectory, 0777, true);
    }

    $filePath = 'uploads/despachos/Despacho_' . $data['id'] . '.pdf';
    $pdf->Output('F', __DIR__ . '/../' . $filePath);

    return $filePath;
}
?>