<?php
/**
 * Login Endpoint - Aceita Google Token
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
    // 1. PROCESSAR REQUISIÇÃO
    $data = DecryptMiddleware::processRequest();
    
    // LOG: Dados após DecryptMiddleware
    error_log("device-login.php - Data after DecryptMiddleware: " . json_encode($data));
    
    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("device-login.php - Data from raw input: " . json_encode($data));
    }
    
    // LOG: Dados finais
    error_log("device-login.php - Final data: " . json_encode($data));
    error_log("device-login.php - google_token present: " . (isset($data['google_token']) ? 'YES' : 'NO'));
    error_log("device-login.php - google_token value: " . ($data['google_token'] ?? 'NULL'));
    
    // 2. VALIDAR DADOS - aceitar apenas google_token
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        error_log("device-login.php - ERROR: google_token is missing or empty");
        DecryptMiddleware::sendError('Google token é obrigatório');
        exit;
    }
    
    // 3. REDIRECIONAR PARA GOOGLE-LOGIN
    error_log("device-login.php - Redirecting to google-login.php");
    include __DIR__ . '/google-login.php';
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>
