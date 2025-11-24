<?php
/**
 * Security Validation Helper
 * Função helper para validar headers de segurança em qualquer endpoint
 */

require_once __DIR__ . '/xreq_manager.php';

/**
 * Valida headers de segurança obrigatórios
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
    
    // Headers obrigatórios
    $requiredHeaders = [
        'X-REQ',
        'X-FULL-REQUEST-HASH',
        'X-DEVICE-MODEL',
        'X-PLATFORM-VERSION',
        'X-REQUEST-WINDOW'
    ];
    
    // Verificar presença de todos os headers obrigatórios
    foreach ($requiredHeaders as $requiredHeader) {
        if (!isset($allHeaders[$requiredHeader]) || empty($allHeaders[$requiredHeader])) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => "Missing required security header: $requiredHeader",
                'code' => 'MISSING_SECURITY_HEADER'
            ]);
            exit;
        }
    }
    
    // Validar X-REQ (token rotativo)
    $xReq = $allHeaders['X-REQ'];
    try {
        validateXReqToken($conn, $user, $xReq);
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'X-REQ validation failed: ' . $e->getMessage(),
            'code' => 'INVALID_XREQ_TOKEN'
        ]);
        exit;
    }
    
    // Validar X-FULL-REQUEST-HASH (formato SHA256)
    $fullRequestHash = $allHeaders['X-FULL-REQUEST-HASH'];
    if (!preg_match('/^[a-f0-9]{64}$/i', $fullRequestHash)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-FULL-REQUEST-HASH format (must be SHA256)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-DEVICE-MODEL (não vazio, máx 100 chars)
    $deviceModel = $allHeaders['X-DEVICE-MODEL'];
    if (strlen($deviceModel) > 100) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-DEVICE-MODEL (max 100 characters)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-PLATFORM-VERSION (não vazio, máx 50 chars)
    $platformVersion = $allHeaders['X-PLATFORM-VERSION'];
    if (strlen($platformVersion) > 50) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-PLATFORM-VERSION (max 50 characters)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-REQUEST-WINDOW (deve ser numérico)
    $requestWindow = $allHeaders['X-REQUEST-WINDOW'];
    if (!is_numeric($requestWindow)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-REQUEST-WINDOW (must be numeric)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Log para debug
    error_log("[SECURITY] Headers validated - Hash: $fullRequestHash, Device: $deviceModel, Platform: $platformVersion, Window: $requestWindow");
    
    // Salvar métricas de segurança (opcional)
    try {
        $stmt = $conn->prepare("INSERT INTO security_metrics (user_id, headers_count, device_model, platform_version, request_window, full_request_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $headersCount = count($allHeaders);
        $userId = $user['id'];
        $stmt->bind_param("iissis", $userId, $headersCount, $deviceModel, $platformVersion, $requestWindow, $fullRequestHash);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Não bloquear se falhar ao salvar métricas
        error_log("[SECURITY] Failed to save metrics: " . $e->getMessage());
    }
    
    return [
        'valid' => true,
        'score' => 100,
        'message' => 'Security headers validated',
        'headers_validated' => $requiredHeaders,
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
