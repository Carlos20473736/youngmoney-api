<?php
/**
 * CryptoManagerV2 - Criptografia com Chaves Rotativas
 * 
 * Sistema de segurança máxima:
 * - Chaves derivadas com HKDF
 * - Rotação automática a cada 30 segundos
 * - Descriptografia automática com janelas adjacentes
 */

require_once 'SecureKeyManager.php';

class CryptoManagerV2 {
    
    /**
     * Criptografa dados usando chave rotativa
     * 
     * @param string $data Dados a criptografar
     * @param string $masterSeed Master seed (base64)
     * @param string $sessionSalt Session salt (base64)
     * @param int|null $timestampWindow Janela temporal (null = atual)
     * @return string|false Dados criptografados em base64
     */
    public static function encrypt($data, $masterSeed, $sessionSalt, $timestampWindow = null) {
        try {
            // Usar janela atual se não especificada
            if ($timestampWindow === null) {
                $timestampWindow = SecureKeyManager::getCurrentTimestampWindow();
            }
            
            // Derivar chave rotativa
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
            
            if ($encrypted === false) {
                error_log("CryptoManagerV2: Failed to encrypt data");
                return false;
            }
            
            // Concatenar IV + dados criptografados
            $combined = $iv . $encrypted;
            
            return base64_encode($combined);
            
        } catch (Exception $e) {
            error_log("CryptoManagerV2: Encryption error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografa dados usando chave rotativa específica
     * 
     * @param string $encryptedData Dados criptografados (base64)
     * @param string $masterSeed Master seed (base64)
     * @param string $sessionSalt Session salt (base64)
     * @param int $timestampWindow Janela temporal
     * @return string|false Dados descriptografados ou false
     */
    public static function decrypt($encryptedData, $masterSeed, $sessionSalt, $timestampWindow) {
        try {
            // Derivar chave rotativa
            $key = SecureKeyManager::deriveKey($masterSeed, $sessionSalt, $timestampWindow);
            
            // Decodificar base64
            $combined = base64_decode($encryptedData);
            
            if ($combined === false) {
                error_log("CryptoManagerV2: Failed to decode base64");
                return false;
            }
            
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
            
            if ($decrypted === false) {
                return false;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("CryptoManagerV2: Decryption error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografa automaticamente tentando janelas adjacentes
     * 
     * @param string $encryptedData Dados criptografados (base64)
     * @param string $masterSeed Master seed (base64)
     * @param string $sessionSalt Session salt (base64)
     * @param int $receivedWindow Janela recebida do cliente
     * @return string|false Dados descriptografados ou false
     */
    public static function decryptAuto($encryptedData, $masterSeed, $sessionSalt, $receivedWindow) {
        // Validar janela temporal
        if (!SecureKeyManager::isWindowValid($receivedWindow)) {
            error_log("CryptoManagerV2: Invalid timestamp window - too old or too new");
            return false;
        }
        
        // Tentar janela recebida
        $decrypted = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $receivedWindow);
        if ($decrypted !== false) {
            error_log("CryptoManagerV2: Decrypted with received window");
            return $decrypted;
        }
        
        // Tentar janela anterior
        $decrypted = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $receivedWindow - 1);
        if ($decrypted !== false) {
            error_log("CryptoManagerV2: Decrypted with previous window");
            return $decrypted;
        }
        
        // Tentar janela seguinte
        $decrypted = self::decrypt($encryptedData, $masterSeed, $sessionSalt, $receivedWindow + 1);
        if ($decrypted !== false) {
            error_log("CryptoManagerV2: Decrypted with next window");
            return $decrypted;
        }
        
        error_log("CryptoManagerV2: Failed to decrypt with any window");
        return false;
    }
}

?>
