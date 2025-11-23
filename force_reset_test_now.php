<?php
/**
 * ENDPOINT DE TESTE - Força reset imediato
 * ATENÇÃO: Usar apenas para testes!
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Token de segurança
$token = $_GET['token'] ?? '';
if ($token !== 'ym_force_reset_test_2024') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
    ]);
    exit;
}

try {
    // Conectar ao banco
    require_once __DIR__ . '/database.php';
    
    $conn = getDbConnection();
    
    // Converter MySQLi para PDO para usar prepared statements
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
    
    // Buscar quantos usuários têm pontos antes do reset
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $beforeCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT SUM(daily_points) as total_points FROM users");
    $beforePoints = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'];
    
    // FORÇAR RESET - Zerar todos os pontos
    $stmt = $pdo->prepare("UPDATE users SET daily_points = 0");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    // Buscar quantos usuários têm pontos depois do reset
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $afterCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT SUM(daily_points) as total_points FROM users");
    $afterPoints = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'];
    
    // Atualizar data do último reset (usando INSERT ... ON DUPLICATE KEY UPDATE)
    $current_date = date('Y-m-d');
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('last_reset_date', ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$current_date, $current_date]);
    
    // Registrar no log
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) 
        VALUES (0, 'ranking_reset_test', ?, ?, NOW())
    ");
    $details = "Reset forçado via teste. Usuários afetados: $affected. Pontos antes: $beforePoints, depois: $afterPoints";
    $stmt->execute([$details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reset executado com sucesso!',
        'timestamp' => date('Y-m-d H:i:s'),
        'before' => [
            'users_with_points' => (int)$beforeCount,
            'total_points' => (int)$beforePoints
        ],
        'after' => [
            'users_with_points' => (int)$afterCount,
            'total_points' => (int)$afterPoints
        ],
        'affected_users' => $affected,
        'reset_date' => $current_date
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset',
        'details' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
