<?php
/**
 * X-Req Manager
 * Sistema de geração e validação de tokens rotativos x-req
 * Cada token só pode ser usado uma vez (anti-replay)
 */

class XReqManager {
    private $conn;
    private $user;
    
    public function __construct($conn, $user) {
        $this->conn = $conn;
        $this->user = $user;
    }
    
    /**
     * Gera um novo x-req token
     * 
     * @return string Token x-req
     */
    public function generateXReq() {
        $userId = $this->user['id'];
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        
        // Gerar token: timestamp + user_id + random
        $token = hash('sha256', $timestamp . $userId . $random);
        
        // Salvar no banco com timestamp de expiração (90 segundos)
        $expiresAt = date('Y-m-d H:i:s', $timestamp + 90);
        
        try {
            $stmt = $this->conn->prepare("INSERT INTO xreq_tokens (user_id, token, created_at, expires_at, used) VALUES (?, ?, NOW(), ?, 0)");
            $stmt->bind_param("iss", $userId, $token, $expiresAt);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("[XREQ] Failed to save token: " . $e->getMessage());
        }
        
        return $token;
    }
    
    /**
     * Valida um x-req token
     * 
     * @param string $token Token x-req recebido
     * @return bool True se válido, False se inválido
     * @throws Exception Se token inválido
     */
    public function validateXReq($token) {
        $userId = $this->user['id'];
        
        // Verificar se token existe, não foi usado, não expirou e pertence ao usuário
        $stmt = $this->conn->prepare("
            SELECT id, used, expires_at 
            FROM xreq_tokens 
            WHERE token = ? AND user_id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("si", $token, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            throw new Exception("X-REQ token not found or invalid");
        }
        
        $row = $result->fetch_assoc();
        $tokenId = $row['id'];
        $used = $row['used'];
        $expiresAt = $row['expires_at'];
        $stmt->close();
        
        // Verificar se já foi usado
        if ($used == 1) {
            throw new Exception("X-REQ token already used (replay attack detected)");
        }
        
        // Verificar se expirou
        if (strtotime($expiresAt) < time()) {
            throw new Exception("X-REQ token expired");
        }
        
        // Marcar como usado
        $stmt = $this->conn->prepare("UPDATE xreq_tokens SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $tokenId);
        $stmt->execute();
        $stmt->close();
        
        return true;
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
 * Função helper para gerar novo x-req
 */
function generateNewXReq($conn, $user) {
    $manager = new XReqManager($conn, $user);
    return $manager->generateXReq();
}

/**
 * Função helper para validar x-req
 */
function validateXReqToken($conn, $user, $token) {
    $manager = new XReqManager($conn, $user);
    return $manager->validateXReq($token);
}
?>
