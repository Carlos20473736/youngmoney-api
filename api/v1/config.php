<?php
/**
 * Endpoint Público de Configurações
 * Permite que o app Android busque configurações do sistema
 * 
 * GET /api/v1/config.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurações do banco usando variáveis de ambiente
$dbHost = getenv('DB_HOST') ?: 'mysql-2bb9a545-project-e36d.b.aivencloud.com';
$dbUser = getenv('DB_USER') ?: 'avnadmin';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'defaultdb';
$dbPort = getenv('DB_PORT') ?: 26594;

// Criar conexão MySQLi
$conn = mysqli_init();
if (!$conn) {
    throw new Exception('mysqli_init failed');
}

// Configurar SSL
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

// Conectar
$success = $conn->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort, NULL, MYSQLI_CLIENT_SSL);
if (!$success) {
    throw new Exception('Connection failed: ' . $conn->connect_error);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    // Conexão já criada acima
    
    // Buscar horário de reset
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'reset_time'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $resetTime = '21:00'; // Valor padrão
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $resetTime = $row['setting_value'];
    }
    
    // Extrair hora e minuto
    list($hour, $minute) = explode(':', $resetTime);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'reset_time' => $resetTime,
            'reset_hour' => (int)$hour,
            'reset_minute' => (int)$minute,
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
