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
                $pdo->prepare('UPDATE courses SET director_user_id = :uid WHERE id = :cid')
                    ->execute(['uid' => $newUserId, 'cid' => (int) $_POST['course_id']]);
            } elseif ($roleCode === 'DIRECTOR' && !empty($_POST['department_id'])) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Gestão de Utilizadores</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Estilo Corporativo Unificado -->
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --topbar-bg: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary-color: #0b5394;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--main-bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }

        /* Barra Lateral Escura */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #cbd5e1; display: flex; flex-direction: column; border-right: 1px solid #334155; }
        .sidebar-brand { padding: 20px; font-size: 1.1rem; font-weight: bold; color: #fff; background: var(--topbar-bg); display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { list-style: none; padding: 15px 0; overflow-y: auto; flex: 1; }
        .sidebar-menu .menu-item { display: block; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar-menu .menu-item:hover, .sidebar-menu .menu-item.active { background-color: var(--sidebar-hover); color: #fff; border-left-color: #38bdf8; }

        /* Área Principal */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navbar */
        .top-navbar { height: 60px; background-color: var(--topbar-bg); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; }
        .user-info { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; }
        .user-info a { color: #f87171; text-decoration: none; font-weight: 600; }

        /* Conteúdo do Dashboard */
        .dashboard-content { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        .card-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .panel-header { margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .panel-header h3 { font-size: 1rem; color: var(--text-main); }

        /* Formulários */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        label { display: flex; flex-direction: column; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); gap: 5px; }
        input, select { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; background: #fff; color: var(--text-main); }
        button[type="submit"] { background: var(--primary-color); color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; margin-top: 10px; }
        button[type="submit"]:hover { background: #083c6d; }

        /* Alertas */
        .alert-error { background: #fee2e2; color: #991b1b; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #15803d; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600; }

        /* Tabela Enterprise */
        table.enterprise-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.enterprise-table th, table.enterprise-table td { padding: 12px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
        table.enterprise-table th { background: #f8fafc; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        table.enterprise-table tr:hover { background: #f1f5f9; }
    </style>
</head>
<body>

    <!-- Barra Lateral Escura com Logotipo Visível -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <img src="../../assets/css/img/logotipo.png" alt="ISPS" style="width: 24px; height: 24px; object-fit: contain;">
            <span>ISPS Docs</span>
        </div>
        <ul class="sidebar-menu" style="list-style: none;">
            <li><a href="dashboard.php" class="menu-item">📊 Visão Geral</a></li>
            <li><a href="users.php" class="menu-item active">👥 Utilizadores</a></li>
            <li><a href="request_types.php" class="menu-item">📄 Tipos de Documentos</a></li>
            <li><a href="destinations.php" class="menu-item">🏛️ Destinos & Unidades</a></li>
            <li><a href="routing_rules.php" class="menu-item">🔄 Regras de Tramitação</a></li>
            <li><a href="../logout.php" class="menu-item" style="color: #f87171;">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="font-weight: 600; font-size: 0.95rem;">Gestão de Utilizadores</div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['full_name'] ?? 'Administrador') ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo Principal -->
        <div class="dashboard-content">

            <!-- Formulário Novo Utilizador -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Criar Novo Utilizador</h3>
                </div>

                <?php if ($erro): ?><div class="alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
                <?php if ($sucesso): ?><div class="alert-success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

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

                    <div id="campos-estudante" style="display:none; margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <div class="form-row">
                            <label>Número de estudante
                                <input type="text" name="student_number">
                            </label>
                            <label>Ano de ingresso
                                <input type="number" name="enrollment_year" value="<?= date('Y') ?>">
                            </label>
                        </div>
                        <label style="margin-top: 15px;">Curso
                            <select name="course_id">
                                <option value="">-- Selecciona --</option>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div id="campos-director" style="display:none; margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px">
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

            <!-- Tabela de Utilizadores Existentes -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Utilizadores Existentes</h3>
                </div>
                <table class="enterprise-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($utilizadores as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['role_name']) ?></td>
                            <td>
                                <span style="font-weight: 600; color: <?= $u['active'] ? '#15803d' : '#dc2626' ?>;">
                                    <?= $u['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

<script>
function toggleCampos() {
    const role = document.getElementById('role_code').value;
    document.getElementById('campos-estudante').style.display = (role === 'ESTUDANTE') ? 'block' : 'none';
    document.getElementById('campos-director').style.display = (role === 'DIRECTOR') ? 'block' : 'none';
}
</script>
</body>
</html>