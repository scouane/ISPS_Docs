<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /public/login.php');
exit;
