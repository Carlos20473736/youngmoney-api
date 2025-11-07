<?php
/**
 * Validação XReq Simplificada
 * SEM banco de dados - Apenas valida timestamp e assinatura
 */

// Chave secreta (DEVE ser a mesma no app!)
define('XREQ_SECRET_KEY', 'young_money_secret_2025_v1');

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
        'error' => 'Formato de X-Req inválido'
    ]);
    exit;
}

$timestamp = $matches[1];
$receivedSignature = $matches[2];

// Validar timestamp (máximo 30 segundos de diferença - mais permissivo)
$now = round(microtime(true) * 1000);
$diff = abs($now - $timestamp);

if ($diff > 30000) { // 30 segundos
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Token expirado'
    ]);
    exit;
}

// Validar assinatura
$ipAddress = '';  // Sempre vazio (app não envia IP)
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$expectedSignature = md5($timestamp . XREQ_SECRET_KEY . $userAgent . $ipAddress);

if (strtolower($receivedSignature) !== strtolower($expectedSignature)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Assinatura inválida'
    ]);
    exit;
}

// XReq válido! Continuar com o endpoint
?>
