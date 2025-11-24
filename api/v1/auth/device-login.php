<?php
/**
 * Login Endpoint - Aceita Google Token (JSON Puro)
 * 
 * Este endpoint redireciona para google-login.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. LER JSON PURO (sem criptografia)
    $rawInput = file_get_contents('php://input');
    error_log("device-login.php - Raw input: " . $rawInput);
    
    $data = json_decode($rawInput, true);
    error_log("device-login.php - Decoded data: " . json_encode($data));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("device-login.php - JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'JSON inválido: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    // 2. VALIDAR DADOS
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        error_log("device-login.php - ERROR: google_token is missing or empty");
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Google token é obrigatório'
        ]);
        exit;
    }
    
    error_log("device-login.php - google_token received: " . substr($data['google_token'], 0, 50) . "...");
    
    // 3. REDIRECIONAR PARA GOOGLE-LOGIN
    error_log("device-login.php - Redirecting to google-login.php");
    
    // Passar dados via $_POST para o google-login.php
    $_POST = $data;
    
    include __DIR__ . '/google-login.php';
    
} catch (Exception $e) {
    error_log("device-login.php - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
