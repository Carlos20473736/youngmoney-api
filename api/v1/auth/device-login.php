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
    
    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    // 2. VALIDAR DADOS - aceitar apenas google_token
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        DecryptMiddleware::sendError('Google token é obrigatório');
        exit;
    }
    
    // 3. REDIRECIONAR PARA GOOGLE-LOGIN
    include __DIR__ . '/google-login.php';
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>
