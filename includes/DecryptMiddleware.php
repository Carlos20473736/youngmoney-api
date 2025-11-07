<?php
/**
 * DecryptMiddleware - Middleware para descriptografar requisições
 * 
 * Este middleware intercepta todas as requisições e descriptografa
 * automaticamente se estiverem criptografadas.
 */

require_once 'CryptoManager.php';

class DecryptMiddleware {
    
    /**
     * Processa a requisição e descriptografa se necessário
     * 
     * @return array Dados da requisição (descriptografados se necessário)
     */
    public static function processRequest() {
        // Obter body da requisição
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return [];
        }
        
        // Tentar decodificar JSON
        $data = json_decode($input, true);
        
        if ($data === null) {
            error_log("DecryptMiddleware: Failed to decode JSON");
            return [];
        }
        
        // Verificar se está criptografado
        if (isset($data['encrypted']) && $data['encrypted'] === true) {
            
            if (!isset($data['data'])) {
                error_log("DecryptMiddleware: Encrypted flag set but no data field");
                return [];
            }
            
            // Descriptografar
            $decrypted = CryptoManager::decrypt($data['data']);
            
            if ($decrypted === false) {
                error_log("DecryptMiddleware: Failed to decrypt request");
                return [];
            }
            
            // Decodificar JSON descriptografado
            $decryptedData = json_decode($decrypted, true);
            
            if ($decryptedData === null) {
                error_log("DecryptMiddleware: Failed to decode decrypted JSON");
                return [];
            }
            
            error_log("DecryptMiddleware: Request decrypted successfully");
            return $decryptedData;
            
        } else {
            // Não está criptografado, retornar dados originais
            error_log("DecryptMiddleware: Request not encrypted");
            return $data;
        }
    }
    
    /**
     * Envia resposta criptografada
     * 
     * @param array $response Dados da resposta
     * @param bool $encrypt Se deve criptografar a resposta
     */
    public static function sendResponse($response, $encrypt = true) {
        header('Content-Type: application/json');
        
        $jsonResponse = json_encode($response);
        
        if ($encrypt) {
            // Criptografar resposta
            $encrypted = CryptoManager::encrypt($jsonResponse);
            
            if ($encrypted !== false) {
                echo json_encode([
                    'encrypted' => true,
                    'data' => $encrypted
                ]);
                error_log("DecryptMiddleware: Response encrypted successfully");
            } else {
                // Se falhar ao criptografar, enviar sem criptografia
                echo $jsonResponse;
                error_log("DecryptMiddleware: Failed to encrypt response, sending plain");
            }
        } else {
            // Enviar sem criptografia
            echo $jsonResponse;
        }
    }
    
    /**
     * Envia erro em JSON
     * 
     * @param string $message Mensagem de erro
     * @param int $httpCode Código HTTP
     */
    public static function sendError($message, $httpCode = 400) {
        http_response_code($httpCode);
        self::sendResponse([
            'success' => false,
            'error' => $message
        ], false); // Erros não são criptografados para facilitar debug
    }
    
    /**
     * Envia sucesso em JSON
     * 
     * @param mixed $data Dados de sucesso
     * @param bool $encrypt Se deve criptografar
     */
    public static function sendSuccess($data, $encrypt = true) {
        self::sendResponse([
            'success' => true,
            'data' => $data
        ], $encrypt);
    }
}

?>
