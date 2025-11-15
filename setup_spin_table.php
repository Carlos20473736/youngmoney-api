<?php
/**
 * Script temporário para criar tabela spin_history
 * 
 * Acesse: https://youngmoney-api-production.up.railway.app/setup_spin_table.php
 * Depois delete este arquivo!
 */

header('Content-Type: text/plain');
require_once __DIR__ . '/database.php';

try {
    echo "Criando tabela spin_history...\n\n";
    
    $conn = getDbConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS spin_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        prize_value INT NOT NULL,
        prize_index INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at)
    )";
    
    if ($conn->query($sql)) {
        echo "✅ Tabela spin_history criada com sucesso!\n\n";
    } else {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    // Verificar se tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'spin_history'");
    
    if ($result->num_rows > 0) {
        echo "✅ Verificação: Tabela existe no banco!\n\n";
        
        // Mostrar estrutura
        $result = $conn->query("DESCRIBE spin_history");
        
        echo "Estrutura da tabela:\n";
        echo "-------------------\n";
        while ($col = $result->fetch_assoc()) {
            echo "- {$col['Field']}: {$col['Type']}\n";
        }
    } else {
        echo "❌ Erro: Tabela não foi criada!\n";
    }
    
    echo "\n\n🗑️ IMPORTANTE: Delete este arquivo após executar!\n";
    echo "Comando: rm setup_spin_table.php\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Erro ao criar tabela:\n";
    echo $e->getMessage() . "\n";
}
?>
