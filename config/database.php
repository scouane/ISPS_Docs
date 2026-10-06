<?php

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Tenta ler do Railway (MYSQL*) ou variáveis personalizadas (DB_*), com fallback para o XAMPP local
        $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');
        $name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'railway');
        $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
        $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: '');

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // Regista o erro privado no log do servidor (Railway)
            error_log('Erro PDO: ' . $e->getMessage());

            // Devolve resposta limpa ao utilizador
            http_response_code(500);
            die('Erro de ligação à base de dados. Por favor, tente novamente mais tarde.');
        }
    }

    return $pdo;
}
