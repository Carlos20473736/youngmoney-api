<?php
/**
 * Middleware para validar XReq token
 * Deve ser incluído em todos os endpoints protegidos
 */

function validateXReq() {
    $headers = getallheaders();
    $xreqToken = isset($headers['X-Req']) ? $headers['X-Req'] : null;
    
    if (!$xreqToken) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'XReq token não fornecido',
            'code' => 'XREQ_MISSING'
        ]);
        exit;
    }
    
    // Validar formato (64 caracteres hexadecimais)
    if (!preg_match('/^[a-f0-9]{64}$/', $xreqToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'XReq token inválido',
            'code' => 'XREQ_INVALID_FORMAT'
        ]);
        exit;
    }
    
    try {
        require_once __DIR__ . '/../database.php';
        $conn = getDbConnection();
        
        // Buscar token no banco
        $stmt = $conn->prepare("SELECT id, is_used, created_at FROM xreq_tokens WHERE token = ?");
        $stmt->bind_param("s", $xreqToken);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'XReq token não encontrado',
                'code' => 'XREQ_NOT_FOUND'
            ]);
            exit;
        }
        
        $tokenData = $result->fetch_assoc();
        
        // Verificar se já foi usado
        if ($tokenData['is_used']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'XReq token já foi usado',
                'code' => 'XREQ_ALREADY_USED'
            ]);
            exit;
        }
        
        // Verificar se expirou (5 minutos)
        $createdAt = strtotime($tokenData['created_at']);
        $now = time();
        $expirationTime = 5 * 60; // 5 minutos
        
        if (($now - $createdAt) > $expirationTime) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'XReq token expirado',
                'code' => 'XREQ_EXPIRED'
            ]);
            exit;
        }
        
        // Marcar como usado
        $stmt = $conn->prepare("UPDATE xreq_tokens SET is_used = TRUE, used_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $tokenData['id']);
        $stmt->execute();
        
        // Token válido
        return true;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao validar XReq: ' . $e->getMessage(),
            'code' => 'XREQ_VALIDATION_ERROR'
        ]);
        exit;
    }
}

/**
 * Função auxiliar para verificar se endpoint requer XReq
 * Endpoints públicos (login, registro) não precisam de XReq
 */
function requiresXReq() {
    $publicEndpoints = [
        '/api/v1/auth/google-login.php',
        '/api/v1/auth/device-login.php',
        '/xreq/generate.php'
    ];
    
    $currentScript = $_SERVER['SCRIPT_NAME'];
    
    foreach ($publicEndpoints as $endpoint) {
        if (strpos($currentScript, $endpoint) !== false) {
            return false;
        }
    }
    
    return true;
}
?>
