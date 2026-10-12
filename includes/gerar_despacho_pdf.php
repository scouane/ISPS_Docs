<?php
// Incluir ficheiros de autenticação, base de dados e bibliotecas
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/fpdf.php'; // Caminho para FPDF
require_once __DIR__ . '/../../lib/qrlib.php'; // Caminho para phpqrcode

$user = requireRole(['ESTUDANTE', 'ADMIN']);
$processId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = getDB();

// 1. Buscar os dados do requerimento, estudante, curso e despacho
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
$req = $stmt->execute->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    die("Requerimento não encontrado.");
}

// Se for estudante, garantir que só descarrega os seus próprios requerimentos
if ($user['role'] === 'ESTUDANTE' && $req['student_id'] != $user['id']) {
    die("Acesso não autorizado.");
}

// --- CONFIGURAÇÃO DO QR CODE ---

// URL de verificação (Substitui pela URL real do teu sistema no Railway)
// Esta URL deve apontar para uma página pública que valida o protocolo.
$verificacaoUrl = "https://teu-projeto-no-railway.up.railway.app/verificar_despacho.php?protocolo=" . $req['protocol_number'];

// Caminho temporário para guardar a imagem do QR Code
$tempQrPath = __DIR__ . '/../../temp/qrcode_' . $req['protocol_number'] . '.png';

// Criar a pasta temp se não existir
if (!file_exists(__DIR__ . '/../../temp')) {
    mkdir(__DIR__ . '/../../temp', 0777, true);
}

// Gerar o QR Code
// Parâmetros: conteúdo, ficheiro de saída, nível de correção, tamanho do pixel, margem
QRcode::png($verificacaoUrl, $tempQrPath, QR_ECLEVEL_L, 4, 2);

// --- GERAÇÃO DO PDF ---

// Estender FPDF para incluir rodapé institucional
class PDF extends FPDF {
    function Footer() {
        // Rodapé institucional idêntico ao modelo oficial
        $this->SetY(-25);
        $this->SetFont('Times', '', 8);
        $this->Cell(0, 4, utf8_decode("ISPS | Campus Principal: Bairro Catondo Vila de Songo, Distrito de Cahora Bassa, Tel: 258-875253322, Fax:"), 0, 1, 'C');
        $this->Cell(0, 4, utf8_decode("258-25282337/8, Correio electrónico: secretariado@ispsongo.ac.mz, Página oficial: www.ispsongo.ac.mz"), 0, 1, 'C');
    }
}

// Criar instância do PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(25, 20, 25);

// --- INSERÇÃO DO QR CODE NO CANTO SUPERIOR DIREITO ---
// Parâmetros da imagem: ficheiro, x, y, largura, altura (opcional)
// Posicionado a 160mm da esquerda e 20mm do topo, com 25mm de largura.
$pdf->Image($tempQrPath, 160, 20, 25);

// Texto explicativo do QR Code
$pdf->SetXY(160, 46); // Posicionar abaixo do QR Code
$pdf->SetFont('Times', 'I', 8);
$pdf->MultiCell(25, 3, utf8_decode("Escoreie o código para verificar a autenticidade"), 0, 'C');

// Restaurar posição para o cabeçalho
$pdf->SetY(20);

// Cabeçalho institucional (Texto do exemplar)
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, utf8_decode("INSTITUTO SUPERIOR POLITÉCNICO DE SONGO"), 0, 1, 'C');
$pdf->SetFont('Times', 'I', 10);
$pdf->Cell(0, 5, utf8_decode("DIVISÃO DE ENGENHARIA"), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode("CURSO DE LICENCIATURA EM ENGENHARIA ELECTRÓNICA E DE TELECOMUNICAÇÕES"), 0, 1, 'C');
$pdf->Ln(8);

// Título do documento
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 6, utf8_decode("COMUNICAÇÃO DE DESPACHO"), 0, 1, 'C');
$pdf->Ln(6);

// Destinatário
$pdf->SetFont('Times', '', 11);
$pdf->Cell(0, 6, utf8_decode("Exmo. (a) Senhor (a)"), 0, 1, 'L');
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, utf8_decode($req['student_name']), 0, 1, 'L');
$pdf->Ln(6);

// Referência e Data do Requerimento
$pdf->SetFont('Times', '', 11);
$refText = "Nossa Ref.: " . $req['protocol_number'] . "/ISPS/DE/2026";
$pdf->Cell(0, 6, utf8_decode($refText), 0, 1, 'L');
$pdf->Ln(4);

// Texto do corpo (baseado no exemplar oficial)
$formattedDate = date('d de F^{\cdot} de Y', strtotime($req['submitted_at'])); // Ajuste simples de data
$corpoTexto = "Apresentada a vossa solicitação nº " . $req['protocol_number'] . ", onde solicita autorização para " . strtolower($req['request_type_name']) . ", cumpre-nos transcrever o teor do despacho exarado pelas instâncias competentes do ISPS.";

$pdf->MultiCell(0, 6, utf8_decode($corpoTexto));
$pdf->Ln(6);

// Detalhes do assunto e descrição enviada pelo aluno (opcional, baseado na tua implementação)
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, utf8_decode("Assunto: " . $req['subject']), 0, 1, 'L');
$pdf->SetFont('Times', '', 11);
$pdf->MultiCell(0, 6, utf8_decode("Teor / Justificativa:\n" . $req['description']));
$pdf->Ln(10);

// Decisão / Despacho
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, utf8_decode("Despacho: " . $req['current_status']), 0, 1, 'L');
$pdf->Ln(15);

// Bloco de Assinaturas e Carimbo Virtual (Baseado no exemplar oficial)
$pdf->SetFont('Times', '', 11);
$pdf->Cell(0, 5, utf8_decode("O Director (a)"), 0, 1, 'L');
$pdf->Ln(15); // Espaço para assinatura visual ou carimbo

$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(0, 5, utf8_decode("Mestre. Engº. Veronica Wamusse"), 0, 1, 'L');
$pdf->SetFont('Times', '', 10);
$pdf->Cell(0, 5, utf8_decode("(Assistente Universitário)"), 0, 1, 'L');

// Saída do PDF para o navegador
$pdf->Output('I', 'Despacho_' . $req['protocol_number'] . '.pdf');

// Limpeza: Apagar o ficheiro temporário do QR Code
unlink($tempQrPath);

exit;