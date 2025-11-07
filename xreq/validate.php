<?php
/**
 * Validação Segura de XReq Token com Timestamp + Signature
 * VERSÃO FINAL - IP sempre vazio (compatível com app)
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
    
    // Validar timestamp (máximo 5 segundos de diferença)
    $now = round(microtime(true) * 1000); // Milliseconds
    $diff = abs($now - $timestamp);
    
    if ($diff > 5000) { // 5 segundos
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Token expirado'
        ]);
        exit;
    }
    
    // Validar assinatura
    // IMPORTANTE: IP sempre vazio (app não tem como saber o IP que o Railway vê)
    $ipAddress = '';  // SEMPRE VAZIO!
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $expectedSignature = md5($timestamp . XREQ_SECRET_KEY . $userAgent . $ipAddress);
    
    if (strtolower($receivedSignature) !== strtolower($expectedSignature)) {
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
        $stmt = $conn->prepare("SELECT * FROM xreq_tokens WHERE token = ? LIMIT 1");
        $stmt->bind_param("s", $receivedSignature);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $createdAt = strtotime($row['created_at']);
            $now = time();
            $diff = $now - $createdAt;
            
            // Se foi criado há menos de 2 segundos, aceitar (duplicata do Railway)
            if ($diff <= 2) {
                // Token duplicado mas recente (Railway retry) - aceitar
                $stmt->close();
                $conn->close();
                return true;
            }
            
            // Token já foi usado (replay attack)
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Token XReq já foi utilizado (possível replay attack)'
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        // Salvar token no banco
        $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, created_at) VALUES (?, NOW())");
        $stmt->bind_param("s", $receivedSignature);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao validar XReq: " . $e->getMessage());
        // Em caso de erro no banco, permitir (fail-open para não quebrar o app)
        return true;
    }
}

// Executar validação
validateXReqSecure();
?>
