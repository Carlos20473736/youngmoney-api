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

require_once __DIR__ . '/../../database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    $conn = getDbConnection();
    
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
