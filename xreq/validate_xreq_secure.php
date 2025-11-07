<?php
/**
 * Validação Segura de XReq Token com Timestamp + Signature
 * 
 * Este arquivo valida XReq com proteção contra scripts:
 * - Token tem formato: timestamp:signature
 * - Timestamp deve estar dentro de 5 segundos
 * - Signature = MD5(timestamp + SECRET_KEY + user_agent + ip)
 * 
 * USO:
 * require_once __DIR__ . '/validate_xreq_secure.php';
 */

// Chave secreta (DEVE ser a mesma no app Android!)
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
    
    // Validar formato: timestamp:signature
    if (!preg_match('/^(\d+):([a-f0-9]{32})$/i', $xreqToken, $matches)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato de X-Req inválido'
        ]);
        exit;
    }
    
    $timestamp = $matches[1];
    $signature = $matches[2];
    
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
                'max_ms' => 5000
            ]
        ]);
        exit;
    }
    
    // Validar assinatura
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $expectedSignature = md5($timestamp . XREQ_SECRET_KEY . $userAgent . $ipAddress);
    
    if (strtolower($signature) !== strtolower($expectedSignature)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Assinatura inválida (possível script/bot)'
        ]);
        exit;
    }
    
    try {
        require_once __DIR__ . '/database.php';
        $conn = getDbConnection();
        
        // Verificar se token já foi usado (usando a assinatura como chave única)
        $stmt = $conn->prepare("SELECT id, created_at FROM xreq_tokens WHERE token = ?");
        $stmt->bind_param("s", $signature);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Token já existe - verificar se é duplicata recente ou replay attack
            $row = $result->fetch_assoc();
            $createdAt = strtotime($row['created_at']);
            $nowSeconds = time();
            $diffSeconds = $nowSeconds - $createdAt;
            
            // Se o token foi criado há menos de 2 segundos, aceitar (duplicata de proxy)
            if ($diffSeconds <= 2) {
                // Duplicata legítima - aceitar silenciosamente
                return true;
            } else {
                // Token antigo sendo reutilizado - REPLAY ATTACK!
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Token XReq já foi utilizado (possível replay attack)',
                    'debug' => [
                        'token_age_seconds' => $diffSeconds,
                        'created_at' => $row['created_at']
                    ]
                ]);
                exit;
            }
        }
        
        // Obter user_id se tiver Bearer token
        $userId = null;
        $bearerToken = $headers['Authorization'] ?? null;
        if ($bearerToken) {
            $bearerToken = str_replace('Bearer ', '', $bearerToken);
            $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
            $stmt->bind_param("s", $bearerToken);
            $stmt->execute();
            $userResult = $stmt->get_result();
            if ($userResult->num_rows > 0) {
                $user = $userResult->fetch_assoc();
                $userId = $user['id'];
            }
        }
        
        // Salvar token no banco (usar signature como chave única)
        if ($userId === null) {
            $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, user_id, ip_address, user_agent) VALUES (?, NULL, ?, ?)");
            $stmt->bind_param("sss", $signature, $ipAddress, $userAgent);
        } else {
            $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $signature, $userId, $ipAddress, $userAgent);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao registrar XReq token: " . $stmt->error);
        }
        
        // Token válido e registrado - continuar com a requisição
        return true;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao validar XReq: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Executar validação automaticamente quando este arquivo é incluído
validateXReqSecure();
?>
