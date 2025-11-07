<?php
/**
 * Crypto - Criptografia AES-256-CBC para Young Money API
 * 
 * Criptografa e descriptografa requests/responses usando AES-256-CBC
 * 
 * USO:
 * $encrypted = Crypto::encrypt($data);
 * $decrypted = Crypto::decrypt($encrypted);
 */

class Crypto {
    // Chave secreta (32 bytes para AES-256)
    // DEVE ser a mesma no app Android!
    private static $SECRET_KEY = 'young_money_crypto_key_2025_v1!!';
    
    // Método de criptografia
    private static $CIPHER = 'AES-256-CBC';
    
    /**
     * Criptografa dados usando AES-256-CBC
     * 
     * @param mixed $data Dados para criptografar (será convertido para JSON)
     * @return string Dados criptografados em base64
     */
    public static function encrypt($data) {
        try {
            // Converter para JSON se não for string
            if (!is_string($data)) {
                $data = json_encode($data);
            }
            
            // Gerar IV aleatório (16 bytes para AES)
            $iv = openssl_random_pseudo_bytes(16);
            
            // Criptografar
            $encrypted = openssl_encrypt(
                $data,
                self::$CIPHER,
                self::$SECRET_KEY,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                throw new Exception('Erro ao criptografar dados');
            }
            
            // Retornar IV + dados criptografados em base64
            return base64_encode($iv . $encrypted);
            
        } catch (Exception $e) {
            error_log("Crypto::encrypt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografa dados usando AES-256-CBC
     * 
     * @param string $encryptedData Dados criptografados em base64
     * @param bool $asJson Se true, retorna array decodificado do JSON
     * @return mixed Dados descriptografados
     */
    public static function decrypt($encryptedData, $asJson = true) {
        try {
            // Decodificar base64
            $data = base64_decode($encryptedData);
            
            if ($data === false) {
                throw new Exception('Dados inválidos (base64)');
            }
            
            // Extrair IV (primeiros 16 bytes)
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            // Descriptografar
            $decrypted = openssl_decrypt(
                $encrypted,
                self::$CIPHER,
                self::$SECRET_KEY,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                throw new Exception('Erro ao descriptografar dados');
            }
            
            // Decodificar JSON se solicitado
            if ($asJson) {
                $decoded = json_decode($decrypted, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Se não for JSON válido, retornar string
                    return $decrypted;
                }
                return $decoded;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("Crypto::decrypt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia resposta criptografada em JSON
     * 
     * @param array $data Dados para enviar
     * @param int $httpCode Código HTTP (padrão 200)
     */
    public static function sendEncryptedResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        
        // Criptografar dados
        $encrypted = self::encrypt($data);
        
        if ($encrypted === false) {
            // Fallback: enviar sem criptografia em caso de erro
            echo json_encode($data);
        } else {
            // Enviar dados criptografados
            echo json_encode([
                'encrypted' => true,
                'data' => $encrypted
            ]);
        }
        
        exit;
    }
    
    /**
     * Recebe e descriptografa request body
     * 
     * @return mixed Dados descriptografados
     */
    public static function receiveEncryptedRequest() {
        try {
            // Ler body da requisição
            $body = file_get_contents('php://input');
            
            if (empty($body)) {
                return null;
            }
            
            // Decodificar JSON
            $json = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON inválido');
            }
            
            // Verificar se está criptografado
            if (isset($json['encrypted']) && $json['encrypted'] === true) {
                // Descriptografar
                return self::decrypt($json['data'], true);
            }
            
            // Não está criptografado, retornar como está
            return $json;
            
        } catch (Exception $e) {
            error_log("Crypto::receiveEncryptedRequest error: " . $e->getMessage());
            return null;
        }
    }
}
?>
