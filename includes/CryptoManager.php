<?php
/**
 * CryptoManager - Descriptografia compatível com Android
 * 
 * Esta classe descriptografa dados enviados pelo app Android usando
 * a mesma chave estática (V1 - compatibilidade).
 * 
 * IMPORTANTE: Esta é a versão V1 (chave estática) para compatibilidade.
 * Quando implementar segurança máxima, use SecureKeyManager + CryptoManagerV2.
 */

class CryptoManager {
    
    /**
     * Chave secreta (DEVE SER A MESMA DO ANDROID)
     * 
     * IMPORTANTE: Esta chave DEVE ser idêntica à do CryptoManager.java
     * Por segurança, mova para variável de ambiente.
     */
    private static $SECRET_KEY = "young_money_crypto_key_2025_v1!!"; // DEVE SER IGUAL AO ANDROID
    
    /**
     * Descriptografa dados criptografados pelo Android
     * 
     * @param string $encryptedData Dados criptografados em base64
     * @return string|false Dados descriptografados (JSON) ou false em caso de erro
     */
    public static function decrypt($encryptedData) {
        try {
            // Decodificar base64
            $combined = base64_decode($encryptedData);
            
            if ($combined === false) {
                error_log("CryptoManager: Failed to decode base64");
                return false;
            }
            
            // Extrair IV (primeiros 16 bytes)
            $iv = substr($combined, 0, 16);
            $encrypted = substr($combined, 16);
            
            // Derivar chave de 32 bytes usando SHA-256
            $key = hash('sha256', self::$SECRET_KEY, true);
            
            // Descriptografar usando AES-256-CBC
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                error_log("CryptoManager: Failed to decrypt data");
                return false;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("CryptoManager: Exception during decryption - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criptografa dados para enviar ao Android
     * 
     * @param string $data Dados a criptografar (JSON)
     * @return string|false Dados criptografados em base64 ou false em caso de erro
     */
    public static function encrypt($data) {
        try {
            // Derivar chave de 32 bytes usando SHA-256
            $key = hash('sha256', self::$SECRET_KEY, true);
            
            // Gerar IV aleatório (16 bytes)
            $iv = openssl_random_pseudo_bytes(16);
            
            // Criptografar usando AES-256-CBC
            $encrypted = openssl_encrypt(
                $data,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                error_log("CryptoManager: Failed to encrypt data");
                return false;
            }
            
            // Concatenar IV + dados criptografados
            $combined = $iv . $encrypted;
            
            // Codificar em base64
            return base64_encode($combined);
            
        } catch (Exception $e) {
            error_log("CryptoManager: Exception during encryption - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configura a chave secreta (recomendado usar variável de ambiente)
     * 
     * @param string $key Nova chave secreta
     */
    public static function setSecretKey($key) {
        self::$SECRET_KEY = $key;
    }
    
    /**
     * Obtém a chave secreta de variável de ambiente
     * 
     * @return bool true se carregou com sucesso
     */
    public static function loadKeyFromEnv() {
        $envKey = getenv('CRYPTO_SECRET_KEY');
        
        if ($envKey && !empty($envKey)) {
            self::$SECRET_KEY = $envKey;
            return true;
        }
        
        return false;
    }
}

// Tentar carregar chave de variável de ambiente automaticamente
CryptoManager::loadKeyFromEnv();

?>
