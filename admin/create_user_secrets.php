<?php
/**
 * Script para criar a tabela user_secrets
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // SQL para criar a tabela user_secrets
    $sql = "CREATE TABLE IF NOT EXISTS user_secrets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        master_seed TEXT NOT NULL,
        session_salt VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Tabela user_secrets criada com sucesso!'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao criar tabela: ' . $conn->error
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
