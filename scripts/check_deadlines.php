<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// 1. Procurar pedidos 'ENCAMINHADO' com 3 ou menos dias para o prazo e alert_sent = 0
$stmtAlerts = $db->prepare("
    SELECT r.id, r.protocol_number, r.deadline_date, u.email, u.name
    FROM requests r
    JOIN destinations d ON r.current_destination_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE r.current_status = 'ENCAMINHADO' 
      AND r.is_alert_sent = 0 
      AND DATEDIFF(r.deadline_date, NOW()) <= 3
");
$stmtAlerts->execute();
$pendingAlerts = $stmtAlerts->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingAlerts as $alert) {
    // Registar o envio do alerta na BD
    $stmtUpdate = $db->prepare("UPDATE requests SET is_alert_sent = 1 WHERE id = ?");
    $stmtUpdate->execute([$alert['id']]);

    // Criar notificação interna no sistema
    $stmtNotify = $db->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $msg = "⚠️ ALERTA DE SLA: O requerimento " . $alert['protocol_number'] . " expira a " . date('d/m/Y', strtotime($alert['deadline_date'])) . ". Por favor exare o despacho.";
    $stmtNotify->execute([$alert['user_id'], $msg]);
}

echo "Varredura de prazos concluída com sucesso. Alertas disparados: " . count($pendingAlerts) . "\n";
?>