<?php
// Script de Teste de Conexão com o Banco de Dados

require_once 'database.php';

echo "Testando conexão com o banco de dados Aiven...\n\n";

try {
    $conn = getDbConnection();
    echo "✅ Conexão estabelecida com sucesso!\n\n";
    
    // Testar se as tabelas existem
    $tables = ['users', 'points_history', 'withdrawals', 'ranking', 'notifications', 'settings', 'invites', 'daily_checkin'];
    
    echo "Verificando tabelas:\n";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Tabela '$table' encontrada\n";
        } else {
            echo "❌ Tabela '$table' NÃO encontrada (você precisa importar o schema.sql)\n";
        }
    }
    
    $conn->close();
    echo "\n✅ Teste concluído!\n";
    
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
}

?>
