<?php
// Endpoint público para o app buscar configurações do sistema
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

date_default_timezone_set('America/Sao_Paulo');

try {
    // Conectar diretamente ao banco usando variáveis de ambiente
    $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $db_pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    $db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
    
    $conn = mysqli_init();
    
    if (!$conn) {
        throw new Exception("mysqli_init falhou");
    }
    
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    
    if (!mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL)) {
        throw new Exception("Conexão falhou: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, "utf8mb4");
    
    // Buscar horário de reset configurado
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reset_time' LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $reset_time = $row ? $row['setting_value'] : '21:00';
    
    list($reset_hour, $reset_minute) = explode(':', $reset_time);
    
    $stmt->close();
    $conn->close();
    
    // Retornar configurações
    echo json_encode([
        'success' => true,
        'data' => [
            'reset_time' => $reset_time,
            'reset_hour' => (int)$reset_hour,
            'reset_minute' => (int)$reset_minute,
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar configurações',
        'message' => $e->getMessage()
    ]);
}
?>
