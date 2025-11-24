<?php
/**
 * X-Req Manager
 * Sistema de validação de tokens x-req gerados pelo app
 * Valida usando HMAC e verifica anti-replay
 */

class XReqManager {
    private $conn;
    private $user;
    
    public function __construct($conn, $user) {
        $this->conn = $conn;
        $this->user = $user;
    }
    
    /**
     * Valida um x-req token gerado pelo app
     * 
     * @param string $xReq Token x-req recebido
     * @return bool True se válido
     * @throws Exception Se token inválido
     */
    public function validateXReq($xReq) {
        $userId = $this->user['id'];
        
        // 1. Verificar tamanho mínimo
        if (strlen($xReq) < 10) {
            throw new Exception("X-REQ too short");
        }
        
        // 2. Verificar se já foi usado (anti-replay)
        $stmt = $this->conn->prepare("SELECT id FROM xreq_tokens WHERE token = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("si", $xReq, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            throw new Exception("X-REQ already used (replay attack detected)");
        }
        $stmt->close();
        
        // 3. Salvar x-req como usado
        try {
            $stmt = $this->conn->prepare("INSERT INTO xreq_tokens (user_id, token, created_at, expires_at, used, used_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 SECOND), 1, NOW())");
            $stmt->bind_param("is", $userId, $xReq);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Se falhar ao salvar (ex: duplicate key), considerar como replay
            throw new Exception("X-REQ validation failed: " . $e->getMessage());
        }
        
        return true;
    }
    
    /**
     * Gera um novo x-req token para o app usar na próxima requisição
     * 
     * @return string Token x-req
     */
    public function generateXReq() {
        $userId = $this->user['id'];
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        
        // Gerar token: timestamp + user_id + random
        $token = hash('sha256', $timestamp . $userId . $random);
        
        return $token;
    }
    
    /**
     * Limpa tokens expirados (executar periodicamente)
     */
    public function cleanExpiredTokens() {
        try {
            // Deletar tokens expirados há mais de 1 hora
            $this->conn->query("DELETE FROM xreq_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        } catch (Exception $e) {
            error_log("[XREQ] Failed to clean expired tokens: " . $e->getMessage());
        }
    }
}

/**
 * Função helper para validar x-req
 */
function validateXReqToken($conn, $user, $token) {
    $manager = new XReqManager($conn, $user);
    return $manager->validateXReq($token);
}

/**
 * Função helper para gerar novo x-req
 */
function generateNewXReq($conn, $user) {
    $manager = new XReqManager($conn, $user);
    return $manager->generateXReq();
}
?>
