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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Tipos de Pedido</title>
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
        label { display: flex; flex-direction: column; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); gap: 5px; margin-bottom: 15px; }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; background: #fff; color: var(--text-main); }
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
            <li><a href="request_types.php" class="menu-item active">📄 Tipos de Documentos</a></li>
            <li><a href="destinations.php" class="menu-item">🏛️ Destinos & Unidades</a></li>
            <li><a href="routing_rules.php" class="menu-item">🔄 Regras de Tramitação</a></li>
            <li><a href="../logout.php" class="menu-item" style="color: #f87171;">Terminar Sessão</a></li>
        </ul>
    </nav>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="font-weight: 600; font-size: 0.95rem;">Gestão de Tipos de Pedido</div>
            <div class="user-info">
                <span>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['full_name'] ?? 'Administrador') ?></strong></span>
                <a href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Conteúdo Principal -->
        <div class="dashboard-content">

            <!-- Formulário Novo Tipo de Pedido -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Novo Tipo de Pedido</h3>
                </div>

                <?php if ($erro): ?><div class="alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
                <?php if ($sucesso): ?><div class="alert-success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

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
                    <label style="flex-direction: row; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="requires_course" style="width:auto;" checked>
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

            <!-- Tabela Tipos Existentes -->
            <div class="card-panel">
                <div class="panel-header">
                    <h3>Tipos Existentes</h3>
                </div>
                <table class="enterprise-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Destino por omissão</th>
                            <th>Depende do curso</th>
                            <th>Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td><?= htmlspecialchars($t['default_destination_type']) ?></td>
                            <td><?= $t['requires_course'] ? 'Sim' : 'Não' ?></td>
                            <td>
                                <span style="font-weight: 600; color: <?= $t['active'] ? '#15803d' : '#dc2626' ?>;">
                                    <?= $t['active'] ? 'Sim' : 'Não' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>
</html>