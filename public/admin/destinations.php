<?php
require_once __DIR__ . '/../../includes/auth.php';

$user = requireRole(['ADMIN']);
$pdo = getDB();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $courseId = !empty($_POST['course_id']) ? (int) $_POST['course_id'] : null;
    $departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $label = trim($_POST['label'] ?? '');

    $valido =
        ($type === 'DIRECTOR_CURSO' && $courseId && !$departmentId) ||
        ($type === 'DIRECCAO_CENTRAL' && $departmentId && !$courseId) ||
        ($type === 'DIRECCAO_GERAL' && !$courseId);

    if (!$valido || $label === '') {
        $erro = 'Combinação inválida: Director de Curso exige curso (sem direcção); ' .
                'Direcção Central exige direcção (sem curso); Direcção Geral não usa nenhum dos dois.';
    } else {
        $pdo->prepare(
            'INSERT INTO destinations (type, course_id, department_id, label)
             VALUES (:type, :course, :dept, :label)'
        )->execute(['type' => $type, 'course' => $courseId, 'dept' => $departmentId, 'label' => $label]);
        $sucesso = "Destino \"$label\" criado com sucesso.";
    }
}

$destinos = $pdo->query(
    "SELECT d.*, c.code AS course_code, dep.code AS dept_code
     FROM destinations d
     LEFT JOIN courses c ON c.id = d.course_id
     LEFT JOIN departments dep ON dep.id = d.department_id
     ORDER BY d.type, d.label"
)->fetchAll();

$cursos = $pdo->query('SELECT id, code, name FROM courses ORDER BY code')->fetchAll();
$departamentos = $pdo->query('SELECT id, code, name FROM departments ORDER BY code')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Destinos — ISPS Flow</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../_nav.php'; ?>

<main class="container">
    <h1>Destinos Institucionais</h1>
    <p style="color:#667;font-size:0.9rem">
        Um destino é um ponto de decisão concreto para onde um processo pode ser
        encaminhado — a base sobre a qual o motor de tramitação decide.
    </p>

    <div class="card">
        <h3>Novo Destino</h3>
        <?php if ($erro): ?><p class="error"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="success"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

        <form method="post">
            <label>Tipo
                <select name="type" required>
                    <option value="DIRECTOR_CURSO">Director de Curso</option>
                    <option value="DIRECCAO_CENTRAL">Direcção Central</option>
                    <option value="DIRECCAO_GERAL">Direcção Geral</option>
                </select>
            </label>
            <div class="form-row">
                <label>Curso (se Director de Curso)
                    <select name="course_id">
                        <option value="">-- Nenhum --</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Direcção (se Direcção Central)
                    <select name="department_id">
                        <option value="">-- Nenhuma --</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Rótulo (nome amigável)
                <input type="text" name="label" placeholder="Ex: Director do Curso EET" required>
            </label>
            <button type="submit">Criar Destino</button>
        </form>
    </div>

    <h3>Destinos existentes</h3>
    <table class="table">
        <thead><tr><th>Rótulo</th><th>Tipo</th><th>Curso/Direcção</th></tr></thead>
        <tbody>
        <?php foreach ($destinos as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['label']) ?></td>
                <td><?= htmlspecialchars($d['type']) ?></td>
                <td><?= htmlspecialchars($d['course_code'] ?? $d['dept_code'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
