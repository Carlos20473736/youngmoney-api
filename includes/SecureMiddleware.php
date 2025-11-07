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
    
    /**
     * Processa requisição automaticamente (compatível com DecryptMiddleware)
     * Extrai token do header Authorization e busca userId
     * 
     * @return array|false Dados descriptografados ou false
     */
    public static function processRequest() {
        require_once __DIR__ . '/../database.php';
        
        try {
            // Obter token do header
            $headers = getallheaders();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            
            if (!$token) {
                error_log("SecureMiddleware: No authorization token");
                self::sendError("Token de autorização não fornecido", 401);
                exit;
            }
            
            // Buscar userId pelo token
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT id, master_seed FROM users WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                error_log("SecureMiddleware: Invalid token");
                return false;
            }
            
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            
            // Verificar se tem seed (V2)
            if (empty($user['master_seed'])) {
                error_log("SecureMiddleware: No seed found for user");
                self::sendError("Usuário sem seed de segurança. Faça login novamente.", 401);
                exit;
            }
            
            // Converter conexão mysqli para PDO
            $pdo = new PDO(
                "mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME'),
                getenv('DB_USER'),
                getenv('DB_PASSWORD'),
                [
                    PDO::MYSQL_ATTR_SSL_CA => true,
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
            
            // Processar com V2
            return self::processSecureRequest($pdo, $userId);
            
        } catch (Exception $e) {
            error_log("SecureMiddleware::processRequest: " . $e->getMessage());
            self::sendError("Erro ao processar requisição: " . $e->getMessage(), 500);
            exit;
        }
    }
    
    /**
     * Envia resposta de sucesso automaticamente (compatível com DecryptMiddleware)
     * 
     * @param mixed $data Dados de sucesso
     * @param bool $encrypt Se deve criptografar (true) ou não (false)
     */
    public static function sendSuccessAuto($data, $encrypt = true) {
        require_once __DIR__ . '/../database.php';
        
        if (!$encrypt) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            return;
        }
        
        try {
            // Obter token do header
            $headers = getallheaders();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            
            if (!$token) {
                error_log("SecureMiddleware: No authorization token for response");
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Não autorizado']);
                return;
            }
            
            // Buscar userId pelo token
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT id, master_seed FROM users WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                error_log("SecureMiddleware: Invalid token for response");
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido']);
                return;
            }
            
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            
            // Verificar se tem seed (V2)
            if (empty($user['master_seed'])) {
                error_log("SecureMiddleware: No seed for response");
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário sem seed. Faça login novamente.']);
                return;
            }
            
            // Converter conexão mysqli para PDO
            $pdo = new PDO(
                "mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME'),
                getenv('DB_USER'),
                getenv('DB_PASSWORD'),
                [
                    PDO::MYSQL_ATTR_SSL_CA => true,
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
            
            // Enviar com V2
            self::sendSuccess($data, $pdo, $userId);
            
        } catch (Exception $e) {
            error_log("SecureMiddleware::sendSuccessAuto: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao enviar resposta']);
        }
    }
}

?>
