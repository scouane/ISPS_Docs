<?php
/**
 * Configuração da ligação à base de dados MySQL/MariaDB (XAMPP).
 * Ajusta estas constantes conforme o teu ambiente local.
 */
/*
$host="localhost";
$port=3306;
$socket="";
$user="root";
$password="";
$dbname="isps_flow";  */
define('DB_HOST', 'localhost');
define('DB_NAME', 'isps_flow');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Pasta física onde os documentos são armazenados (fora da webroot, idealmente)
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Erro de ligação à base de dados: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
