<?php
/**
 * Endpoint Público de Configurações com Criptografia
 * Permite que o app Android busque configurações do sistema
 * 
 * GET /api/v1/config.php
 * 
 * Suporta:
 * - Requisições criptografadas (X-Req header)
 * - Respostas criptografadas
 * - Fallback para JSON não criptografado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/DecryptMiddleware.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        DecryptMiddleware::sendError('Método não permitido', 405);
        exit;
    }
    
    // Conectar ao banco
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
    
    // Enviar resposta criptografada
    DecryptMiddleware::sendSuccess([
        'reset_time' => $resetTime,
        'reset_hour' => (int)$hour,
        'reset_minute' => (int)$minute,
        'timezone' => 'America/Sao_Paulo'
    ], true); // true = criptografar resposta
    
} catch (Exception $e) {
    error_log("Config endpoint error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro ao buscar configurações: ' . $e->getMessage(), 500);
}
?>
