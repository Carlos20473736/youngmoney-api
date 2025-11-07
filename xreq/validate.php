<?php
/**
 * Validação de XReq Token
 * 
 * Este arquivo deve ser incluído no início de cada endpoint que requer XReq
 * Valida que o token foi enviado, tem formato correto e não foi usado antes
 * 
 * IMPORTANTE: Aceita tokens duplicados dentro de 2 segundos para lidar com
 * duplicação de requisições por proxies/load balancers (ex: Railway)
 * 
 * USO:
 * require_once __DIR__ . '/../xreq/validate.php';
 */

// Função para validar XReq
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
        exit;
    }
    
    // Validar formato (32 ou 64 caracteres hexadecimais)
    if (!preg_match('/^[a-f0-9]{32,64}$/i', $xreqToken)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato de X-Req inválido'
        ]);
        exit;
    }
    
    try {
        require_once __DIR__ . '/../database.php';
        $conn = getDbConnection();
        
        // Verificar se token já foi usado
        $stmt = $conn->prepare("SELECT id, created_at FROM xreq_tokens WHERE token = ?");
        $stmt->bind_param("s", $xreqToken);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Token já existe - verificar se é duplicata recente ou replay attack
            $row = $result->fetch_assoc();
            $createdAt = strtotime($row['created_at']);
            $now = time();
            $diff = $now - $createdAt;
            
            // Se o token foi criado há menos de 2 segundos, aceitar (duplicata de proxy)
            if ($diff <= 2) {
                // Duplicata legítima - aceitar silenciosamente
                return true;
            } else {
                // Token antigo sendo reutilizado - REPLAY ATTACK!
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Token XReq já foi utilizado (possível replay attack)',
                    'debug' => [
                        'token_age_seconds' => $diff,
                        'created_at' => $row['created_at']
                    ]
                ]);
                exit;
            }
        }
        
        // Obter informações da requisição
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
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
        
        // Salvar token no banco (marcar como usado)
        if ($userId === null) {
            $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, user_id, ip_address, user_agent) VALUES (?, NULL, ?, ?)");
            $stmt->bind_param("sss", $xreqToken, $ipAddress, $userAgent);
        } else {
            $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $xreqToken, $userId, $ipAddress, $userAgent);
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
validateXReq();
?>
