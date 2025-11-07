<?php
/**
 * SecureMiddleware - Middleware de Segurança Máxima V2
 * 
 * Processa requisições com:
 * - Chaves rotativas (HKDF)
 * - Validação de timestamp
 * - Validação de assinatura HMAC
 * - Descriptografia automática
 */

require_once 'SecureKeyManager.php';
require_once 'CryptoManagerV2.php';

class SecureMiddleware {
    
    /**
     * Processa requisição com segurança máxima
     * 
     * @param PDO $pdo Conexão com banco
     * @param int $userId ID do usuário autenticado
     * @return array|false Dados descriptografados ou false
     */
    public static function processSecureRequest($pdo, $userId) {
        try {
            // 1. OBTER BODY DA REQUISIÇÃO
            $input = file_get_contents('php://input');
            
            if (empty($input)) {
                error_log("SecureMiddleware: Empty request body");
                return false;
            }
            
            $data = json_decode($input, true);
            
            if ($data === null) {
                error_log("SecureMiddleware: Failed to decode JSON");
                return false;
            }
            
            // 2. VERIFICAR SE ESTÁ CRIPTOGRAFADO
            if (!isset($data['encrypted']) || $data['encrypted'] !== true) {
                error_log("SecureMiddleware: Request not encrypted");
                return false;
            }
            
            if (!isset($data['data'])) {
                error_log("SecureMiddleware: No data field in encrypted request");
                return false;
            }
            
            // 3. OBTER HEADERS DE SEGURANÇA
            $timestampWindow = $_SERVER['HTTP_X_TIMESTAMP_WINDOW'] ?? null;
            $signature = $_SERVER['HTTP_X_REQ_SIGNATURE'] ?? null;
            
            if (!$timestampWindow) {
                error_log("SecureMiddleware: Missing X-Timestamp-Window header");
                return false;
            }
            
            if (!$signature) {
                error_log("SecureMiddleware: Missing X-Req-Signature header");
                return false;
            }
            
            $timestampWindow = intval($timestampWindow);
            
            // 4. VALIDAR TIMESTAMP
            if (!SecureKeyManager::isWindowValid($timestampWindow)) {
                error_log("SecureMiddleware: Invalid timestamp window");
                return false;
            }
            
            // 5. OBTER SECRETS DO USUÁRIO
            $secrets = SecureKeyManager::getUserSecrets($pdo, $userId);
            
            if ($secrets === false) {
                error_log("SecureMiddleware: Failed to get user secrets");
                return false;
            }
            
            $masterSeed = $secrets['master_seed'];
            $sessionSalt = $secrets['session_salt'];
            
            // 6. VALIDAR ASSINATURA HMAC
            $bodyForSignature = $data['data']; // Dados criptografados
            
            $isValidSignature = SecureKeyManager::validateHMAC(
                $bodyForSignature,
                $signature,
                $masterSeed,
                $sessionSalt,
                $timestampWindow
            );
            
            if (!$isValidSignature) {
                error_log("SecureMiddleware: Invalid HMAC signature");
                return false;
            }
            
            // 7. DESCRIPTOGRAFAR COM CHAVE ROTATIVA
            $decrypted = CryptoManagerV2::decryptAuto(
                $data['data'],
                $masterSeed,
                $sessionSalt,
                $timestampWindow
            );
            
            if ($decrypted === false) {
                error_log("SecureMiddleware: Failed to decrypt request");
                return false;
            }
            
            // 8. DECODIFICAR JSON DESCRIPTOGRAFADO
            $decryptedData = json_decode($decrypted, true);
            
            if ($decryptedData === null) {
                error_log("SecureMiddleware: Failed to decode decrypted JSON");
                return false;
            }
            
            error_log("SecureMiddleware: Request processed successfully (V2 - rotating keys)");
            return $decryptedData;
            
        } catch (Exception $e) {
            error_log("SecureMiddleware: Exception - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia resposta criptografada com segurança máxima
     * 
     * @param array $response Dados da resposta
     * @param PDO $pdo Conexão com banco
     * @param int $userId ID do usuário
     */
    public static function sendSecureResponse($response, $pdo, $userId) {
        header('Content-Type: application/json');
        
        try {
            // 1. OBTER SECRETS DO USUÁRIO
            $secrets = SecureKeyManager::getUserSecrets($pdo, $userId);
            
            if ($secrets === false) {
                error_log("SecureMiddleware: Failed to get user secrets for response");
                echo json_encode($response); // Fallback sem criptografia
                return;
            }
            
            $masterSeed = $secrets['master_seed'];
            $sessionSalt = $secrets['session_salt'];
            
            // 2. CRIPTOGRAFAR RESPOSTA COM CHAVE ROTATIVA ATUAL
            $jsonResponse = json_encode($response);
            $encrypted = CryptoManagerV2::encrypt($jsonResponse, $masterSeed, $sessionSalt);
            
            if ($encrypted === false) {
                error_log("SecureMiddleware: Failed to encrypt response");
                echo json_encode($response); // Fallback sem criptografia
                return;
            }
            
            // 3. ENVIAR RESPOSTA CRIPTOGRAFADA
            echo json_encode([
                'encrypted' => true,
                'data' => $encrypted
            ]);
            
            error_log("SecureMiddleware: Response encrypted successfully (V2 - rotating keys)");
            
        } catch (Exception $e) {
            error_log("SecureMiddleware: Exception sending response - " . $e->getMessage());
            echo json_encode($response); // Fallback sem criptografia
        }
    }
    
    /**
     * Envia erro em JSON (não criptografado para facilitar debug)
     * 
     * @param string $message Mensagem de erro
     * @param int $httpCode Código HTTP
     */
    public static function sendError($message, $httpCode = 400) {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
    
    /**
     * Envia sucesso em JSON criptografado
     * 
     * @param mixed $data Dados de sucesso
     * @param PDO $pdo Conexão com banco
     * @param int $userId ID do usuário
     */
    public static function sendSuccess($data, $pdo, $userId) {
        self::sendSecureResponse([
            'success' => true,
            'data' => $data
        ], $pdo, $userId);
    }
}

?>
