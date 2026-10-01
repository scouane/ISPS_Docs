<?php
require_once __DIR__ . '/../../includes/auth.php';

$user = requireRole(['ADMIN']);
$pdo = getDB();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestTypeId = (int) ($_POST['request_type_id'] ?? 0);
    $courseId = !empty($_POST['course_id']) ? (int) $_POST['course_id'] : null;
    $destinationId = (int) ($_POST['destination_id'] ?? 0);
    $priority = (int) ($_POST['priority'] ?? 0);

    if ($requestTypeId <= 0 || $destinationId <= 0) {
        $erro = 'Selecciona o tipo de pedido e o destino.';
    } else {
        $pdo->prepare(
            'INSERT INTO routing_rules (request_type_id, course_id, destination_id, priority)
             VALUES (:rt, :course, :dest, :prio)'
        )->execute([
            'rt' => $requestTypeId, 'course' => $courseId,
            'dest' => $destinationId, 'prio' => $priority,
        ]);
        $sucesso = 'Regra de encaminhamento criada com sucesso.';
    }
}

$regras = $pdo->query(
    "SELECT rr.id, rt.name AS request_type_name, c.code AS course_code,
            d.label AS destination_label, rr.priority
     FROM routing_rules rr
     JOIN request_types rt ON rt.id = rr.request_type_id
     LEFT JOIN courses c ON c.id = rr.course_id
     JOIN destinations d ON d.id = rr.destination_id
     ORDER BY rt.name, rr.priority DESC"
)->fetchAll();

$tipos = $pdo->query('SELECT id, name FROM request_types WHERE active = 1 ORDER BY name')->fetchAll();
$cursos = $pdo->query('SELECT id, code FROM courses ORDER BY code')->fetchAll();
$destinos = $pdo->query('SELECT id, label FROM destinations ORDER BY label')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Regras de Encaminhamento — ISPS Flow</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../_nav.php'; ?>

<main class="container">
    <h1>Regras de Encaminhamento</h1>
    <p style="color:#667;font-size:0.9rem">
        Sobrepõem o destino por omissão de um tipo de pedido. Deixa "Curso" em
        branco para uma regra genérica (aplica-se a todos os cursos), ou
        selecciona um curso para uma regra específica. São obrigatórias para
        tipos de pedido com destino por omissão "Direcção Central".
    </p>

    <div class="card">
        <h3>Nova Regra</h3>
        <?php if ($erro): ?><p class="error"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="success"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

        <form method="post">
            <label>Tipo de Pedido
                <select name="request_type_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-row">
                <label>Curso (opcional — genérica se vazio)
                    <select name="course_id">
                        <option value="">-- Todos os cursos --</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Destino
                    <select name="destination_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($destinos as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Prioridade (regras com valor mais alto vencem em caso de empate)
                <input type="number" name="priority" value="0">
            </label>
            <button type="submit">Criar Regra</button>
        </form>
    </div>

    <h3>Regras existentes</h3>
    <table class="table">
        <thead><tr><th>Tipo de Pedido</th><th>Curso</th><th>Destino</th><th>Prioridade</th></tr></thead>
        <tbody>
        <?php foreach ($regras as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['request_type_name']) ?></td>
                <td><?= htmlspecialchars($r['course_code'] ?? 'Todos') ?></td>
                <td><?= htmlspecialchars($r['destination_label']) ?></td>
                <td><?= (int)$r['priority'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
