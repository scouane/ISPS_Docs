<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = requireRole(['ESTUDANTE', 'SECRETARIA', 'DIRECTOR', 'ADMIN']);

$db = getDB();
$requestId = $_GET['id'] ?? null;

if (!$requestId) {
    header('Location: ../secretaria/dashboard.php');
    exit;
}

// 1. Processar Ação da Secretaria (Tramitação/Encaminhamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'secretaria_tramitar') {
    if (in_array($user['role_code'], ['SECRETARIA', 'ADMIN'])) {
        $newStatus      = $_POST['current_status'] ?? 'ENCAMINHADO';
        $destinationId = $_POST['destination_id'] ?? null;

        $updStmt = $db->prepare("
            UPDATE requests 
            SET current_status = ?, current_destination_id = ? 
            WHERE id = ?
        ");
        $updStmt->execute([$newStatus, $destinationId, $requestId]);

        header("Location: processo.php?id=" . $requestId . "&msg=tramitado");
        exit;
    }
}

// 2. Processar Despacho do Director
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'director_despacho') {
    if (in_array($user['role_code'], ['DIRECTOR', 'ADMIN'])) {
        $decision     = $_POST['decision'] ?? '';
        $decisionText = trim($_POST['decision_text'] ?? '');

        if ($decision && $decisionText) {
            $db->beginTransaction();
            try {
                $dispStmt = $db->prepare("
                    INSERT INTO dispatches (request_id, dispatched_by, decision, decision_text)
                    VALUES (?, ?, ?, ?)
                ");
                $dispStmt->execute([$requestId, $user['id'], $decision, $decisionText]);

                $newStatus = ($decision === 'DEFERIDO' || $decision === 'INDEFERIDO') ? 'DESPACHADO' : 'EM_ANALISE';
                $updStmt = $db->prepare("UPDATE requests SET current_status = ? WHERE id = ?");
                $updStmt->execute([$newStatus, $requestId]);

                $db->commit();
                header("Location: processo.php?id=" . $requestId . "&msg=despachado");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
            }
        }
    }
}

// 3. Obter dados do requerimento
$query = "
    SELECT 
        r.id,
        r.protocol_number,
        r.subject,
        r.description,
        r.current_status,
        r.submitted_at,
        r.current_destination_id,
        u.full_name AS student_name,
        s.student_number,
        c.name AS course_name,
        t.name AS request_type,
        d.label AS current_destination
    FROM requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN courses c ON s.course_id = c.id
    JOIN request_types t ON r.request_type_id = t.id
    LEFT JOIN destinations d ON r.current_destination_id = d.id
    WHERE r.id = ?
";
$stmt = $db->prepare($query);
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "Requerimento não encontrado.";
    exit;
}

// Carregar destinos disponíveis para o dropdown da Secretaria
$destinations = $db->query("SELECT id, label FROM destinations")->fetchAll(PDO::FETCH_ASSOC);

// Carregar histórico de despachos
$dispStmt = $db->prepare("
    SELECT d.decision, d.decision_text, d.dispatched_at, u.full_name AS director_name
    FROM dispatches d
    JOIN users u ON d.dispatched_by = u.id
    WHERE d.request_id = ?
    ORDER BY d.dispatched_at DESC
");
$dispStmt->execute([$requestId]);
$dispatches = $dispStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Processo <?= htmlspecialchars($request['protocol_number']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="dashboard-body">
    <header class="top-navbar">
        <div class="nav-brand">
            <img src="../../assets/img/logo_isps.png" alt="ISPS" class="nav-logo">
            <span>ISPS DOCS <small>| Detalhes do Processo</small></span>
        </div>
        <div class="nav-user">
            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Utilizador') ?></span>
            <a href="../logout.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="dashboard-layout">
        <main class="main-content" style="max-width: 1000px; margin: 0 auto; width: 100%;">
            <div class="page-header flex-between">
                <div>
                    <h2>Protocolo: <?= htmlspecialchars($request['protocol_number']) ?></h2>
                    <p>Submetido a <?= date('d/m/Y \à\s H:i', strtotime($request['submitted_at'])) ?></p>
                </div>
                <span class="status-badge <?= strtolower($request['current_status']) ?>" style="font-size: 1rem; padding: 8px 16px;">
                    <?= htmlspecialchars($request['current_status']) ?>
                </span>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert success" style="background: #dcfce7; color: #15803d; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $_GET['msg'] === 'tramitado' ? 'Processo encaminhado com sucesso!' : 'Despacho registado com sucesso!' ?>
                </div>
            <?php endif; ?>

            <div class="content-grid">
                <!-- Informações do Pedido -->
                <div class="card-panel">
                    <div class="panel-header">
                        <h3>Informação do Pedido</h3>
                    </div>
                    <p><strong>Requerente:</strong> <?= htmlspecialchars($request['student_name']) ?> (Nº <?= htmlspecialchars($request['student_number']) ?>)</p>
                    <p><strong>Curso:</strong> <?= htmlspecialchars($request['course_name']) ?></p>
                    <p><strong>Tipo de Documento:</strong> <?= htmlspecialchars($request['request_type']) ?></p>
                    <p><strong>Localização Atual:</strong> <?= htmlspecialchars($request['current_destination'] ?? 'Secretaria Geral') ?></p>
                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
                    <h4>Assunto: <?= htmlspecialchars($request['subject']) ?></h4>
                    <p style="background: #f8fafc; padding: 15px; border-radius: 8px; color: #334155;">
                        <?= nl2br(htmlspecialchars($request['description'] ?? 'Sem descrição detalhada.')) ?>
                    </p>
                </div>

                <!-- Painel de Gestão e Tramitação -->
                <div class="card-panel">
                    <div class="panel-header">
                        <h3>Tratamento do Processo</h3>
                    </div>

                    <!-- 1. Formulário para a Secretaria Geral -->
                    <?php if (in_array($user['role_code'], ['SECRETARIA', 'ADMIN'])): ?>
                        <h4 style="margin-bottom: 10px;">Encaminhar / Atualizar Estado</h4>
                        <form method="post" style="margin-bottom: 25px;">
                            <input type="hidden" name="action_type" value="secretaria_tramitar">
                            
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="current_status">Estado do Processo</label>
                                <select name="current_status" id="current_status" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="SUBMETIDO" <?= $request['current_status'] === 'SUBMETIDO' ? 'selected' : '' ?>>SUBMETIDO</option>
                                    <option value="EM_ANALISE" <?= $request['current_status'] === 'EM_ANALISE' ? 'selected' : '' ?>>EM ANÁLISE</option>
                                    <option value="ENCAMINHADO" <?= $request['current_status'] === 'ENCAMINHADO' ? 'selected' : '' ?>>ENCAMINHADO</option>
                                    <option value="CONCLUIDO" <?= $request['current_status'] === 'CONCLUIDO' ? 'selected' : '' ?>>CONCLUÍDO</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="destination_id">Destino / Departamento</label>
                                <select name="destination_id" id="destination_id" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <?php foreach ($destinations as $dest): ?>
                                        <option value="<?= $dest['id'] ?>" <?= $request['current_destination_id'] == $dest['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dest['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn-primary" style="width: 100%; border: none; cursor: pointer;">Guardar Tramitação</button>
                        </form>
                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                    <?php endif; ?>

                    <!-- 2. Formulário para o Director -->
                    <?php if (in_array($user['role_code'], ['DIRECTOR', 'ADMIN'])): ?>
                        <h4 style="margin-bottom: 10px;">Emitir Despacho Final</h4>
                        <form method="post" style="margin-bottom: 25px;">
                            <input type="hidden" name="action_type" value="director_despacho">
                            
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="decision">Decisão</label>
                                <select name="decision" id="decision" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="DEFERIDO">DEFERIDO (Aprovado)</option>
                                    <option value="INDEFERIDO">INDEFERIDO (Rejeitado)</option>
                                    <option value="PENDENTE_INFO">SOLICITAR MAIS INFORMAÇÃO</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="decision_text">Parecer / Justificação</label>
                                <textarea name="decision_text" id="decision_text" rows="3" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; box-sizing: border-box;"></textarea>
                            </div>
                            <button type="submit" class="btn-primary" style="width: 100%; border: none; cursor: pointer;">Submeter Decisão</button>
                        </form>
                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                    <?php endif; ?>

                    <!-- 3. Histórico de Despachos -->
                    <h4>Histórico de Despachos</h4>
                    <?php if (empty($dispatches)): ?>
                        <p class="empty-table" style="color: #64748b; font-size: 0.9rem; margin-top: 10px;">Ainda não foi emitido nenhum despacho para este processo.</p>
                    <?php else: ?>
                        <?php foreach ($dispatches as $disp): ?>
                            <div style="border-left: 3px solid #0b5394; padding-left: 12px; margin-top: 12px;">
                                <strong><?= htmlspecialchars($disp['decision']) ?></strong> por <?= htmlspecialchars($disp['director_name']) ?>
                                <br><small style="color: #64748b;"><?= date('d/m/Y H:i', strtotime($disp['dispatched_at'])) ?></small>
                                <p style="margin-top: 5px; font-size: 0.9rem;"><?= nl2br(htmlspecialchars($disp['decision_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>