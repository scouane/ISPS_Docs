<?php
require_once __DIR__ . '/../../includes/auth.php';

$user = requireRole(['ADMIN']);
$pdo = getDB();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requiresCourse = isset($_POST['requires_course']) ? 1 : 0;
    $requiredDocs = trim($_POST['required_documents'] ?? '');
    $defaultDest = $_POST['default_destination_type'] ?? '';

    if ($name === '' || $defaultDest === '') {
        $erro = 'Preenche o nome e o destino por omissão.';
    } else {
        $pdo->prepare(
            'INSERT INTO request_types
                (name, description, requires_course, required_documents, default_destination_type)
             VALUES (:name, :desc, :requires, :docs, :dest)'
        )->execute([
            'name' => $name, 'desc' => $description, 'requires' => $requiresCourse,
            'docs' => $requiredDocs, 'dest' => $defaultDest,
        ]);
        $sucesso = "Tipo de pedido \"$name\" criado com sucesso.";
    }
}

$tipos = $pdo->query('SELECT * FROM request_types ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Tipos de Pedido — ISPS Flow</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../_nav.php'; ?>

<main class="container">
    <h1>Tipos de Pedido</h1>

    <div class="card">
        <h3>Novo Tipo de Pedido</h3>
        <?php if ($erro): ?><p class="error"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="success"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

        <form method="post">
            <label>Nome
                <input type="text" name="name" required>
            </label>
            <label>Descrição
                <textarea name="description" rows="2"></textarea>
            </label>
            <label>Documentos obrigatórios (texto livre, um por linha)
                <textarea name="required_documents" rows="3"></textarea>
            </label>
            <label>
                <input type="checkbox" name="requires_course" style="width:auto;display:inline-block" checked>
                Depende do curso do estudante
            </label>
            <label>Destino por omissão
                <select name="default_destination_type" required>
                    <option value="DIRECTOR_CURSO">Director do Curso</option>
                    <option value="DIRECCAO_GERAL">Direcção Geral</option>
                    <option value="DIRECCAO_CENTRAL">Direcção Central (requer regra específica)</option>
                </select>
            </label>
            <button type="submit">Criar Tipo de Pedido</button>
        </form>
    </div>

    <h3>Tipos existentes</h3>
    <table class="table">
        <thead><tr><th>Nome</th><th>Destino por omissão</th><th>Depende do curso</th><th>Activo</th></tr></thead>
        <tbody>
        <?php foreach ($tipos as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['name']) ?></td>
                <td><?= htmlspecialchars($t['default_destination_type']) ?></td>
                <td><?= $t['requires_course'] ? 'Sim' : 'Não' ?></td>
                <td><?= $t['active'] ? 'Sim' : 'Não' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
