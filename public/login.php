<?php
require_once __DIR__ . '/../includes/auth.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = attemptLogin($email, $password);

    if ($user) {
        $destinos = [
            'ESTUDANTE'  => 'estudante/dashboard.php',
            'SECRETARIA' => 'secretaria/dashboard.php',
            'DIRECTOR'   => 'director/dashboard.php',
            'ADMIN'      => 'admin/dashboard.php',
        ];
        header('Location: ' . ($destinos[$user['role_code']] ?? 'login.php'));
        exit;
    }

    $erro = 'Email ou palavra-passe inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISPS DOCS — Entrar</title>
    <!-- Subir 1 nível para aceder a assets/css/style.css -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page-wrapper">
    <div class="auth-container">
        <!-- Banner Institucional -->
        <div class="auth-banner">
            <div class="banner-content">
                <!-- Subir 1 nível para aceder a assets/img/logo_isps.png -->
                <img src="../assets/css/img/logotipo.png" alt="Logótipo ISPS" class="banner-logo">
                <h2>ISPS DOCS</h2>
                <p>Sistema Integrado de Gestão documental do Instituto Superior Politécnico de Songo.</p>
            </div>
        </div>

        <!-- Formulário de Autenticação -->
        <div class="auth-form-container">
            <div class="auth-form-header">
                <h3>Bem-vindo volta</h3>
                <p>Aceda à sua conta para acompanhar e gerir os seus processos.</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert error"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="email">Endereço de Email</label>
                    <input type="email" id="email" name="email" placeholder="exemplo@isps.ac.mz" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Palavra-passe</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-primary-block">Entrar no Sistema</button>
            </form>

            <div class="auth-footer">
                <p>&copy; <?= date('Y') ?> Instituto Superior Politécnico de Songo</p>
            </div>
        </div>
    </div>
</body>
</html>