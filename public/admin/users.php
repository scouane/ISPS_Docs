<?php
require_once __DIR__ . '/../../includes/auth.php';

$user = requireRole(['ADMIN']);
$pdo = getDB();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleCode = $_POST['role_code'] ?? '';

    if ($fullName === '' || $email === '' || strlen($password) < 6 || $roleCode === '') {
        $erro = 'Preenche todos os campos (a palavra-passe deve ter pelo menos 6 caracteres).';
    } else {
        try {
            $pdo->beginTransaction();
            $newUserId = createUser($pdo, $fullName, $email, $password, $roleCode);

            if ($roleCode === 'ESTUDANTE') {
                $studentNumber = trim($_POST['student_number'] ?? '');
                $courseId = (int) ($_POST['course_id'] ?? 0);
                $year = (int) ($_POST['enrollment_year'] ?? date('Y'));

                if ($studentNumber === '' || $courseId <= 0) {
                    throw new InvalidArgumentException('Número de estudante e curso são obrigatórios.');
                }

                $pdo->prepare(
                    'INSERT INTO students (user_id, student_number, course_id, enrollment_year)
                     VALUES (:uid, :num, :course, :year)'
                )->execute([
                    'uid' => $newUserId, 'num' => $studentNumber,
                    'course' => $courseId, 'year' => $year,
                ]);
            } elseif ($roleCode === 'DIRECTOR' && !empty($_POST['course_id'])) {
                // Associa directamente este director como responsável do curso escolhido
                $pdo->prepare('UPDATE courses SET director_user_id = :uid WHERE id = :cid')
                    ->execute(['uid' => $newUserId, 'cid' => (int) $_POST['course_id']]);
            } elseif ($roleCode === 'DIRECTOR' && !empty($_POST['department_id'])) {
                // Ou como responsável de uma direcção central
                $pdo->prepare('UPDATE departments SET responsible_user_id = :uid WHERE id = :did')
                    ->execute(['uid' => $newUserId, 'did' => (int) $_POST['department_id']]);
            }

            $pdo->commit();
            $sucesso = "Utilizador \"$fullName\" criado com sucesso.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao criar utilizador: ' . $e->getMessage();
        }
    }
}

$utilizadores = $pdo->query(
    "SELECT u.id, u.full_name, u.email, u.active, r.name AS role_name, r.code AS role_code
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY r.code, u.full_name"
)->fetchAll();

$cursos = $pdo->query('SELECT id, code, name FROM courses ORDER BY code')->fetchAll();
$departamentos = $pdo->query('SELECT id, code, name FROM departments ORDER BY code')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Utilizadores — ISPS Flow</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../_nav.php'; ?>

<main class="container">
    <h1>Gestão de Utilizadores</h1>

    <div class="card">
        <h3>Novo Utilizador</h3>
        <?php if ($erro): ?><p class="error"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="success"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

        <form method="post" id="user-form">
            <div class="form-row">
                <label>Nome completo
                    <input type="text" name="full_name" required>
                </label>
                <label>Email
                    <input type="email" name="email" required>
                </label>
            </div>
            <div class="form-row">
                <label>Palavra-passe
                    <input type="password" name="password" minlength="6" required>
                </label>
                <label>Perfil
                    <select name="role_code" id="role_code" required onchange="toggleCampos()">
                        <option value="">-- Selecciona --</option>
                        <option value="ESTUDANTE">Estudante</option>
                        <option value="SECRETARIA">Secretaria</option>
                        <option value="DIRECTOR">Director</option>
                        <option value="ADMIN">Administrador</option>
                    </select>
                </label>
            </div>

            <div id="campos-estudante" style="display:none">
                <div class="form-row">
                    <label>Número de estudante
                        <input type="text" name="student_number">
                    </label>
                    <label>Ano de ingresso
                        <input type="number" name="enrollment_year" value="<?= date('Y') ?>">
                    </label>
                </div>
                <label>Curso
                    <select name="course_id">
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div id="campos-director" style="display:none">
                <p style="font-size:0.85rem;color:#667;margin-bottom:8px">
                    Associa este director a UM curso OU a UMA direcção central (não ambos).
                </p>
                <div class="form-row">
                    <label>Director do Curso
                        <select name="course_id">
                            <option value="">-- Nenhum --</option>
                            <?php foreach ($cursos as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Ou Responsável pela Direcção
                        <select name="department_id">
                            <option value="">-- Nenhuma --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['code'] . ' — ' . $d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <button type="submit">Criar Utilizador</button>
        </form>
    </div>

    <h3>Utilizadores existentes</h3>
    <table class="table">
        <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($utilizadores as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['role_name']) ?></td>
                <td><?= $u['active'] ? 'Activo' : 'Inactivo' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>

<script>
function toggleCampos() {
    const role = document.getElementById('role_code').value;
    document.getElementById('campos-estudante').style.display = (role === 'ESTUDANTE') ? 'block' : 'none';
    document.getElementById('campos-director').style.display = (role === 'DIRECTOR') ? 'block' : 'none';
}
</script>
</body>
</html>
