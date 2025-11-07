<?php
/**
 * Validação XReq com DEBUG
 * Use este arquivo temporariamente para ver por que as assinaturas não batem
 */

// Chave secreta (DEVE ser a mesma no app!)
define('XREQ_SECRET_KEY', 'young_money_secret_2025_v1');

function validateXReqSecure() {
    // Obter X-Req do header
    $headers = getallheaders();
    $xreqToken = $headers['X-Req'] ?? $headers['x-req'] ?? null;
    
    if (!$xreqToken) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Header X-Req não fornecido'
        ]);
        exit;
    }
    
    // Validar formato timestamp:signature
    if (!preg_match('/^(\d+):([a-f0-9]{32})$/i', $xreqToken, $matches)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato de X-Req inválido',
            'debug' => [
                'received' => $xreqToken,
                'expected_format' => 'timestamp:signature'
            ]
        ]);
        exit;
    }
    
    $timestamp = $matches[1];
    $receivedSignature = $matches[2];
    
    // Validar timestamp (máximo 5 segundos de diferença)
    $now = round(microtime(true) * 1000); // Milliseconds
    $diff = abs($now - $timestamp);
    
    if ($diff > 5000) { // 5 segundos
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Token expirado',
            'debug' => [
                'diff_ms' => $diff,
                'max_ms' => 5000,
                'timestamp' => $timestamp,
                'now' => $now
            ]
        ]);
        exit;
    }
    
    // Obter dados para assinatura
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Calcular assinatura esperada
    $dataToSign = $timestamp . XREQ_SECRET_KEY . $userAgent . $ipAddress;
    $expectedSignature = md5($dataToSign);
    
    // DEBUG: Mostrar todos os dados
    $debugInfo = [
        'timestamp' => $timestamp,
        'secret_key' => XREQ_SECRET_KEY,
        'user_agent' => $userAgent,
        'ip_address' => $ipAddress,
        'data_to_sign' => $dataToSign,
        'data_to_sign_length' => strlen($dataToSign),
        'expected_signature' => $expectedSignature,
        'received_signature' => $receivedSignature,
        'match' => (strtolower($receivedSignature) === strtolower($expectedSignature))
    ];
    
    if (strtolower($receivedSignature) !== strtolower($expectedSignature)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Assinatura inválida (possível script/bot)',
            'debug' => $debugInfo
        ]);
        exit;
    }
    
    // Sucesso!
    echo json_encode([
        'success' => true,
        'message' => 'XReq válido',
        'debug' => $debugInfo
    ]);
    exit;
}

// Executar validação
validateXReqSecure();
?>
