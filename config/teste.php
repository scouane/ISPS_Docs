<?php
// Carrega o arquivo de configuração da base de dados
require_once __DIR__ . '/config/database.php';

try {
    // Tenta obter a conexão PDO
    $pdo = getDB();
    
    // Executa uma consulta simples para validar a resposta do servidor
    $stmt = $pdo->query("SELECT DATABASE() AS banco_atual, VERSION() AS versao_mysql");
    $dados = $stmt->fetch();

    echo "<div style='font-family: Arial, sans-serif; padding: 20px; border: 2px solid #27AE60; background: #E9F9EF; color: #1E7D46; border-radius: 8px;'>";
    echo "<h2> Conexão estabelecida com sucesso!</h2>";
    echo "<p><strong>Base de Dados Conectada:</strong> " . htmlspecialchars($dados['banco_atual']) . "</p>";
    echo "<p><strong>Versão do MySQL:</strong> " . htmlspecialchars($dados['versao_mysql']) . "</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; border: 2px solid #C0392B; background: #FBEAE8; color: #922B21; border-radius: 8px;'>";
    echo "<h2> Erro na Conexão com o Banco de Dados</h2>";
    echo "<p><strong>Detalhes do Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}