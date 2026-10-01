<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/fpdf.php';

$user = requireRole(['ESTUDANTE', 'ADMIN']);$db = getDB();

// 1. Carregar os tipos de requerimentos e dados do estudante
$types =$db->query("SELECT * FROM request_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmtStudent =$db->prepare("
    SELECT s.*, u.full_name AS name, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.id = ?
");
$stmtStudent->execute([$user['id']]);
$studentData =$stmtStudent->fetch(PDO::FETCH_ASSOC);

// 2. Processar ações (Gerar Prévia em PDF ou Submeter)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typeId = (int)$_POST['request_type_id'];
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $action =$_POST['action'];

    $stmtType =$db->prepare("SELECT name FROM request_types WHERE id = ?");
    $stmtType->execute([$typeId]);
    $selectedType =$stmtType->fetchColumn();

    if ($action === 'generate_preview') {$pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();$pdf->SetMargins(30, 30, 20);
        
        $pdf->SetFont('Times', 'B', 12);$pdf->Cell(0, 7, utf8_decode("INSTITUTO SUPERIOR POLITÉCNICO DE SONGO"), 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('Times', 'B', 12);$pdf->Cell(0, 6, utf8_decode("EXMO. SENHOR DIRECTOR GERAL DO ISPS"), 0, 1, 'L');
        $pdf->Ln(5);$pdf->SetFont('Times', '', 12);
        $text = "Eu, " . $studentData['name'] . ", estudante do ISPS, vem por este meio requerer a V. Excia. se digne autorizar o pedido de " . $selectedType . ".\n\nFundamentação:\n" . $description;
        $pdf->MultiCell(0, 6, utf8_decode($text));$pdf->Output('I', 'Previa_Requerimento.pdf');
        exit;
    } 

    if ($action === 'submit_request') {$deadline = date('Y-m-d H:i:s', strtotime('+14 days'));

        $stmtInsert =$db->prepare("
            INSERT INTO requests (
                student_id, request_type_id, subject, description, 
                current_status, submitted_at, deadline_date
            ) VALUES (?, ?, ?, ?, 'SUBMETIDO', NOW(), ?)
        ");
        
        $stmtInsert->execute([$studentData['id'],
            $typeId,$subject,
            $description,$deadline
        ]);

        header('Location: meus_requerimentos.php?status=success');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS - Novo Requerimento</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --primary: #0f2b48;
            --primary-hover: #1a3e66;
            --accent: #2563eb;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text); padding: 40px 20px; }

        .container { max-width: 800px; margin: 0 auto; }
        
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }

        .header { margin-bottom: 28px; }
        .header h2 { font-size: 1.6rem; color: var(--primary); font-weight: 700; }
        .header p { color: var(--text-muted); font-size: 0.95rem; margin-top: 4px; }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background-color: #fafafa;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea.form-control { min-height: 180px; resize: vertical; line-height: 1.5; }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-primary { background-color: var(--primary); color: #fff; flex: 2; }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .btn-secondary { background-color: #f1f5f9; color: var(--text); border: 1px solid var(--border); flex: 1; }
        .btn-secondary:hover { background-color: #e2e8f0; }
    </style>
    <script>
        function updateTemplate() {
            const typeSelect = document.getElementById('request_type_id');
            const selectedText = typeSelect.options[typeSelect.selectedIndex].text;
            const textarea = document.getElementById('description');
            
            if (typeSelect.value !== '') {
                textarea.value = `Pelo exposto, solicita-se a V. Excia. a concessão/emissão relativa ao pedido de ${selectedText}.\n\nJustificativa:\n[Insira aqui os detalhes ou motivos do seu pedido]`;
            }
        }
    </script>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h2>Criar Novo Requerimento</h2>
            <p>Preencha os campos abaixo para submeter o seu pedido à Secretaria Geral do ISPS.</p>
        </div>

        <form method="POST" action="" id="reqForm">
            <div class="form-group">
                <label for="request_type_id">Tipo de Requerimento</label>
                <select name="request_type_id" id="request_type_id" class="form-control" onchange="updateTemplate()" required>
                    <option value="">-- Selecione o Tipo --</option>
                    <?php foreach ($types as$t): ?>
                        <option value="<?= $t['id'] ?>"><?= $t['id'] ?>. <?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject">Assunto</label>
                <input type="text" name="subject" id="subject" class="form-control" placeholder="Ex: Solicitação de Declaração de Frequência" required>
            </div>

            <div class="form-group">
                <label for="description">Teor do Pedido (Template Formatado)</label>
                <textarea name="description" id="description" class="form-control" rows="8" required></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" name="action" value="generate_preview" class="btn btn-secondary" onclick="document.getElementById('reqForm').target='_blank';">
                    📄 Gerar Prévia (PDF)
                </button>
                <button type="submit" name="action" value="submit_request" class="btn btn-primary" onclick="document.getElementById('reqForm').target='_self';">
                    🚀 Submeter Requerimento
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>