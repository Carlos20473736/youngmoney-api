<?php
/**
 * Script para atualizar o horário de reset para 02:30
 */

header('Content-Type: application/json; charset=utf-8');

// Token de segurança
$token = $_GET['token'] ?? '';
if ($token !== 'ym_update_time_2024') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

try {
    require_once __DIR__ . '/database.php';
    
    $conn = getDbConnection();
    
    // Converter MySQLi para PDO
    $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $db_pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    $db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
    
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::MYSQL_ATTR_SSL_CA => false,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Atualizar horário para 02:30
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('reset_time', '02:30') 
        ON DUPLICATE KEY UPDATE setting_value = '02:30'
    ");
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Horário de reset atualizado para 02:30',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
