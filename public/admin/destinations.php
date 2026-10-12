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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Destinos Institucionais</title>
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
        label { display: flex; flex-direction: column; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); gap: 5px; margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; background: #fff; color: var(--text-main); }
        button[type="submit"] { background: var(--primary-color); color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; margin-top: 5px; }
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
            <li><a href="users.php" class="menu-item">👥 Utilizadores</a></li>
            <li><a href="request_types.php" class="menu-item">📄 Tipos de Documentos</a></li>
            <li><a href="destinations.php" class="menu-item active">🏛️ Destinos & Unidades</a></li>
            <li><a href="routing_rules.php" class="menu-item">🔄 Regras de Tramitação</a></li>
            <li><a href="../logout.php" class="menu-item" style="color: #f87171;">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="font-weight: 600; font-size: 0.95rem;">Destinos Institucionais</div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['full_name'] ?? 'Administrador') ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo Principal -->
        <div class="dashboard-content">

            <div style="background: #fff; padding: 15px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <h2 style="font-size: 1.3rem; color: var(--text-main);">Destinos Institucionais</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">
                    Um destino é um ponto de decisão concreto para onde um processo pode ser encaminhado — a base sobre a qual o motor de tramitação decide.
                </p>
            </div>

            <!-- Formulário Novo Destino -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Novo Destino</h3>
                </div>

                <?php if ($erro): ?><div class="alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
                <?php if ($sucesso): ?><div class="alert-success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

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

            <!-- Tabela Destinos Existentes -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Destinos Existentes</h3>
                </div>
                <table class="enterprise-table">
                    <thead>
                        <tr>
                            <th>Rótulo</th>
                            <th>Tipo</th>
                            <th>Curso/Direcção</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($destinos as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['label']) ?></strong></td>
                            <td><?= htmlspecialchars($d['type']) ?></td>
                            <td><?= htmlspecialchars($d['course_code'] ?? $d['dept_code'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>
</html>