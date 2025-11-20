<?php
/**
 * Endpoint de Teste - Config Completo (SEM criptografia)
 * Apenas para verificar se prize_values está sendo retornado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('America/Sao_Paulo');

try {
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
    
    // Buscar horário de reset
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reset_time' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $reset_time = $row ? $row['setting_value'] : '21:00';
    list($reset_hour, $reset_minute) = explode(':', $reset_time);
    $stmt->close();
    
    // Buscar valores rápidos de saque
    $stmt = $conn->prepare("SELECT value_amount FROM withdrawal_quick_values WHERE is_active = 1 ORDER BY value_amount ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $quick_values = [];
    while ($row = $result->fetch_assoc()) {
        $quick_values[] = (int)$row['value_amount'];
    }
    if (empty($quick_values)) {
        $quick_values = [10, 20, 50, 100, 200, 500];
    }
    $stmt->close();
    
    // Buscar valores dos prêmios da roleta
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM roulette_settings WHERE setting_key LIKE 'prize_%' ORDER BY setting_key ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $prize_values = [];
    while ($row = $result->fetch_assoc()) {
        $prize_values[] = (int)$row['setting_value'];
    }
    if (empty($prize_values)) {
        $prize_values = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    }
    $stmt->close();
    
    $conn->close();
    
    // Retornar SEM criptografia para teste
    echo json_encode([
        'success' => true,
        'data' => [
            'reset_time' => $reset_time,
            'reset_hour' => (int)$reset_hour,
            'reset_minute' => (int)$reset_minute,
            'timezone' => 'America/Sao_Paulo',
            'quick_withdrawal_values' => $quick_values,
            'prize_values' => $prize_values
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
