<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $destinos = [
        'ESTUDANTE'  => '/public/estudante/dashboard.php',
        'SECRETARIA' => '/public/secretaria/dashboard.php',
        'DIRECTOR'   => '/public/director/dashboard.php',
        'ADMIN'      => '/public/admin/dashboard.php',
    ];
    header('Location: ' . ($destinos[currentUser()['role_code']] ?? '/public/login.php'));
} else {
    header('Location: /public/login.php');
}
exit;
