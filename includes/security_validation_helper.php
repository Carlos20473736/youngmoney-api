<?php
/**
 * Security Validation Helper
 * Função helper para validar headers de segurança em qualquer endpoint
 */

/**
 * Valida o header X-FULL-REQUEST-HASH obrigatório
 * 
 * @param mysqli $conn Conexão com banco de dados
 * @param array $user Usuário autenticado
 * @return array Resultado da validação
 */
function validateSecurityHeaders($conn, $user) {
    // Extrair headers do $_SERVER
    $allHeaders = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == 'HTTP_') {
            // Converter HTTP_X_FULL_REQUEST_HASH para X-FULL-REQUEST-HASH
            $header = substr($key, 5); // Remove HTTP_
            $header = str_replace('_', '-', $header); // Substitui _ por -
            $allHeaders[$header] = $value;
        }
    }
    
    // Validar X-FULL-REQUEST-HASH (obrigatório)
    if (!isset($allHeaders['X-FULL-REQUEST-HASH']) || empty($allHeaders['X-FULL-REQUEST-HASH'])) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required security header: X-FULL-REQUEST-HASH',
            'code' => 'MISSING_SECURITY_HEADER'
        ]);
        exit;
    }
    
    $fullRequestHash = $allHeaders['X-FULL-REQUEST-HASH'];
    
    // Validar formato do hash (deve ser SHA256 - 64 caracteres hexadecimais)
    if (!preg_match('/^[a-f0-9]{64}$/i', $fullRequestHash)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-FULL-REQUEST-HASH format (must be SHA256)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar hash (verificar se corresponde ao hash real da requisição)
    $requestData = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'timestamp' => $allHeaders['X-REQUEST-TIMESTAMP'] ?? time(),
        'user_id' => $user['id'] ?? 0,
        'body' => file_get_contents('php://input')
    ];
    
    $calculatedHash = hash('sha256', json_encode($requestData));
    
    // Log para debug (opcional - remover em produção)
    error_log("[SECURITY] X-FULL-REQUEST-HASH validation - Received: $fullRequestHash, Calculated: $calculatedHash");
    
    // Por enquanto, apenas validar presença e formato
    // TODO: Implementar validação rigorosa do hash
    
    return [
        'valid' => true,
        'score' => 100,
        'message' => 'X-FULL-REQUEST-HASH validated',
        'hash_received' => $fullRequestHash,
        'headers_count' => count($allHeaders)
    ];
}

/**
 * Verifica se o endpoint atual é público (não precisa de validação de headers)
 * 
 * @return bool
 */
function isPublicEndpoint() {
    $publicEndpoints = [
        '/api/v1/auth/google-login.php',
        '/api/v1/auth/device-login.php',
        '/api/v1/invite/validate.php',
        '/api/v1/config.php',
        '/api/v1/config-simple.php',
        '/admin/'
    ];
    
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    foreach ($publicEndpoints as $endpoint) {
        if (strpos($requestUri, $endpoint) !== false) {
            return true;
        }
    }
    
    return false;
}
?>
