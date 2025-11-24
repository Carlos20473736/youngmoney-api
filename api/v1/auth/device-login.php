<?php
/**
 * Login Endpoint - Aceita Google Token (Criptografado ou JSON Puro)
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

require_once __DIR__ . '/../../../includes/DecryptMiddleware.php';

try {
    // 1. LER RAW INPUT
    $rawInput = file_get_contents('php://input');
    error_log("device-login.php - Raw input: " . substr($rawInput, 0, 200) . "...");
    
    $rawData = json_decode($rawInput, true);
    
    // 2. VERIFICAR SE É REQUISIÇÃO CRIPTOGRAFADA
    if (isset($rawData['encrypted']) && $rawData['encrypted'] === true && isset($rawData['data'])) {
        error_log("device-login.php - Detected encrypted request, calling DecryptMiddleware");
        
        // Chamar DecryptMiddleware para descriptografar
        $data = DecryptMiddleware::processRequest();
        
        if (empty($data)) {
            error_log("device-login.php - DecryptMiddleware returned empty data");
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Falha ao descriptografar requisição'
            ]);
            exit;
        }
        
        error_log("device-login.php - Data after decryption: " . json_encode($data));
        
    } else {
        // 3. JSON PURO (não criptografado)
        error_log("device-login.php - Plain JSON request");
        $data = $rawData;
    }
    
    // 4. VALIDAR DADOS
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        error_log("device-login.php - ERROR: google_token is missing or empty");
        error_log("device-login.php - Available keys: " . implode(', ', array_keys($data ?: [])));
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Google token é obrigatório'
        ]);
        exit;
    }
    
    error_log("device-login.php - google_token received: " . substr($data['google_token'], 0, 50) . "...");
    
    // 5. REDIRECIONAR PARA GOOGLE-LOGIN
    error_log("device-login.php - Redirecting to google-login.php");
    
    // Passar dados via $_POST para o google-login.php
    $_POST = $data;
    $_POST['_is_encrypted'] = isset($rawData['encrypted']) && $rawData['encrypted'] === true;
    
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
