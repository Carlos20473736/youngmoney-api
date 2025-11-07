<?php
/**
 * Validação XReq com função validateXReq()
 * Compatível com os endpoints existentes
 */

// Chave secreta (DEVE ser a mesma no app!)
define('XREQ_SECRET_KEY', 'young_money_secret_2025_v1');

/**
 * Valida o token XReq
 * @return bool True se válido, false caso contrário
 */
function validateXReq() {
    // Obter X-Req do header
    $headers = getallheaders();
    $xreqToken = $headers['X-Req'] ?? $headers['x-req'] ?? null;

    if (!$xreqToken) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Header X-Req não fornecido'
        ]);
        return false;
    }

    // Validar formato timestamp:signature
    if (!preg_match('/^(\d+):([a-f0-9]{32})$/i', $xreqToken, $matches)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato de X-Req inválido'
        ]);
        return false;
    }

    $timestamp = $matches[1];
    $receivedSignature = $matches[2];

    // Validar timestamp (máximo 30 segundos de diferença)
    $now = round(microtime(true) * 1000);
    $diff = abs($now - $timestamp);

    if ($diff > 30000) { // 30 segundos
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Token expirado',
            'debug' => [
                'now' => $now,
                'timestamp' => $timestamp,
                'diff_ms' => $diff
            ]
        ]);
        return false;
    }

    // Validar assinatura
    $ipAddress = '';  // Sempre vazio (app não envia IP)
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $expectedSignature = md5($timestamp . XREQ_SECRET_KEY . $userAgent . $ipAddress);

    if (strtolower($receivedSignature) !== strtolower($expectedSignature)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Assinatura inválida',
            'debug' => [
                'timestamp' => $timestamp,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'expected' => $expectedSignature,
                'received' => $receivedSignature
            ]
        ]);
        return false;
    }

    // XReq válido!
    return true;
}

// Validar automaticamente quando o arquivo é incluído
// Se falhar, o script para aqui
if (!validateXReq()) {
    exit;
}
?>
