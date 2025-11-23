<?php
/**
 * FORCE DATE CHANGE - Apenas para testes
 * Muda o last_reset_date para ontem para simular um novo dia
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$token = $_GET['token'] ?? '';
if ($token !== 'ym_auto_reset_2024_secure_xyz') {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Token inválido']));
}

require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    date_default_timezone_set('America/Sao_Paulo');
    
    // Mudar para ontem
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'last_reset_date'");
    $stmt->bind_param('s', $yesterday);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Data alterada para ontem',
        'last_reset_date' => $yesterday,
        'current_date' => date('Y-m-d')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
