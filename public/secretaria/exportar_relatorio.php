<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['SECRETARIA', 'ADMIN']);
$db = getDB();

// Obter o mês e ano atuais ou passados via parâmetro (ex: ?mes=10&ano=2026)
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// Definir cabeçalhos para download do ficheiro Excel (.xls)
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=Relatorio_Pedidos_' . $ano . '_' . $mes . '.xls');

// Buscar dados dos pedidos do mês selecionado
$stmt = $db->prepare("
    SELECT r.protocol_number, u.full_name as student_name, s.student_number, 
           rt.name as request_type, r.current_status, r.submitted_at
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN request_types rt ON r.request_type_id = rt.id
    WHERE MONTH(r.submitted_at) = ? AND YEAR(r.submitted_at) = ?
    ORDER BY r.submitted_at DESC
");
$stmt->execute([$mes, $ano]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gerar tabela HTML que o Excel lê perfeitamente
echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<table border="1">
    <thead>
        <tr style="background-color: #0b5394; color: #ffffff; font-weight: bold;">
            <th>Protocolo</th>
            <th>Estudante</th>
            <th>Nº de Estudante</th>
            <th>Tipo de Pedido</th>
            <th>Estado Atual</th>
            <th>Data de Submissão</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pedidos as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['protocol_number']) ?></td>
                <td><?= htmlspecialchars($p['student_name']) ?></td>
                <td><?= htmlspecialchars($p['student_number']) ?></td>
                <td><?= htmlspecialchars($p['request_type']) ?></td>
                <td><?= htmlspecialchars($p['current_status']) ?></td>
                <td><?= htmlspecialchars($p['submitted_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php exit; ?>