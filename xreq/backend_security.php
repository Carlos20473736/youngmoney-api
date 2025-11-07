<?php
/**
 * Backend Security System - Young Money API
 * 
 * Sistema de segurança máxima:
 * - Chave NUNCA transmitida
 * - Derivação HKDF
 * - Validação temporal
 * - Assinatura HMAC
 */

class SecureKeyManager {
    
    // Janela temporal (30 segundos)
    const WINDOW_SIZE_MS = 30000;
    
    // Tolerância de janelas (±1 = 90 segundos total)
    const WINDOW_TOLERANCE = 1;
    
    /**
     * Deriva chave usando HKDF-SHA256
     * 
     * @param string $masterSeed Seed mestre (base64)
     * @param string $sessionSalt Salt da sessão (base64)
     * @param int $timestampWindow Janela temporal
     * @return string Chave derivada (32 bytes, binário)
     */
    public static function deriveKey($masterSeed, $sessionSalt, $timestampWindow) {
        // Decodificar base64
        $seedBytes = base64_decode($masterSeed);
        $saltBytes = base64_decode($sessionSalt);
        
        // Info = "youngmoney" + timestamp_window
        $info = "youngmoney_v2_" . $timestampWindow;
        
        // HKDF Extract: PRK = HMAC-SHA256(salt, seed)
        $prk = hash_hmac('sha256', $seedBytes, $saltBytes, true);
        
        // HKDF Expand: OKM = HMAC-SHA256(PRK, info || 0x01)
        $okm = hash_hmac('sha256', $info . chr(1), $prk, true);
        
        // Retornar 32 bytes (AES-256)
        return substr($okm, 0, 32);
    }
    
    /**
     * Calcula janela temporal atual
     * 
     * @return int Número da janela temporal
     */
    public static function getCurrentTimestampWindow() {
        return intval(floor(microtime(true) * 1000) / self::WINDOW_SIZE_MS);
    }
    
    /**
     * Valida se janela temporal está dentro do intervalo válido
     * 
     * @param int $window Janela a validar
     * @return bool true se válida
     */
    public static function isWindowValid($window) {
        $currentWindow = self::getCurrentTimestampWindow();
        $diff = abs($currentWindow - $window);
        return $diff <= self::WINDOW_TOLERANCE;
    }
    
    /**
     * Gera master seed aleatório
     * 
     * @return string Seed em base64 (256 bits)
     */
    public static function generateMasterSeed() {
        return base64_encode(random_bytes(32));
    }
    
    /**
     * Gera session salt aleatório
     * 
     * @return string Salt em base64 (128 bits)
     */
    public static function generateSessionSalt() {
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Criptografa seed com senha do usuário
     * 
     * @param string $seed Seed a criptografar
     * @param string $userPassword Senha do usuário
     * @return string Seed criptografado em base64
     */
    public static function encryptSeedWithPassword($seed, $userPassword) {
        // Derivar chave da senha usando SHA-256
        $passwordKey = hash('sha256', $userPassword, true);
        
        // Gerar IV aleatório
        $iv = random_bytes(16);
        
        // Criptografar usando AES-256-CBC
        $encrypted = openssl_encrypt(
            $seed,
            'AES-256-CBC',
            $passwordKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Concatenar IV + dados criptografados
        $combined = $iv . $encrypted;
        
        return base64_encode($combined);
    }
}

class CryptoManager {
    
    /**
     * Criptografa dados usando chave derivada
     * 
     * @param string $data Dados a criptografar (JSON)
     * @param string $masterSeed Seed mestre
     * @param string $sessionSalt Salt da sessão
     * @param int $timestampWindow Janela temporal
     * @return string Dados criptografados em base64
     */
    public static function encrypt($data, $masterSeed, $sessionSalt, $timestampWindow) {
        // Derivar chave
        $key = SecureKeyManager::deriveKey($masterSeed, $sessionSalt, $timestampWindow);
        
        // Gerar IV aleatório
        $iv = random_bytes(16);
        
        // Criptografar usando AES-256-CBC
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Concatenar IV + dados criptografados
        $combined = $iv . $encrypted;
        
        return base64_encode($combined);
    }
    
    /**
     * Descriptografa dados usando chave derivada
     * 
     * @param string $encryptedData Dados criptografados em base64
     * @param string $masterSeed Seed mestre
     * @param string $sessionSalt Salt da sessão
     * @param int $timestampWindow Janela temporal
     * @return string|false Dados descriptografados (JSON) ou false
     */
    public static function decrypt($encryptedData, $masterSeed, $sessionSalt, $timestampWindow) {
        // Validar janela temporal
        if (!SecureKeyManager::isWindowValid($timestampWindow)) {
            error_log("Janela temporal inválida: " . $timestampWindow);
            return false;
        }
        
        // Derivar chave
        $key = SecureKeyManager::deriveKey($masterSeed, $sessionSalt, $timestampWindow);
        
        // Decodificar base64
        $combined = base64_decode($encryptedData);
        
        // Extrair IV (primeiros 16 bytes)
        $iv = substr($combined, 0, 16);
        $encrypted = substr($combined, 16);
        
        // Descriptografar usando AES-256-CBC
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted;
    }
    
    /**
     * Descriptografa tentando janela atual e adjacentes
     * 
     * @param string $encryptedData Dados criptografados
     * @param string $masterSeed Seed mestre
     * @param string $sessionSalt Salt da sessão
     * @param int $timestampWindow Janela temporal do header
     * @return string|false Dados descriptografados ou false
     */
    public static function decryptAuto($encryptedData, $masterSeed, $sessionSalt, $timestampWindow) {
        // Tentar janela informada
        $result = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $timestampWindow);
        if ($result !== false) return $result;
        
        // Tentar janela anterior
        $result = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $timestampWindow - 1);
        if ($result !== false) return $result;
        
        // Tentar próxima janela
        $result = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $timestampWindow + 1);
        if ($result !== false) return $result;
        
        error_log("Falha ao descriptografar em todas as janelas válidas");
        return false;
    }
    
    /**
     * Gera assinatura HMAC
     * 
     * @param string $data Dados a assinar
     * @param string $masterSeed Seed mestre
     * @param string $sessionSalt Salt da sessão
     * @param int $timestampWindow Janela temporal
     * @return string Assinatura em base64
     */
    public static function generateHMAC($data, $masterSeed, $sessionSalt, $timestampWindow) {
        $key = SecureKeyManager::deriveKey($masterSeed, $sessionSalt, $timestampWindow);
        $signature = hash_hmac('sha256', $data, $key, true);
        return base64_encode($signature);
    }
    
    /**
     * Valida assinatura HMAC
     * 
     * @param string $data Dados originais
     * @param string $signature Assinatura recebida (base64)
     * @param string $masterSeed Seed mestre
     * @param string $sessionSalt Salt da sessão
     * @param int $timestampWindow Janela temporal
     * @return bool true se válida
     */
    public static function validateHMAC($data, $signature, $masterSeed, $sessionSalt, $timestampWindow) {
        $expectedSignature = self::generateHMAC($data, $masterSeed, $sessionSalt, $timestampWindow);
        return hash_equals($expectedSignature, $signature);
    }
}

/**
 * Exemplo de uso no endpoint de login
 */
function handleLogin($pdo) {
    // Obter dados da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['encrypted']) || !$data['encrypted']) {
        http_response_code(400);
        echo json_encode(['error' => 'Request must be encrypted']);
        return;
    }
    
    // Para login, usar chave temporária (primeira requisição sem seed)
    // Ou descriptografar com chave pública/privada
    // Aqui vamos assumir que conseguimos os dados
    
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    // Validar credenciais
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    // Gerar novo master seed e session salt
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    
    // Criptografar seed com senha do usuário
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $password);
    
    // Armazenar no banco (seed criptografado com chave do servidor)
    $serverKey = getenv('SERVER_ENCRYPTION_KEY'); // Chave do servidor (env var)
    $seedForDB = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, substr(md5($user['id']), 0, 16));
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET master_seed = ?, session_salt = ?, salt_updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$seedForDB, $sessionSalt, $user['id']]);
    
    // Gerar JWT
    $jwt = generateJWT($user['id']);
    
    // Preparar resposta
    $response = [
        'success' => true,
        'jwt' => $jwt,
        'encrypted_seed' => $encryptedSeed,
        'session_salt' => $sessionSalt,
        'timestamp' => time()
    ];
    
    // Criptografar resposta
    $currentWindow = SecureKeyManager::getCurrentTimestampWindow();
    $encryptedResponse = CryptoManager::encrypt(
        json_encode($response),
        $masterSeed,
        $sessionSalt,
        $currentWindow
    );
    
    echo json_encode([
        'encrypted' => true,
        'data' => $encryptedResponse
    ]);
}

/**
 * Exemplo de uso em endpoint protegido
 */
function handleProtectedEndpoint($pdo, $userId) {
    // Obter headers
    $timestampWindow = intval($_SERVER['HTTP_X_TIMESTAMP_WINDOW'] ?? 0);
    $signature = $_SERVER['HTTP_X_REQ_SIGNATURE'] ?? '';
    
    if (!$timestampWindow) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing timestamp window']);
        return;
    }
    
    // Validar janela temporal
    if (!SecureKeyManager::isWindowValid($timestampWindow)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired timestamp window']);
        return;
    }
    
    // Obter seed e salt do usuário
    $stmt = $pdo->prepare("SELECT master_seed, session_salt FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    // Descriptografar seed do banco
    $serverKey = getenv('SERVER_ENCRYPTION_KEY');
    $masterSeed = openssl_decrypt($user['master_seed'], 'AES-256-CBC', $serverKey, 0, substr(md5($userId), 0, 16));
    $sessionSalt = $user['session_salt'];
    
    // Obter body da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validar assinatura HMAC
    if ($signature && !CryptoManager::validateHMAC($input, $signature, $masterSeed, $sessionSalt, $timestampWindow)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        return;
    }
    
    // Descriptografar dados
    if (isset($data['encrypted']) && $data['encrypted']) {
        $decryptedData = CryptoManager::decryptAuto(
            $data['data'],
            $masterSeed,
            $sessionSalt,
            $timestampWindow
        );
        
        if ($decryptedData === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to decrypt request']);
            return;
        }
        
        $data = json_decode($decryptedData, true);
    }
    
    // Processar requisição...
    $response = [
        'success' => true,
        'message' => 'Request processed successfully',
        'data' => $data
    ];
    
    // Criptografar resposta
    $encryptedResponse = CryptoManager::encrypt(
        json_encode($response),
        $masterSeed,
        $sessionSalt,
        SecureKeyManager::getCurrentTimestampWindow()
    );
    
    echo json_encode([
        'encrypted' => true,
        'data' => $encryptedResponse
    ]);
}

/**
 * Função helper para gerar JWT
 */
function generateJWT($userId) {
    // Implementar geração de JWT
    // Usar biblioteca como firebase/php-jwt
    return 'jwt_token_here';
}
?>
