<?php
/**
 * SecureKeyManager - Gerenciamento de Seeds e Derivação de Chaves
 * 
 * Sistema de segurança máxima V2:
 * - Chaves derivadas com HKDF-SHA256
 * - Rotação automática a cada 30 segundos
 * - Chave NUNCA transmitida
 */

class SecureKeyManager {
    
    // Janela temporal (30 segundos)
    const WINDOW_SIZE_MS = 30000;
    
    // Tolerância de janelas (±1 = 90 segundos total)
    const WINDOW_TOLERANCE = 1;
    
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
        $key = self::deriveKey($masterSeed, $sessionSalt, $timestampWindow);
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
    
    /**
     * Obtém seed e salt do usuário do banco de dados
     * 
     * @param PDO $pdo Conexão com banco
     * @param int $userId ID do usuário
     * @return array|false Array com master_seed e session_salt ou false
     */
    public static function getUserSecrets($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("
                SELECT master_seed, session_salt 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['master_seed'] || !$user['session_salt']) {
                return false;
            }
            
            // Descriptografar seed do banco (criptografado com chave do servidor)
            $serverKey = getenv('SERVER_ENCRYPTION_KEY');
            if (!$serverKey) {
                error_log("SecureKeyManager: SERVER_ENCRYPTION_KEY not set");
                return false;
            }
            
            $iv = substr(md5($userId), 0, 16);
            $masterSeed = openssl_decrypt(
                $user['master_seed'],
                'AES-256-CBC',
                $serverKey,
                0,
                $iv
            );
            
            if ($masterSeed === false) {
                error_log("SecureKeyManager: Failed to decrypt master seed");
                return false;
            }
            
            return [
                'master_seed' => $masterSeed,
                'session_salt' => $user['session_salt']
            ];
            
        } catch (PDOException $e) {
            error_log("SecureKeyManager: Database error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Armazena seed e salt do usuário no banco de dados
     * 
     * @param PDO $pdo Conexão com banco
     * @param int $userId ID do usuário
     * @param string $masterSeed Master seed (base64)
     * @param string $sessionSalt Session salt (base64)
     * @return bool true se armazenado com sucesso
     */
    public static function storeUserSecrets($pdo, $userId, $masterSeed, $sessionSalt) {
        try {
            // Criptografar seed com chave do servidor
            $serverKey = getenv('SERVER_ENCRYPTION_KEY');
            if (!$serverKey) {
                error_log("SecureKeyManager: SERVER_ENCRYPTION_KEY not set");
                return false;
            }
            
            $iv = substr(md5($userId), 0, 16);
            $encryptedSeed = openssl_encrypt(
                $masterSeed,
                'AES-256-CBC',
                $serverKey,
                0,
                $iv
            );
            
            if ($encryptedSeed === false) {
                error_log("SecureKeyManager: Failed to encrypt master seed");
                return false;
            }
            
            // Armazenar no banco
            $stmt = $pdo->prepare("
                UPDATE users 
                SET master_seed = ?, 
                    session_salt = ?, 
                    salt_updated_at = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$encryptedSeed, $sessionSalt, $userId]);
            
        } catch (PDOException $e) {
            error_log("SecureKeyManager: Database error - " . $e->getMessage());
            return false;
        }
    }
}

?>
